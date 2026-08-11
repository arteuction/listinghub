#!/usr/bin/env python3
"""Build ListingHub's Bulgaria-only geo and population JSON datasets.

The script intentionally keeps stable EKATTE geography separate from dated
GRAO population snapshots. It uses only Python's standard library.
"""

from __future__ import annotations

import argparse
import collections
import datetime as dt
import hashlib
import json
import re
import sqlite3
import unicodedata
from pathlib import Path


NSI_EKATTE_URL = "https://www.nsi.bg/nrnm/ekatte/index"
NSI_SPATIAL_URL = "https://www.nsi.bg/nrnm/spatial-data-files"
GRAO_URL = "https://www.grao.bg/tna/t41nm-15-06-2026_2.txt"


def normalize(value: str) -> str:
    value = unicodedata.normalize("NFKC", value).upper().strip()
    return re.sub(r"\s+", " ", value)


def load_json(path: Path) -> list[dict]:
    with path.open(encoding="utf-8") as handle:
        value = json.load(handle)
    if not isinstance(value, list):
        raise ValueError(f"Expected a JSON array in {path}")
    return value


def load_grao_text(path: Path) -> tuple[str, str]:
    raw = path.read_bytes()
    try:
        text = raw.decode("utf-8")
        source_encoding = "UTF-8"
    except UnicodeDecodeError:
        text = raw.decode("windows-1251")
        source_encoding = "Windows-1251"

    text = text.replace("\r\n", "\n").replace("\r", "\n")
    lines = text.splitlines()
    if lines and lines[0].strip() == "```":
        lines = lines[1:]
    if lines and lines[-1].strip() == "```":
        lines = lines[:-1]
    return "\n".join(lines) + "\n", source_encoding


def read_spatial_points(path: Path) -> dict[str, dict]:
    connection = sqlite3.connect(path)
    try:
        rows = connection.execute(
            """
            SELECT Identifier, Name, NameLatin, TypeCode, MunCode, DistrCode,
                   xcoord, ycoord, VersionId
            FROM Naseleni_mesta
            ORDER BY Identifier
            """
        ).fetchall()
    finally:
        connection.close()

    points: dict[str, dict] = {}
    for identifier, name, name_en, type_code, municipality, region, lng, lat, version in rows:
        points[identifier] = {
            "name": name,
            "name_en": name_en,
            "type_code": type_code,
            "municipality": municipality,
            "region": region,
            "lat": round(float(lat), 7),
            "lng": round(float(lng), 7),
            "version": version,
        }
    return points


def center_for(identifier: str, points: dict[str, dict], label: str) -> tuple[float, float]:
    point = points.get(identifier)
    if point is None:
        raise ValueError(f"Missing spatial center for {label}: EKATTE {identifier}")
    return point["lat"], point["lng"]


def build_geo(
    regions: list[dict],
    municipalities: list[dict],
    settlements: list[dict],
    points: dict[str, dict],
    generated_at: str,
) -> tuple[dict, dict]:
    region_rows = [row for row in regions if "oblast" in row]
    municipality_rows = [row for row in municipalities if "obshtina" in row]
    settlement_rows = [row for row in settlements if "ekatte" in row]

    if len(region_rows) != 28:
        raise ValueError(f"Expected 28 regions, got {len(region_rows)}")
    if len(municipality_rows) != 265:
        raise ValueError(f"Expected 265 municipalities, got {len(municipality_rows)}")
    if len(settlement_rows) != 5256:
        raise ValueError(f"Expected 5256 settlements, got {len(settlement_rows)}")

    settlement_ids = {row["ekatte"] for row in settlement_rows}
    point_ids = set(points)
    if settlement_ids != point_ids:
        raise ValueError(
            "EKATTE/spatial identifier mismatch: "
            f"missing points={sorted(settlement_ids - point_ids)[:10]}, "
            f"extra points={sorted(point_ids - settlement_ids)[:10]}"
        )

    municipalities_by_region: dict[str, list[dict]] = collections.defaultdict(list)
    for municipality in municipality_rows:
        municipalities_by_region[municipality["obshtina"][:3]].append(municipality)

    settlements_by_municipality: dict[str, list[dict]] = collections.defaultdict(list)
    for settlement in settlement_rows:
        settlements_by_municipality[settlement["obshtina"]].append(settlement)

    type_names = {1: "town", 3: "village", 7: "monastery"}
    output_regions = []
    type_counts: collections.Counter[str] = collections.Counter()
    seen_municipalities = 0
    seen_settlements = 0

    for region in sorted(region_rows, key=lambda item: item["oblast"]):
        region_lat, region_lng = center_for(region["ekatte"], points, region["name"])
        output_municipalities = []
        for municipality in sorted(
            municipalities_by_region[region["oblast"]], key=lambda item: item["obshtina"]
        ):
            municipality_lat, municipality_lng = center_for(
                municipality["ekatte"], points, municipality["name"]
            )
            output_settlements = []
            for settlement in sorted(
                settlements_by_municipality[municipality["obshtina"]],
                key=lambda item: item["ekatte"],
            ):
                point = points[settlement["ekatte"]]
                settlement_type = type_names.get(settlement["kind"])
                if settlement_type is None:
                    raise ValueError(
                        f"Unsupported settlement type {settlement['kind']} for {settlement['ekatte']}"
                    )
                output_settlements.append(
                    {
                        "name": settlement["name"],
                        "ekatte": settlement["ekatte"],
                        "type": settlement_type,
                        "lat": point["lat"],
                        "lng": point["lng"],
                    }
                )
                type_counts[settlement_type] += 1
                seen_settlements += 1

            output_municipalities.append(
                {
                    "name": municipality["name"],
                    "code": municipality["obshtina"],
                    "lat": municipality_lat,
                    "lng": municipality_lng,
                    "settlements": output_settlements,
                }
            )
            seen_municipalities += 1

        output_regions.append(
            {
                "name": region["name"],
                "code": region["oblast"],
                "lat": region_lat,
                "lng": region_lng,
                "municipalities": output_municipalities,
            }
        )

    geo = {
        "_comment": "Generated Bulgaria-only geography from official NSI EKATTE and spatial data.",
        "_version": "1.0.0",
        "_source": NSI_EKATTE_URL,
        "_spatial_source": NSI_SPATIAL_URL,
        "_generated_at": generated_at,
        "country": {"name": "България", "iso2": "BG"},
        "regions": output_regions,
    }
    validation = {
        "regions": len(output_regions),
        "municipalities": seen_municipalities,
        "settlements": seen_settlements,
        "settlement_types": dict(sorted(type_counts.items())),
        "spatial_version": sorted({point["version"] for point in points.values()}),
        "coordinate_bounds": {
            "min_lat": min(point["lat"] for point in points.values()),
            "max_lat": max(point["lat"] for point in points.values()),
            "min_lng": min(point["lng"] for point in points.values()),
            "max_lng": max(point["lng"] for point in points.values()),
        },
    }
    return geo, validation


def parse_grao(
    text: str,
    regions: list[dict],
    municipalities: list[dict],
    settlements: list[dict],
) -> tuple[list[dict], dict]:
    region_rows = [row for row in regions if "oblast" in row]
    municipality_rows = [row for row in municipalities if "obshtina" in row]
    settlement_rows = [row for row in settlements if "ekatte" in row]

    region_by_name = {normalize(row["name"]): row["oblast"] for row in region_rows}
    # GRAO uses these two historical presentation labels.
    region_by_name["СОФИЯ"] = "SOF"
    region_by_name["СОФИЙСКА"] = "SFO"

    municipality_by_region_name = {
        (row["obshtina"][:3], normalize(row["name"])): row["obshtina"]
        for row in municipality_rows
    }
    municipality_aliases = {
        ("DOB", "ДОБРИЧКА"): "DOB15",
        ("DOB", "ДОБРИЧ-ГРАД"): "DOB28",
    }

    candidates: dict[tuple[str, int, str], list[str]] = collections.defaultdict(list)
    settlement_by_id = {row["ekatte"]: row for row in settlement_rows}
    for row in settlement_rows:
        candidates[(row["obshtina"], row["kind"], normalize(row["name"]))].append(
            row["ekatte"]
        )
    for values in candidates.values():
        values.sort()

    explicit_labels = {
        ("VID16", 3, "ОРЕШЕЦ (ГАРА ОРЕШЕЦ)"): "53758",
        ("DOB03", 3, "ОБРОЧИЩЕ,К.К.АЛБЕНА"): "53120",
        ("SFO17", 3, "ЕЛИН ПЕЛИН (ГАРА ЕЛИН П"): "18490",
        ("SFO43", 3, "БОВ (ГАРА БОВ)"): "14461",
        ("SFO43", 3, "ЛАКАТНИК(ГАРА ЛАКАТНИК)"): "43092",
    }
    prefix_types = {"ГР.": 1, "С.": 3, "МАН.": 7}
    header_pattern = re.compile(r"^\s*област (.+?) община (.+?)\s*$")
    row_pattern = re.compile(
        r"^\|(ГР\.|С\.|МАН\.)([^|]+)\|\s*(\d+)\|\s*(\d+)\|\s*(\d+)\|$"
    )
    total_pattern = re.compile(
        r"^\|Всичко за общината\s*\|\s*(\d+)\|\s*(\d+)\|\s*(\d+)\|$"
    )

    current: tuple[str, str, str, str] | None = None
    source_rows: list[dict] = []
    source_sums: dict[tuple[str, str], list[int]] = collections.defaultdict(lambda: [0, 0, 0])
    source_totals: dict[tuple[str, str], list[int]] = {}
    duplicate_occurrence: collections.Counter[tuple[str, int, str]] = collections.Counter()
    match_methods: collections.Counter[str] = collections.Counter()
    unmatched: list[dict] = []

    for line in text.splitlines():
        header = header_pattern.match(line)
        if header:
            region_name, municipality_name = map(normalize, header.groups())
            region_code = region_by_name.get(region_name)
            municipality_code = None
            if region_code:
                municipality_code = municipality_aliases.get(
                    (region_code, municipality_name),
                    municipality_by_region_name.get((region_code, municipality_name)),
                )
            if not region_code or not municipality_code:
                raise ValueError(
                    f"Unknown GRAO header: region={region_name}, municipality={municipality_name}"
                )
            current = (region_name, municipality_name, region_code, municipality_code)
            continue

        total = total_pattern.match(line)
        if total:
            if current is None:
                raise ValueError("GRAO municipality total before a municipality header")
            source_totals[(current[2], current[3])] = [int(value) for value in total.groups()]
            continue

        row = row_pattern.match(line)
        if not row:
            continue
        if current is None:
            raise ValueError("GRAO settlement row before a municipality header")

        prefix, source_name, permanent, current_address, same_settlement = row.groups()
        type_code = prefix_types[prefix]
        normalized_name = normalize(source_name)
        municipality_code = current[3]
        lookup_key = (municipality_code, type_code, normalized_name)

        ekatte = explicit_labels.get(lookup_key)
        match_method = "explicit-alias" if ekatte else "exact"

        if not ekatte and municipality_code == "SOF46" and normalized_name.startswith("СОФИЯ,"):
            ekatte = "68134"
            match_method = "sofia-quarter-aggregate"

        if not ekatte:
            possible = candidates.get(lookup_key, [])
            if len(possible) == 1:
                ekatte = possible[0]
            elif len(possible) > 1:
                index = duplicate_occurrence[lookup_key]
                if index < len(possible):
                    ekatte = possible[index]
                    duplicate_occurrence[lookup_key] += 1
                    match_method = "duplicate-name-occurrence"

        values = [int(permanent), int(current_address), int(same_settlement)]
        source_sums[(current[2], current[3])][0] += values[0]
        source_sums[(current[2], current[3])][1] += values[1]
        source_sums[(current[2], current[3])][2] += values[2]

        parsed = {
            "ekatte": ekatte,
            "source_name": f"{prefix}{source_name.strip()}",
            "region_code": current[2],
            "municipality_code": municipality_code,
            "permanent_address_total": values[0],
            "current_address_total": values[1],
            "permanent_and_current_same_settlement_total": values[2],
        }
        source_rows.append(parsed)
        if ekatte:
            match_methods[match_method] += 1
        else:
            unmatched.append(parsed)

    if unmatched:
        raise ValueError(f"Unmatched GRAO rows ({len(unmatched)}): {unmatched[:10]}")
    if len(source_totals) != 265:
        raise ValueError(f"Expected 265 GRAO municipality totals, got {len(source_totals)}")

    total_mismatches = []
    for key, expected in source_totals.items():
        actual = source_sums[key]
        if actual != expected:
            total_mismatches.append({"key": key, "actual": actual, "expected": expected})
    if total_mismatches:
        raise ValueError(f"GRAO municipality total mismatches: {total_mismatches[:10]}")

    aggregated: dict[str, dict] = {}
    for row in source_rows:
        ekatte = row["ekatte"]
        if ekatte not in aggregated:
            official = settlement_by_id[ekatte]
            aggregated[ekatte] = {
                "ekatte": ekatte,
                "name": official["name"],
                "region_code": official["oblast"],
                "municipality_code": official["obshtina"],
                "permanent_address_total": 0,
                "current_address_total": 0,
                "permanent_and_current_same_settlement_total": 0,
                "source_row_count": 0,
            }
        target = aggregated[ekatte]
        target["permanent_address_total"] += row["permanent_address_total"]
        target["current_address_total"] += row["current_address_total"]
        target["permanent_and_current_same_settlement_total"] += row[
            "permanent_and_current_same_settlement_total"
        ]
        target["source_row_count"] += 1

    validation = {
        "source_rows": len(source_rows),
        "matched_settlements": len(aggregated),
        "settlements_without_grao_row": len(settlement_rows) - len(aggregated),
        "municipality_totals_validated": len(source_totals),
        "unmatched_rows": 0,
        "match_methods": dict(sorted(match_methods.items())),
        "aggregated_rows": sum(row["source_row_count"] - 1 for row in aggregated.values()),
    }
    return [aggregated[key] for key in sorted(aggregated)], validation


def write_json(path: Path, value: object) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8", newline="\n") as handle:
        json.dump(value, handle, ensure_ascii=False, indent=2)
        handle.write("\n")


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for block in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(block)
    return digest.hexdigest()


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--ekatte-json-dir", type=Path, required=True)
    parser.add_argument("--settlements-gpkg", type=Path, required=True)
    parser.add_argument("--grao", type=Path, required=True)
    parser.add_argument("--geo-output", type=Path, required=True)
    parser.add_argument("--population-output", type=Path, required=True)
    parser.add_argument("--report-output", type=Path, required=True)
    parser.add_argument(
        "--generated-at",
        default=dt.datetime.now(dt.timezone.utc).replace(microsecond=0).isoformat(),
    )
    args = parser.parse_args()

    regions = load_json(args.ekatte_json_dir / "ek_obl.json")
    municipalities = load_json(args.ekatte_json_dir / "ek_obst.json")
    settlements = load_json(args.ekatte_json_dir / "ek_atte.json")
    points = read_spatial_points(args.settlements_gpkg)
    grao_text, _input_encoding = load_grao_text(args.grao)

    geo, geo_validation = build_geo(
        regions, municipalities, settlements, points, args.generated_at
    )
    population_rows, population_validation = parse_grao(
        grao_text, regions, municipalities, settlements
    )
    population = {
        "_comment": "Dated GRAO registered-address snapshot joined to official EKATTE identifiers.",
        "_version": "1.0.0",
        "_source": GRAO_URL,
        "_source_encoding": "Windows-1251",
        "_generated_at": args.generated_at,
        "as_of": "2026-06-15",
        "country": {"name": "България", "iso2": "BG"},
        "settlements": population_rows,
    }

    write_json(args.geo_output, geo)
    write_json(args.population_output, population)
    report = {
        "status": "passed",
        "generated_at": args.generated_at,
        "sources": {
            "nsi_ekatte": NSI_EKATTE_URL,
            "nsi_spatial": NSI_SPATIAL_URL,
            "grao": GRAO_URL,
        },
        "geo": geo_validation,
        "population": population_validation,
        "outputs": {
            "bg_geo_sha256": sha256(args.geo_output),
            "bg_population_sha256": sha256(args.population_output),
        },
        "known_records": {
            "bansko": points["02676"],
            "dobrinishte": points["21498"],
            "sofia": points["68134"],
        },
    }
    write_json(args.report_output, report)


if __name__ == "__main__":
    main()

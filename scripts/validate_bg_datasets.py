#!/usr/bin/env python3
"""Validate committed ListingHub Bulgaria geo and population datasets."""

from __future__ import annotations

import argparse
import json
from pathlib import Path


def load(path: Path) -> dict:
    with path.open(encoding="utf-8") as handle:
        value = json.load(handle)
    if not isinstance(value, dict):
        raise ValueError(f"Expected a JSON object in {path}")
    return value


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--geo", type=Path, required=True)
    parser.add_argument("--population", type=Path, required=True)
    args = parser.parse_args()

    geo = load(args.geo)
    population = load(args.population)

    assert geo["country"] == {"name": "България", "iso2": "BG"}
    assert population["country"] == {"name": "България", "iso2": "BG"}
    assert population["as_of"] == "2026-06-15"

    regions = geo["regions"]
    assert len(regions) == 28, len(regions)
    region_codes = [region["code"] for region in regions]
    assert len(set(region_codes)) == 28

    municipality_codes: set[str] = set()
    settlement_by_ekatte: dict[str, tuple[str, str]] = {}
    type_counts = {"town": 0, "village": 0, "monastery": 0}

    for region in regions:
        assert 41.0 <= region["lat"] <= 44.5
        assert 22.0 <= region["lng"] <= 29.0
        for municipality in region["municipalities"]:
            municipality_code = municipality["code"]
            assert municipality_code.startswith(region["code"])
            assert municipality_code not in municipality_codes
            municipality_codes.add(municipality_code)
            assert 41.0 <= municipality["lat"] <= 44.5
            assert 22.0 <= municipality["lng"] <= 29.0

            for settlement in municipality["settlements"]:
                ekatte = settlement["ekatte"]
                assert len(ekatte) == 5 and ekatte.isdigit(), ekatte
                assert ekatte not in settlement_by_ekatte, ekatte
                settlement_by_ekatte[ekatte] = (region["code"], municipality_code)
                assert settlement["type"] in type_counts
                type_counts[settlement["type"]] += 1
                assert 41.0 <= settlement["lat"] <= 44.5
                assert 22.0 <= settlement["lng"] <= 29.0

    assert len(municipality_codes) == 265, len(municipality_codes)
    assert len(settlement_by_ekatte) == 5256, len(settlement_by_ekatte)
    assert type_counts == {"town": 257, "village": 4997, "monastery": 2}
    assert settlement_by_ekatte["02676"] == ("BLG", "BLG01")
    assert settlement_by_ekatte["21498"] == ("BLG", "BLG01")
    assert settlement_by_ekatte["68134"] == ("SOF", "SOF46")

    population_ekatte: set[str] = set()
    for row in population["settlements"]:
        ekatte = row["ekatte"]
        assert ekatte in settlement_by_ekatte, ekatte
        assert ekatte not in population_ekatte, ekatte
        population_ekatte.add(ekatte)
        expected_region, expected_municipality = settlement_by_ekatte[ekatte]
        assert row["region_code"] == expected_region
        assert row["municipality_code"] == expected_municipality
        permanent = row["permanent_address_total"]
        current = row["current_address_total"]
        same = row["permanent_and_current_same_settlement_total"]
        assert permanent >= 0 and current >= 0 and same >= 0
        assert same <= permanent and same <= current
        assert row["source_row_count"] >= 1

    assert len(population_ekatte) == 5120, len(population_ekatte)
    print(
        "BG datasets valid: "
        f"{len(regions)} regions, {len(municipality_codes)} municipalities, "
        f"{len(settlement_by_ekatte)} settlements, "
        f"{len(population_ekatte)} population records"
    )


if __name__ == "__main__":
    main()

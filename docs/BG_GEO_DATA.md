# ListingHub — Bulgaria geo data

This package separates stable administrative geography from dated demographic
snapshots.

## Files

- `database/data/bg-geo.json` — 28 regions, 265 municipalities and 5,256
  settlements, keyed by official EKATTE codes. Settlement coordinates are WGS
  84 points from NSI spatial data.
- `database/data/bg-population/grao-2026-06-15.json` — registered-address
  counts from GRAO as of 15 June 2026, joined to EKATTE identifiers.
- `scripts/build_bg_datasets.py` — reproducible generator from official source
  files.
- `scripts/validate_bg_datasets.py` — offline CI gate for the committed JSON.
- `validation-report.json` — counts, coordinate bounds, matching statistics and
  SHA-256 hashes for this generated version.

## Authoritative sources

- EKATTE JSON archive: <https://www.nsi.bg/nrnm/ekatte/index>
- NSI settlement point layer: <https://www.nsi.bg/nrnm/spatial-data-files>
- GRAO snapshot: <https://www.grao.bg/tna/t41nm-15-06-2026_2.txt>

The NSI spatial dataset states that it is freely usable when NSI is cited. The
GRAO file is a population snapshot, not a substitute for EKATTE and not a
coordinate source.

## Important corrections to the example schema

- The official municipality code for Столична is `SOF46`, not `SOF01`.
- The official region labels are kept as supplied by NSI. `SOF` is София
  (столица), while `SFO` is София.
- JSON source fields contain plain URLs, not Markdown link syntax.
- Region and municipality coordinates use the official point of their
  administrative centre. Settlement coordinates come directly from the NSI
  WGS 84 point layer.

## Update workflow

1. Download and extract the current NSI EKATTE JSON archive.
2. Download and extract the current NSI settlement GeoPackage.
3. Download the desired GRAO `t41nm` snapshot. The legacy text is normally
   Windows-1251; the generator also accepts the same content wrapped in a
   UTF-8 Markdown code fence.
4. Run the generator with the three sources.
5. Run the offline validator.
6. Review `validation-report.json`. Changes in expected counts or unmatched
   source rows must be investigated before merge.

Example:

```bash
python3 scripts/build_bg_datasets.py \
  --ekatte-json-dir /path/to/Ekatte \
  --settlements-gpkg /path/to/SU_BG_NSI_NM_2025_1.gpkg \
  --grao /path/to/t41nm-15-06-2026_2.txt \
  --geo-output database/data/bg-geo.json \
  --population-output database/data/bg-population/grao-2026-06-15.json \
  --report-output validation-report.json

python3 scripts/validate_bg_datasets.py \
  --geo database/data/bg-geo.json \
  --population database/data/bg-population/grao-2026-06-15.json
```

## Application rule

ListingHub should treat EKATTE as the permanent identity. Human-readable names,
coordinates and population values are attributes that can change. Population
must therefore be updated as a dated snapshot and must not rewrite listing
foreign keys.

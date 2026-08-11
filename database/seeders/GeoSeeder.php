<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Municipality;
use App\Models\Region;
use App\Models\Settlement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Seeds the Bulgarian geo hierarchy from database/data/bg-geo.json.
 *
 * ListingHub is a Bulgaria-only platform. The dataset is the official NSI
 * EKATTE geography (28 regions, 265 municipalities, 5,256 settlements); see
 * docs/BG_GEO_DATA.md for provenance and the update workflow.
 *
 * IDENTITY: rows are matched on the authoritative codes — region code,
 * municipality code and EKATTE — never on the slug. Seven pairs of settlements
 * share a name inside one municipality (гара Бов and село Бов, the town and the
 * village of Костенец, …); keying on the slug would silently collapse them.
 * Slugs are derived attributes and are disambiguated with the code whenever two
 * names transliterate to the same value.
 */
class GeoSeeder extends Seeder
{
    /** Rows per upsert. Keeps the statement well inside MySQL's packet limit. */
    private const CHUNK = 500;

    public function run(): void
    {
        $path = database_path('data/bg-geo.json');

        if (! is_file($path)) {
            $this->command?->warn('GeoSeeder: database/data/bg-geo.json not found — skipping.');

            return;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data) || ! isset($data['regions']) || ! is_array($data['regions'])) {
            throw new RuntimeException('GeoSeeder: bg-geo.json is missing a regions array.');
        }

        $country = Country::updateOrCreate(
            ['iso2' => 'BG'],
            ['name' => $data['country']['name'] ?? 'България', 'slug' => 'bulgaria'],
        );

        $regionIds = $this->seedRegions($data['regions'], (int) $country->getKey());
        $municipalityIds = $this->seedMunicipalities($data['regions'], $regionIds);
        $settlements = $this->seedSettlements($data['regions'], $municipalityIds);

        $this->command?->info(sprintf(
            'GeoSeeder: %d regions, %d municipalities, %d settlements.',
            count($regionIds),
            count($municipalityIds),
            $settlements,
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $regions
     * @return array<string, int> region code => id
     */
    private function seedRegions(array $regions, int $countryId): array
    {
        $slugs = $this->uniqueSlugs(array_map(
            fn (array $r): array => ['key' => (string) $r['code'], 'name' => (string) $r['name']],
            $regions,
        ));

        $rows = [];

        foreach ($regions as $region) {
            $code = (string) $region['code'];
            $rows[] = [
                'country_id' => $countryId,
                'code' => $code,
                'name' => $region['name'],
                'slug' => $slugs[$code],
                'latitude' => $region['lat'] ?? null,
                'longitude' => $region['lng'] ?? null,
            ];
        }

        Region::upsert($rows, ['code'], ['country_id', 'name', 'slug', 'latitude', 'longitude']);

        /** @var array<string, int> $ids */
        $ids = Region::query()->pluck('id', 'code')->all();

        return $ids;
    }

    /**
     * @param  list<array<string, mixed>>  $regions
     * @param  array<string, int>  $regionIds
     * @return array<string, int> municipality code => id
     */
    private function seedMunicipalities(array $regions, array $regionIds): array
    {
        $rows = [];

        foreach ($regions as $region) {
            $regionId = $regionIds[(string) $region['code']]
                ?? throw new RuntimeException("GeoSeeder: unseeded region {$region['code']}.");

            $municipalities = $region['municipalities'] ?? [];

            // Slugs only need to be unique within their region.
            $slugs = $this->uniqueSlugs(array_map(
                fn (array $m): array => ['key' => (string) $m['code'], 'name' => (string) $m['name']],
                $municipalities,
            ));

            foreach ($municipalities as $municipality) {
                $code = (string) $municipality['code'];
                $rows[] = [
                    'region_id' => $regionId,
                    'code' => $code,
                    'name' => $municipality['name'],
                    'slug' => $slugs[$code],
                    'latitude' => $municipality['lat'] ?? null,
                    'longitude' => $municipality['lng'] ?? null,
                ];
            }
        }

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            Municipality::upsert($chunk, ['code'], ['region_id', 'name', 'slug', 'latitude', 'longitude']);
        }

        /** @var array<string, int> $ids */
        $ids = Municipality::query()->pluck('id', 'code')->all();

        return $ids;
    }

    /**
     * @param  list<array<string, mixed>>  $regions
     * @param  array<string, int>  $municipalityIds
     */
    private function seedSettlements(array $regions, array $municipalityIds): int
    {
        $rows = [];

        foreach ($regions as $region) {
            foreach ($region['municipalities'] ?? [] as $municipality) {
                $code = (string) $municipality['code'];
                $municipalityId = $municipalityIds[$code]
                    ?? throw new RuntimeException("GeoSeeder: unseeded municipality {$code}.");

                $settlements = $municipality['settlements'] ?? [];

                // Slug uniqueness is enforced per municipality, which is also
                // where the real same-name pairs live.
                $slugs = $this->uniqueSlugs(array_map(
                    fn (array $s): array => ['key' => (string) $s['ekatte'], 'name' => (string) $s['name']],
                    $settlements,
                ));

                foreach ($settlements as $settlement) {
                    $ekatte = (string) $settlement['ekatte'];
                    $rows[] = [
                        'municipality_id' => $municipalityId,
                        'ekatte' => $ekatte,
                        'name' => $settlement['name'],
                        'slug' => $slugs[$ekatte],
                        'type' => $settlement['type'] ?? 'village',
                        'latitude' => $settlement['lat'] ?? null,
                        'longitude' => $settlement['lng'] ?? null,
                    ];
                }
            }
        }

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            Settlement::upsert(
                $chunk,
                ['ekatte'],
                ['municipality_id', 'name', 'slug', 'type', 'latitude', 'longitude'],
            );
        }

        return count($rows);
    }

    /**
     * Slugifies a set of names, appending the authoritative code wherever two
     * names produce the same slug. Deterministic: the result depends only on
     * the input set, never on insertion order.
     *
     * @param  list<array{key: string, name: string}>  $items
     * @return array<string, string> key => slug
     */
    private function uniqueSlugs(array $items): array
    {
        $slugs = [];

        foreach ($items as $item) {
            // A name that transliterates to nothing falls back to its code.
            $slugs[$item['key']] = Str::slug($item['name']) ?: Str::lower($item['key']);
        }

        $counts = array_count_values($slugs);

        foreach ($slugs as $key => $slug) {
            if ($counts[$slug] > 1) {
                $slugs[$key] = $slug.'-'.Str::lower($key);
            }
        }

        return $slugs;
    }
}

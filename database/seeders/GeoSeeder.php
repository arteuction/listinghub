<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Municipality;
use App\Models\Region;
use App\Models\Settlement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the Bulgarian geo hierarchy from database/data/bg-geo.json.
 *
 * ListingHub is a Bulgaria-only platform. The dataset is authoritative and
 * versioned; the seeder is a safe no-op when the file is absent — no partial
 * or foreign geo data is ever shipped.
 *
 * Expected JSON shape:
 * {
 *   "country": { "name": "България", "iso2": "BG" },
 *   "regions": [
 *     {
 *       "name": "София-град", "code": "SOF",
 *       "lat": 42.698, "lng": 23.322,
 *       "municipalities": [
 *         {
 *           "name": "Столична", "code": "SOF01",
 *           "settlements": [
 *             { "name": "София", "ekatte": "68134", "type": "city",
 *               "lat": 42.698, "lng": 23.322 }
 *           ]
 *         }
 *       ]
 *     }
 *   ]
 * }
 */
class GeoSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/bg-geo.json');

        if (! is_file($path)) {
            $this->command?->warn('GeoSeeder: database/data/bg-geo.json not found — skipping.');
            $this->command?->warn('Place the official BG geo dataset there before seeding.');

            return;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            $this->command?->error('GeoSeeder: bg-geo.json is not valid JSON — aborting.');

            return;
        }

        $countryData = $data['country'] ?? ['name' => 'България', 'iso2' => 'BG'];

        $country = Country::updateOrCreate(
            ['iso2' => 'BG'],
            [
                'name' => $countryData['name'] ?? 'България',
                'slug' => 'bulgaria',
            ],
        );

        foreach ($data['regions'] ?? [] as $r) {
            $region = Region::updateOrCreate(
                ['country_id' => $country->id, 'slug' => Str::slug($r['name'])],
                [
                    'name' => $r['name'],
                    'code' => $r['code'] ?? null,
                    'latitude' => $r['lat'] ?? null,
                    'longitude' => $r['lng'] ?? null,
                    'boundary' => $r['boundary'] ?? null,
                ],
            );

            foreach ($r['municipalities'] ?? [] as $m) {
                $municipality = Municipality::updateOrCreate(
                    ['region_id' => $region->id, 'slug' => Str::slug($m['name'])],
                    [
                        'name' => $m['name'],
                        'code' => $m['code'] ?? null,
                        'latitude' => $m['lat'] ?? null,
                        'longitude' => $m['lng'] ?? null,
                        'boundary' => $m['boundary'] ?? null,
                    ],
                );

                foreach ($m['settlements'] ?? [] as $s) {
                    Settlement::updateOrCreate(
                        ['municipality_id' => $municipality->id, 'slug' => Str::slug($s['name'])],
                        [
                            'name' => $s['name'],
                            'ekatte' => $s['ekatte'] ?? null,
                            'type' => $s['type'] ?? 'city',
                            'latitude' => $s['lat'] ?? null,
                            'longitude' => $s['lng'] ?? null,
                        ],
                    );
                }
            }
        }

        $this->command?->info('GeoSeeder: Bulgaria geo hierarchy seeded successfully.');
    }
}

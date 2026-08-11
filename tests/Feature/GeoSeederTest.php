<?php

declare(strict_types=1);

use App\Models\Country;
use App\Models\Municipality;
use App\Models\Region;
use App\Models\Settlement;
use Database\Seeders\GeoSeeder;

/*
| 3.2.3 / 3.3.0 — the committed NSI EKATTE dataset must load whole.
|
| The counts below are the official ones and are also asserted by
| scripts/validate_bg_datasets.py against the JSON itself. Asserting them again
| after seeding is what proves the seeder does not drop rows: seven pairs of
| settlements share a name inside one municipality, which an earlier
| slug-keyed upsert collapsed silently.
*/

beforeEach(function () {
    $this->seed(GeoSeeder::class);
});

it('seeds the whole official hierarchy', function () {
    expect(Region::query()->count())->toBe(28)
        ->and(Municipality::query()->count())->toBe(265)
        ->and(Settlement::query()->count())->toBe(5256);
});

it('seeds Bulgaria as the only country', function () {
    expect(Country::query()->count())->toBe(1);

    $country = Country::query()->firstOrFail();

    expect($country->iso2)->toBe('BG')->and($country->slug)->toBe('bulgaria');
});

it('keeps both settlements of every same-name pair', function (string $municipalityCode, string $name) {
    $municipality = Municipality::query()->where('code', $municipalityCode)->firstOrFail();

    $matches = Settlement::query()
        ->where('municipality_id', $municipality->getKey())
        ->where('name', $name)
        ->get();

    expect($matches)->toHaveCount(2)
        ->and($matches->pluck('slug')->unique())->toHaveCount(2)
        ->and($matches->pluck('ekatte')->unique())->toHaveCount(2);
})->with([
    'Елин Пелин' => ['SFO17', 'Елин Пелин'],
    'Костенец' => ['SFO25', 'Костенец'],
    'Бов' => ['SFO43', 'Бов'],
    'Лакатник' => ['SFO43', 'Лакатник'],
    'Каспичан' => ['SHU19', 'Каспичан'],
    'Орешец' => ['VID16', 'Орешец'],
    'Вълчовци' => ['VTR13', 'Вълчовци'],
]);

it('resolves known settlements to their official municipality and region', function (
    string $ekatte,
    string $name,
    string $municipalityCode,
    string $regionCode,
) {
    $settlement = Settlement::query()->where('ekatte', $ekatte)->firstOrFail();

    expect($settlement->name)->toBe($name)
        ->and($settlement->municipality->code)->toBe($municipalityCode)
        ->and($settlement->municipality->region->code)->toBe($regionCode);
})->with([
    'София' => ['68134', 'София', 'SOF46', 'SOF'],
    'Банско' => ['02676', 'Банско', 'BLG01', 'BLG'],
    'Добринище' => ['21498', 'Добринище', 'BLG01', 'BLG'],
]);

it('gives every settlement a slug that is unique inside its municipality', function () {
    $duplicates = Settlement::query()
        ->selectRaw('municipality_id, slug, count(*) as total')
        ->groupBy('municipality_id', 'slug')
        ->havingRaw('count(*) > 1')
        ->get();

    expect($duplicates)->toBeEmpty();
});

it('places every settlement inside Bulgaria', function () {
    $outside = Settlement::query()
        ->whereNotNull('latitude')
        ->where(fn ($query) => $query
            ->whereNotBetween('latitude', [41.0, 44.5])
            ->orWhereNotBetween('longitude', [22.0, 29.0]))
        ->count();

    expect($outside)->toBe(0);
});

it('is idempotent — a second run neither duplicates nor drops rows', function () {
    $this->seed(GeoSeeder::class);

    expect(Region::query()->count())->toBe(28)
        ->and(Municipality::query()->count())->toBe(265)
        ->and(Settlement::query()->count())->toBe(5256);
});

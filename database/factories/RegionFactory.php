<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RegionFactory extends Factory
{
    /**
     * The 28 Bulgarian oblasti with their official codes. Faker has no
     * Bulgarian region provider (and `state()` is US-only), so the real list
     * is used — a BG-only platform should never generate foreign geo names.
     *
     * @var array<string, string>
     */
    private const REGIONS = [
        'Благоевград' => 'BLG',
        'Бургас' => 'BGS',
        'Варна' => 'VAR',
        'Велико Търново' => 'VTR',
        'Видин' => 'VID',
        'Враца' => 'VRC',
        'Габрово' => 'GAB',
        'Добрич' => 'DOB',
        'Кърджали' => 'KRZ',
        'Кюстендил' => 'KNL',
        'Ловеч' => 'LOV',
        'Монтана' => 'MON',
        'Пазарджик' => 'PAZ',
        'Перник' => 'PER',
        'Плевен' => 'PVN',
        'Пловдив' => 'PDV',
        'Разград' => 'RAZ',
        'Русе' => 'RSE',
        'Силистра' => 'SLS',
        'Сливен' => 'SLV',
        'Смолян' => 'SML',
        'София' => 'SFO',
        'София-град' => 'SOF',
        'Стара Загора' => 'SZR',
        'Търговище' => 'TGV',
        'Хасково' => 'HKV',
        'Шумен' => 'SHU',
        'Ямбол' => 'JAM',
    ];

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->randomElement(array_keys(self::REGIONS));
        $code = self::REGIONS[$name];

        return [
            'country_id' => Country::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'code' => $code,
            'latitude' => fake()->latitude(41.24, 44.22),
            'longitude' => fake()->longitude(22.36, 28.61),
            'boundary' => null,
        ];
    }
}

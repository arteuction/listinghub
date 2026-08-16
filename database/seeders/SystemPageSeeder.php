<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PageStatus;
use App\Models\Menu;
use App\Models\Page;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the immutable system pages and default navigation menus.
 * Safe to run multiple times — uses upsert on slug / handle.
 */
class SystemPageSeeder extends Seeder
{
    /** @var list<array{slug: string, title: string, is_homepage: bool}> */
    private const SYSTEM_PAGES = [
        ['slug' => 'home',           'title' => 'Начална страница',  'is_homepage' => true],
        ['slug' => 'about',          'title' => 'За нас',            'is_homepage' => false],
        ['slug' => 'contact',        'title' => 'Контакти',          'is_homepage' => false],
        ['slug' => 'terms',          'title' => 'Условия за ползване', 'is_homepage' => false],
        ['slug' => 'privacy',        'title' => 'Поверителност',     'is_homepage' => false],
        ['slug' => 'refund-policy',  'title' => 'Политика за връщане', 'is_homepage' => false],
    ];

    /** @var list<array{handle: string, name: string}> */
    private const MENUS = [
        ['handle' => 'header',       'name' => 'Главно меню (хедър)'],
        ['handle' => 'footer',       'name' => 'Футър — основни'],
        ['handle' => 'footer-legal', 'name' => 'Футър — правни'],
    ];

    public function run(): void
    {
        foreach (self::SYSTEM_PAGES as $i => $data) {
            Page::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'uuid'        => Str::uuid()->toString(),
                    'title'       => $data['title'],
                    'is_system'   => true,
                    'is_homepage' => $data['is_homepage'],
                    'status'      => PageStatus::Draft,
                    'sort_order'  => ($i + 1) * 1000,
                ]
            );
        }

        foreach (self::MENUS as $data) {
            Menu::query()->firstOrCreate(
                ['handle' => $data['handle']],
                ['name'   => $data['name']],
            );
        }
    }
}

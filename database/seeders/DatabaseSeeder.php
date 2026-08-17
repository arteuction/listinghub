<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Baseline data required for a fresh install.
     *
     * Note: NO admin user is seeded here. The admin account is created
     * interactively by the installer with an operator-chosen password
     * (docs/ARCHITECTURE.md §6, §11).
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PlanSeeder::class,
            // A Bulgaria-only directory without the Bulgarian geography is not
            // installed, it is broken: no region/settlement filters, an empty
            // map, a dead EKATTE autocomplete. Found by a clean-install run —
            // the installer produced a working /admin over a geo-empty site.
            // GeoSeeder upserts on official codes, so re-running is safe, and
            // it no-ops (with a warning) if the data file is absent.
            GeoSeeder::class,
            SystemPageSeeder::class,
            TaxonomySeeder::class,
        ]);
    }
}

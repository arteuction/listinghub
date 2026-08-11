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
        ]);
    }
}

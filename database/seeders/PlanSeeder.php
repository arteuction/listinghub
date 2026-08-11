<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // Prices in integer minor units (USD cents).
        $plans = [
            [
                'name' => 'Free', 'slug' => 'free', 'price_minor' => 0, 'interval' => 'lifetime',
                'listing_limit' => 1, 'gallery_limit' => 3, 'is_featured' => false, 'sort_order' => 1,
            ],
            [
                'name' => 'Pro', 'slug' => 'pro', 'price_minor' => 1900, 'interval' => 'month',
                'listing_limit' => 10, 'gallery_limit' => 20, 'is_featured' => true, 'sort_order' => 2,
            ],
            [
                'name' => 'Business', 'slug' => 'business', 'price_minor' => 4900, 'interval' => 'month',
                'listing_limit' => null, 'gallery_limit' => 100, 'is_featured' => true, 'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan + ['currency' => 'USD', 'is_active' => true]);
        }
    }
}

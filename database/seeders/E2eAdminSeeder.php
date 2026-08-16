<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class E2eAdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'e2e-admin@example.com'],
            [
                'name' => 'E2E Admin',
                'password' => Hash::make('E2eAdm1n-Secure-2026!'),
                'email_verified_at' => now(),
                'status' => 'active',
            ],
        );
        $admin->assignRole('admin');

        $member = User::query()->firstOrCreate(
            ['email' => 'e2e-member@example.com'],
            [
                'name' => 'E2E Member',
                'password' => Hash::make('E2eMember-Secure-2026!'),
                'email_verified_at' => now(),
                'status' => 'active',
            ],
        );
        $member->assignRole('member');

        // Ensure at least one page with blocks exists for reorder tests
        $page = Page::query()->firstOrCreate(
            ['slug' => 'e2e-test-page'],
            [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'title' => 'E2E Test Page',
                'status' => 'draft',
                'sort_order' => 9000,
            ],
        );

        // Create two content blocks if none exist
        if ($page->contentBlocks()->count() === 0) {
            foreach (['Block Alpha', 'Block Beta'] as $i => $label) {
                \App\Models\ContentBlock::query()->create([
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'owner_type' => $page->getMorphClass(),
                    'owner_id' => $page->id,
                    'block_type' => 'rich_text',
                    'status' => 'draft',
                    'version' => 1,
                    'sort_order' => ($i + 1) * 1000,
                    'content' => ['tiptap' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $label]]]]]],
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ]);
            }
        }
    }
}

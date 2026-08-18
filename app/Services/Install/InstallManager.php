<?php

declare(strict_types=1);

namespace App\Services\Install;

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\InstallationState;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Owns installation state and the finalize sequence.
 *
 * Markers (all under storage/app, none contain secrets):
 *  - installed.lock       authoritative "installed" marker (InstallationState)
 *  - installation.pending recovery marker written before migrations
 *  - installation.running exclusive mutex so only one install POST runs
 */
class InstallManager
{
    public function lockPath(): string
    {
        return storage_path('app/installed.lock');
    }

    public function pendingPath(): string
    {
        return storage_path('app/installation.pending');
    }

    public function runningPath(): string
    {
        return storage_path('app/installation.running');
    }

    public function isInstalled(): bool
    {
        return InstallationState::isInstalled();
    }

    /** Fingerprint of the DB target — no credentials, just where it points. */
    public function targetHash(): string
    {
        $c = config('database.connections.'.config('database.default'));

        return hash('sha256', ($c['host'] ?? '').'|'.($c['port'] ?? '').'|'.($c['database'] ?? ''));
    }

    /**
     * Acquire the single-runner mutex atomically. fopen('x') fails if the file
     * already exists, so two concurrent POSTs cannot both win.
     */
    public function acquireRunLock(): bool
    {
        $h = @fopen($this->runningPath(), 'x');
        if ($h === false) {
            return false;
        }
        fwrite($h, (string) time());
        fclose($h);

        return true;
    }

    public function releaseRunLock(): void
    {
        @unlink($this->runningPath());
    }

    public function writePendingMarker(int $step): void
    {
        $data = [
            'timestamp' => date('c'),
            'target_hash' => $this->targetHash(),
            'step' => $step,
            'version' => (string) config('listinghub.version'),
        ];
        file_put_contents($this->pendingPath(), json_encode($data, JSON_PRETTY_PRINT));
    }

    /** A retry may proceed only if the pending marker targets the same DB. */
    public function pendingMatchesTarget(): bool
    {
        if (! is_file($this->pendingPath())) {
            return true; // fresh install, nothing pending
        }
        $data = json_decode((string) file_get_contents($this->pendingPath()), true) ?: [];

        return ($data['target_hash'] ?? null) === $this->targetHash();
    }

    public function removePendingMarker(): void
    {
        @unlink($this->pendingPath());
    }

    /**
     * Finalize: migrate -> seed -> admin (hashed) -> role -> runtime checks ->
     * atomic installed.lock -> remove pending. Never runs migrate:fresh.
     *
     * @param  array{name:string,email:string,password_hash:string}  $admin
     */
    public function finalize(array $admin): void
    {
        if (! $this->pendingMatchesTarget()) {
            throw new RuntimeException('A pending installation targets a different database. Aborting.');
        }

        $this->writePendingMarker(6);

        // Shared hosting commonly caps max_execution_time at 30s; migrations
        // plus the EKATTE settlement seed exceed that and the install dies
        // halfway (verified against a clean MariaDB 11.4). Lift the limit for
        // THIS request only — the installer runs once and locks itself out.
        @set_time_limit(0);

        // migrate (--force, non-interactive, NEVER fresh), then seed roles/plans.
        // The seeder is run directly rather than via `migrate --seed`, because
        // the `db:seed` command is not registered in a web/HTTP request context.
        Artisan::call('migrate', ['--force' => true]);
        app(DatabaseSeeder::class)->setContainer(app())->run();

        // Create public/storage → storage/app/public symlink so uploaded
        // images are web-accessible without shell access.
        Artisan::call('storage:link', ['--force' => true]);

        $verified = false;

        DB::transaction(function () use ($admin, &$verified): void {
            // Retry-safe: a resumed install must not fail on a duplicate email.
            $user = User::query()->firstOrCreate(
                ['email' => $admin['email']],
                [
                    'name' => $admin['name'],
                    'password' => $admin['password_hash'], // already hashed
                    'status' => 'active',
                    'organization_id' => null, // shared-catalog model
                ],
            );

            // email_verified_at is guarded (not fillable) — set it explicitly.
            if ($user->email_verified_at === null) {
                $user->markEmailAsVerified();
            }

            $user->assignRole(Role::findByName('admin', 'web'));

            // Verify on the just-written row INSIDE the transaction (a
            // transaction always sees its own writes). Avoids the MySQL
            // REPEATABLE-READ snapshot and spatie guard/cache pitfalls that a
            // post-commit global query is subject to.
            // status may be cast to the UserStatus enum — compare on its value.
            $statusValue = $user->status instanceof UserStatus
                ? $user->status->value
                : (string) $user->status;

            $verified = $statusValue === 'active'
                && $user->email_verified_at !== null
                && $user->roles()->where('name', 'admin')->exists();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (! $verified) {
            throw new RuntimeException('Post-install check failed: admin/role missing.');
        }

        $this->createLockAtomically();
        $this->removePendingMarker();
    }

    /** Write installed.lock via temp file + atomic rename. */
    protected function createLockAtomically(): void
    {
        $tmp = $this->lockPath().'.'.bin2hex(random_bytes(6)).'.tmp';
        file_put_contents($tmp, json_encode([
            'installed_at' => date('c'),
            'version' => (string) config('listinghub.version'),
        ]));
        if (! @rename($tmp, $this->lockPath())) {
            @unlink($tmp);
            throw new RuntimeException('Could not create installed.lock.');
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Install\DatabaseTester;
use App\Services\Install\EnvironmentWriter;
use App\Services\Install\InstallManager;
use App\Services\Install\RequirementsChecker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Throwable;

class InstallCommand extends Command
{
    protected $signature = 'listinghub:install';

    protected $description = 'Interactive CLI installer — same logic as the web wizard';

    public function handle(
        RequirementsChecker $checker,
        DatabaseTester $tester,
        EnvironmentWriter $writer,
        InstallManager $manager,
    ): int {
        $this->components->info('ListingHub Installer v'.config('listinghub.version'));
        $this->newLine();

        if ($manager->isInstalled()) {
            $this->components->error('Already installed (storage/app/installed.lock exists).');

            return self::FAILURE;
        }

        // 1. Preflight
        $this->components->task('Checking requirements', function () use ($checker) {
            $r = $checker->results();
            if (! $r['php']['passed']) {
                $this->components->error("PHP {$r['php']['required']}+ required, found {$r['php']['current']}");

                return false;
            }
            $missing = array_keys(array_filter($r['extensions'], fn ($v) => ! $v));
            if ($missing) {
                $this->components->error('Missing extensions: '.implode(', ', $missing));

                return false;
            }
            $unwritable = array_keys(array_filter($r['writable'], fn ($v) => ! $v));
            if ($unwritable) {
                $this->components->error('Not writable: '.implode(', ', $unwritable));

                return false;
            }

            return true;
        });

        if (! $checker->satisfied()) {
            return self::FAILURE;
        }

        // 2. Environment
        $appName = $this->ask('Application name', 'ListingHub');
        $appUrl = $this->ask('Application URL (no trailing slash)', 'http://localhost');
        $dbHost = $this->ask('Database host', '127.0.0.1');
        $dbPort = $this->ask('Database port', '3306');
        $dbName = $this->ask('Database name', 'listinghub');
        $dbUser = $this->ask('Database username');
        $dbPass = $this->secret('Database password') ?? '';

        // 3. Test connection
        $result = $tester->test([
            'connection' => 'mysql',
            'host' => $dbHost,
            'port' => $dbPort,
            'database' => $dbName,
            'username' => (string) $dbUser,
            'password' => (string) $dbPass,
        ]);

        if (! $result['ok']) {
            $this->components->error($result['reason']);

            return self::FAILURE;
        }

        $this->components->info('Database connection OK.');

        // 4. Write .env
        $writer->write([
            'APP_NAME' => $appName,
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => $appUrl,
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $dbHost,
            'DB_PORT' => $dbPort,
            'DB_DATABASE' => $dbName,
            'DB_USERNAME' => (string) $dbUser,
            'DB_PASSWORD' => (string) $dbPass,
        ]);

        $this->components->info('.env written with generated APP_KEY.');

        // 5. Admin account
        $adminName = $this->ask('Admin name');
        $adminEmail = $this->ask('Admin email');
        $adminPassword = $this->secret('Admin password (min 8 chars)');

        if (strlen((string) $adminPassword) < 8) {
            $this->components->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        // 6. Confirm
        $this->newLine();
        $this->components->twoColumnDetail('App URL', $appUrl);
        $this->components->twoColumnDetail('Database', "{$dbHost}:{$dbPort}/{$dbName}");
        $this->components->twoColumnDetail('Admin', $adminEmail);
        $this->newLine();

        if (! $this->confirm('Proceed with installation?', true)) {
            return self::SUCCESS;
        }

        // 7. Finalize (same InstallManager as web wizard)
        if (! $manager->acquireRunLock()) {
            $this->components->error('Another installation is in progress.');

            return self::FAILURE;
        }

        try {
            $manager->finalize([
                'name' => $adminName,
                'email' => $adminEmail,
                'password_hash' => Hash::make((string) $adminPassword),
            ]);
        } catch (Throwable $e) {
            $this->components->error('Installation failed: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            $manager->releaseRunLock();
        }

        $this->newLine();
        $this->components->info('ListingHub installed successfully!');
        $this->components->twoColumnDetail('URL', $appUrl);
        $this->components->twoColumnDetail('Admin panel', $appUrl.'/admin');
        $this->newLine();
        $this->components->warn('Next steps:');
        $this->line('  1. Set document root to public/');
        $this->line('  2. Configure cron: * * * * * php artisan schedule:run');
        $this->line('  3. Start queue worker: php artisan queue:work --sleep=3 --tries=3');
        $this->line('  4. Enable HTTPS and set APP_URL accordingly');

        return self::SUCCESS;
    }
}

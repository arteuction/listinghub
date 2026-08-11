<?php

declare(strict_types=1);

use App\Services\Install\DatabaseTester;
use App\Services\Install\EnvironmentWriter;
use App\Services\Install\InstallManager;
use App\Services\Install\RequirementsChecker;

// Never touch installed.lock here — it would flip global install state for
// every other test. Only the running/pending markers are exercised + cleaned.
afterEach(function () {
    @unlink(storage_path('app/installation.running'));
    @unlink(storage_path('app/installation.pending'));
});

it('reports requirements satisfied on the test runtime', function () {
    expect(app(RequirementsChecker::class)->satisfied())->toBeTrue();
});

it('refuses an invalid database connection with a credential-free reason', function () {
    $result = app(DatabaseTester::class)->test([
        'connection' => 'mysql',
        'host' => '127.0.0.1',
        'port' => '1',            // connection refused, fails fast
        'database' => 'nope',
        'username' => 'root',
        'password' => 'super-secret-password',
    ]);

    expect($result['ok'])->toBeFalse()
        ->and($result['reason'])->not->toContain('super-secret-password');
});

it('generates an APP_KEY in PHP with the base64 prefix', function () {
    $key = app(EnvironmentWriter::class)->generateKey();

    expect($key)->toStartWith('base64:')
        ->and(strlen(base64_decode(substr($key, 7), true)))->toBe(32);
});

it('lets only one installer POST hold the run lock', function () {
    $m = app(InstallManager::class);

    expect($m->acquireRunLock())->toBeTrue()   // first wins
        ->and($m->acquireRunLock())->toBeFalse(); // second is blocked

    $m->releaseRunLock();
    expect($m->acquireRunLock())->toBeTrue();   // released -> can re-acquire
    $m->releaseRunLock();
});

it('only resumes a pending install that targets the same database', function () {
    $m = app(InstallManager::class);

    expect($m->pendingMatchesTarget())->toBeTrue(); // no marker yet

    $m->writePendingMarker(6);
    expect($m->pendingMatchesTarget())->toBeTrue(); // same target

    // Simulate a marker written against a different database.
    file_put_contents($m->pendingPath(), json_encode(['target_hash' => 'different']));
    expect($m->pendingMatchesTarget())->toBeFalse();

    $m->removePendingMarker();
});

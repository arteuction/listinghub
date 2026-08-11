<?php

declare(strict_types=1);

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Http\Requests\Install\AdminRequest;
use App\Http\Requests\Install\EnvironmentRequest;
use App\Services\Install\DatabaseTester;
use App\Services\Install\EnvironmentWriter;
use App\Services\Install\InstallManager;
use App\Services\Install\RequirementsChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

/**
 * Production web installer. Backend-first: every step validates and persists
 * safely. The wizard is reachable only while storage/app/installed.lock is
 * absent (EnsureInstalled); after success /install/* returns 404.
 */
class InstallController extends Controller
{
    // 1. Welcome
    public function welcome()
    {
        return view('install.welcome');
    }

    // 2. Requirements + permissions
    public function requirements(RequirementsChecker $checker)
    {
        $r = $checker->results();

        return view('install.requirements', $r + ['satisfied' => $checker->satisfied()]);
    }

    // 3a. Environment form
    public function environment()
    {
        return view('install.environment', ['old' => session('install.env', [])]);
    }

    // 3b. Environment submit — real DB test; .env only written on success.
    public function storeEnvironment(EnvironmentRequest $request, DatabaseTester $tester, EnvironmentWriter $writer): RedirectResponse
    {
        $data = $request->validated();

        $result = $tester->test([
            'connection' => $data['db_connection'],
            'host' => (string) ($data['db_host'] ?? '127.0.0.1'),
            'port' => (string) ($data['db_port'] ?? '3306'),
            'database' => $data['db_database'],
            'username' => (string) ($data['db_username'] ?? ''),
            'password' => (string) ($data['db_password'] ?? ''),
        ]);

        if (! $result['ok']) {
            // Invalid connection / non-empty DB: NO .env change.
            return back()
                ->withInput($request->except('db_password'))
                ->withErrors(['db' => $result['reason']]);
        }

        // Persist allowlisted keys atomically (APP_KEY generated in PHP).
        $writer->write([
            'APP_NAME' => $data['app_name'],
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => $data['app_url'],
            'DB_CONNECTION' => $data['db_connection'],
            'DB_HOST' => (string) ($data['db_host'] ?? '127.0.0.1'),
            'DB_PORT' => (string) ($data['db_port'] ?? '3306'),
            'DB_DATABASE' => $data['db_database'],
            'DB_USERNAME' => (string) ($data['db_username'] ?? ''),
            'DB_PASSWORD' => (string) ($data['db_password'] ?? ''),
        ]);

        // Store only non-secret summary for the review screen.
        session()->put('install.env', [
            'app_name' => $data['app_name'],
            'app_url' => $data['app_url'],
            'db_connection' => $data['db_connection'],
            'db_host' => $data['db_host'] ?? null,
            'db_database' => $data['db_database'],
        ]);

        return redirect()->route('install.admin');
    }

    // 4a. Admin form
    public function admin()
    {
        if (! session()->has('install.env')) {
            return redirect()->route('install.environment');
        }

        return view('install.admin');
    }

    // 4b. Admin submit — hash immediately; never keep plaintext.
    public function storeAdmin(AdminRequest $request): RedirectResponse
    {
        $data = $request->validated();

        session()->put('install.admin', [
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => Hash::make($data['password']),
        ]);

        return redirect()->route('install.review');
    }

    // 5. Review / Install (no secrets shown)
    public function review()
    {
        if (! session()->has('install.env') || ! session()->has('install.admin')) {
            return redirect()->route('install.environment');
        }

        return view('install.review', [
            'env' => session('install.env'),
            'admin' => ['name' => session('install.admin.name'), 'email' => session('install.admin.email')],
        ]);
    }

    // 6-8. Finalize: migrate -> seed -> admin -> role -> checks -> atomic lock.
    public function install(InstallManager $manager): SymfonyResponse
    {
        if (! session()->has('install.admin')) {
            return redirect()->route('install.environment');
        }

        // Concurrency: only one POST may finalize.
        if (! $manager->acquireRunLock()) {
            return back()->withErrors(['install' => 'An installation is already in progress.']);
        }

        try {
            $manager->finalize(session('install.admin'));
        } catch (Throwable $e) {
            return back()->withErrors(['install' => $e->getMessage()]);
        } finally {
            $manager->releaseRunLock();
        }

        session()->forget(['install.env', 'install.admin']);

        // Render the finished screen inline in THIS response. The lock now
        // exists, so any later GET /install/* returns 404 (EnsureInstalled).
        return response(view('install.finished'));
    }
}

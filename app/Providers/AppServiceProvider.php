<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\InstallationState;
use App\Support\SqliteUnicode;
use App\Support\Tiptap\TiptapHtmlRenderer;
use App\Support\Tiptap\TiptapSanitizer;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TiptapSanitizer::class, static fn () => new TiptapSanitizer(new TiptapHtmlRenderer));
    }

    public function boot(): void
    {
        // P0: before installation the `sessions`/`cache` tables do not exist yet,
        // so a production SESSION_DRIVER=database would break the installer's own
        // POST steps. Force file/array drivers for the whole pre-install flow,
        // regardless of .env. Once storage/app/installed.lock exists the
        // configured drivers take over (and the tables are present by then).
        if (! InstallationState::isInstalled()) {
            Config::set('session.driver', 'file');
            Config::set('cache.default', 'array');
            Config::set('queue.default', 'sync');
            // The wizard writes APP_NAME to .env at the environment step, which
            // would otherwise change the session cookie name (slug(APP_NAME)_session)
            // on the next request and silently drop the installer session (=> 419).
            // Pin a stable cookie name for the whole pre-install flow.
            Config::set('session.cookie', 'listinghub_installer_session');
        }

        // Password policy for every rule that uses Password::defaults().
        // The breach check is a network call to the HaveIBeenPwned range API,
        // so it is enabled only outside testing — otherwise the suite would be
        // slow and would depend on an external service being reachable.
        Password::defaults(fn () => Password::min(10)
            ->letters()
            ->numbers()
            ->when(! app()->runningUnitTests(), fn (Password $rule) => $rule->uncompromised()));

        // Give SQLite a Unicode-aware LOWER() so free-text search behaves the
        // same on the dev/test driver as it does on production MySQL. Bound to
        // the event rather than the current connection because it must also
        // run for lazily-opened connections and after a reconnect.
        Event::listen(
            ConnectionEstablished::class,
            static fn (ConnectionEstablished $event) => SqliteUnicode::register($event->connection),
        );

        // A connection opened before this provider booted never saw that event.
        // getConnections() returns only the already-resolved ones, so this does
        // not open anything by asking.
        foreach (DB::getConnections() as $connection) {
            SqliteUnicode::register($connection);
        }

        // Policies are auto-discovered by Laravel (App\Models\Foo ->
        // App\Policies\FooPolicy).

        // @tiptap($doc) — renders a Tiptap JSON array to sanitized HTML.
        // Usage: {!! @tiptap($block->content['tiptap'] ?? []) !!}
        Blade::directive('tiptap', static function (string $expression): string {
            return "<?php echo app(\\App\\Support\\Tiptap\\TiptapSanitizer::class)->toHtml({$expression} ?? []); ?>";
        });
    }
}

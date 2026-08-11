# Runtime verification gate

The package is generated on a host without PHP/Composer, so it is only
**static-verified**. Before a version becomes canonical (and before the next
domain iteration), run `./verify.sh` on a **PHP 8.3+** host.

`verify.sh` is self-contained and safe: it uses a **throwaway SQLite database**
and `APP_ENV=testing`, exports an ephemeral `APP_KEY`, and **never modifies your
`.env` or any real database**. It uses `set -Eeuo pipefail`, checks PHP ≥ 8.3,
and cleans up the temp DB via `trap` on exit.

| Step | Command | Proves |
|------|---------|--------|
| 0 | PHP ≥ 8.3 guard | the interpreter meets the floor |
| 1 | `composer validate --strict` | `composer.json` is well-formed (incl. the `test` script) |
| 2 | `composer install` | the Laravel 13 / Tinker 3 / Spatie 8 / Pest 4 constraints **actually resolve** — the one thing static review cannot confirm |
| 3 | `migrate:fresh --seed` (throwaway SQLite) | migrations run in order; FK/idempotency/unique constraints hold; seeders work |
| 4 | `composer test` (`php artisan test`) | all Pest tests pass, incl. FK-rejection and the 2.7 transition/ordering suites |
| 5 | `php artisan about` | the app boots; providers, config and packages are discovered |

## MySQL portability (separate manual step)

`verify.sh` proves the schema on SQLite. To confirm production portability, also run
once against a **MySQL 8 / MariaDB scratch database** (never real data):

```bash
DB_CONNECTION=mysql DB_DATABASE=listinghub_scratch \
  php artisan migrate:fresh --seed
```

This exercises the inline `organization_id` FKs, the deferred user FKs, and the
`char(3)` / `json` / `uuid` columns on a real MySQL grammar.

## If a step fails

- **Step 2** — a version constraint doesn't resolve. Read the conflict; adjust the pin in
  `composer.json`. This is the most likely first failure and the reason a package is not yet canonical.
- **Step 3/MySQL** — a migration or cast error. Fix the migration/model, re-run.
- **Step 4** — a behavioural regression. Fix code or test.

Only when **every** step is green on SQLite **and** the MySQL step passes should the
version be tagged canonical and the next iteration begin.

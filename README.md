# Weekly To-Do Planner

A small, server-rendered Laravel assessment: plan tasks across Monday–Sunday, estimate hours, edit or delete tasks, and mark work complete or incomplete. Blade + Bootstrap 5, SQLite, and PHPUnit; no login, SPA, build pipeline, or external service is required.

## Prerequisites

- PHP 8.2+ with Laravel's standard extensions, including `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `fileinfo`, XML/DOM, and ctype.
- Composer 2.
- A browser with JavaScript enabled for Bootstrap modals, checkbox submission, and delete confirmation.

Verified locally with PHP 8.3.27, Composer 2.9.2, Laravel 12.69.3, and Chrome. The committed `composer.lock` fixes PHP dependency versions. Bootstrap 5.3.8 CSS/JS and its MIT license are included in `public/vendor/bootstrap`; there is no npm installation step or runtime CDN requirement.

## Install and run

From the project root:

```sh
composer install
```

Copy the example environment **only if you do not already have `.env`**:

```powershell
# Windows PowerShell
Copy-Item .env.example .env
```

```sh
# macOS / Linux
cp .env.example .env
```

Then run:

```sh
php artisan key:generate
php -r "file_exists('database/planner.sqlite') || touch('database/planner.sqlite');"
php artisan config:clear
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Open **http://127.0.0.1:8000**. Stop the server with Ctrl+C. `composer dev` is also a shortcut for `php artisan serve`.

The example environment uses:

```dotenv
DB_CONNECTION=sqlite
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

Leave `DB_DATABASE` unset to use the portable default `database/planner.sqlite`, or set it to an absolute SQLite file path. Existing `.env` files must use the settings above. `storage/` and `bootstrap/cache/` must be writable. The original local starter `database/database.sqlite` was preserved; this planner uses a separate file to avoid deleting existing data. A new installation creates only the `tasks` application table, plus Laravel/SQLite migration metadata.

Tasks survive refreshes and server restarts because they are stored in the SQLite file. Do not remove that file or run `migrate:fresh` if you want to keep your tasks. No seed data is required.

## Behavior and implementation

- `app/Support/PlanningWindow.php` calculates today in `Asia/Ho_Chi_Minh`, the inclusive end date using `addMonthNoOverflow()`, and permitted Monday week starts. The window is recalculated on each request.
- `app/Http/Requests/TaskRequest.php` validates the seven editable fields. Scheduling is allowed from today through one calendar month later. Deadlines can extend beyond that window. An existing task can retain its original date when other fields change, even if that date has passed.
- `app/Http/Controllers/TaskController.php` implements the board and conventional POST/PUT/DELETE/PATCH web actions. An invalid week URL redirects to the current week with feedback. Adding or moving a task opens its destination week; other operations preserve a valid selected week.
- `app/Models/Task.php` casts values, stores scheduled/deadline dates as `YYYY-MM-DD` for SQLite, and calculates deadline status. Completion overrides overdue/due-today styling.
- `resources/views/tasks/` renders seven columns and Bootstrap modal forms. Small assets in `public/css/` and `public/js/` handle horizontal layout and interactions. User text is escaped; forms use Laravel CSRF protection.
- Incomplete tasks appear first, ordered high → medium → low, then oldest first. Completed tasks follow in creation order. IDs provide a stable tie-breaker. Planned hours include **all** scheduled tasks, including completed tasks.
- Old tasks remain in SQLite even when they fall outside navigable weeks. No historical dashboard or month filter is provided.

## Tests

```sh
php artisan test
```

Or invoke PHPUnit directly:

```sh
php vendor/phpunit/phpunit/phpunit
```

`phpunit.xml` forces SQLite `:memory:`; feature tests use `RefreshDatabase` and frozen dates. They do not touch the local planner database. Coverage includes CRUD, reversible completion, validation, ordering/totals, Sunday, week boundaries, old-date edits, leap years/month ends, local midnight, escaping, and form-error recovery.

Latest verification: **33 tests passed, 154 assertions, 0 failures**. Blade compilation and formatting of the changed PHP files were also checked. See [verification notes](assessment/verification.md) for the browser checks and observed corrections.

## Screenshots and assessment documents

Real Chrome screenshots from the running app are in [Assessment screenshots](assessment/screenshot/). The sample tasks were created through the UI for verification and removed afterward; they are not mandatory seed data. Screenshot dates reflect the capture date, not hardcoded application behavior.

- [Initial prompt](assessment/prompt.md): preserved byte-for-byte during implementation.
- [Rules](assessment/rules.md): authoritative scope and behavior.
- [AI Usage Reflection](assessment/reflection.md): Candidate's reflection addressing all four required questions, within the 500-word limit.

The app is a local assessment, without authentication. Production deployment and the excluded features listed in the rules are outside this implementation.

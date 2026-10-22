<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Test every code change by adding or updating a test.
- Run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>

# Pinnacle Project Rules

These rules are decided by the project owner and override any generic guidance above. `PLAN.md` is the source of truth for modules and database fields; read it before adding or changing any table, and update it in the same change when the schema changes.

## 1. API-first
- Every feature is built as an API first: route in `routes/api.php` or a `routes/erp-*.php` file (behind `auth:sanctum` + `permission:` middleware), then Controller → Service → Model.
- Blade/admin screens reuse the same Service classes; business logic never lives in Blade views or web-only controllers.
- One API serves every client: the web admin panel, the web portals, and the Android/iOS mobile apps. Build each feature once; never build a separate mobile-only or web-only version of the same logic.
- Mobile apps authenticate with Sanctum personal access tokens (one per device, tracked in `user_devices`); web uses the Sanctum session. Output is shaped with Eloquent API Resources so all clients get identical fields.

## 2. JSON is only for API responses
- JSON is used only to send API responses. Data is never stored as JSON.
- No `json`/`jsonb` columns, no `array`/`json`/`collection` casts, no serialized arrays in text columns, no JSON blobs in Blade `data-*` attributes, no JSON files as a data store.
- Lists and repeating values go into proper columns, child tables, or pivot tables.

## 3. Every API endpoint always returns the standard response
- Always return through the `ApiResponse` trait / `App\Support\ApiResponseHelper`, with the envelope `{ success, message, data, errors }`, on success and on failure.
- Never return a raw model, array, string, or empty response from an API route. Validation, auth, 403, 404, and server errors must also come back in this envelope.

## 4. Simplest code, fewest functions
- Write the simplest code that works, in the fewest functions. No extra layers (repositories, interfaces, DTOs, helpers) unless the same logic is reused in at least two places.
- Prefer built-in Laravel features (Form Requests, Eloquent relations, scopes, policies/middleware) over custom code.

## 5. Optimized by default
- Eager load relations, select only the columns needed, and never cause N+1 queries.
- Add indexes on every foreign key and on every column used for filtering, searching, or sorting.
- Every list is paginated or server-side. Never load a full table into memory.

## 6. Data tables use Yajra DataTables (server-side)
- Every listing grid (admin and portals) uses Yajra DataTables in server-side mode, so the screen stays fast as data grows.
- Package: `yajra/laravel-datatables-oracle`. It is not installed yet; install it (with approval) when the first module grid is built.
- Grid endpoints still follow rule 3: the Yajra payload goes inside the envelope's `data`, and the grid reads it with `dataSrc: 'data.data'`. Mobile uses the same Service query with normal pagination (`data.items` + `data.meta`).

## 7. Business options are dynamic, never hard-coded
- Categories, types, payment methods, fee heads, leave types, document types, designations, relations, and any option a school may want to add or rename live in database tables managed from the admin panel.
- Do not use PHP enums, config arrays, or hard-coded `<option>` lists for them. Use the shared `lookups` table for simple lists, or a dedicated master table when the option has its own fields (see `PLAN.md`).
- Only internal workflow states that code branches on (e.g. invoice `draft`/`posted`/`paid`) may be fixed string constants.

## 8. Files and images
- Images and documents are stored with Laravel `Storage` on a disk, never as base64 or blobs in the database.
- Every uploaded file gets a row in the polymorphic `attachments` table. A single profile photo may be kept as a `photo_path` column.

## 9. Data safety
- Money is stored as integer minor units (paisa) in `bigInteger` columns, never as float or decimal.
- Nothing is ever permanently deleted. Every table whose rows can be deleted has `deleted_at` and its model uses `SoftDeletes`; every delete action (including child rows a Service replaces or cleans up, and pivot links) is a soft delete. Never use `forceDelete()`, query-builder `->delete()` on business tables, or `detach()` that erases rows. Only security/session data (Sanctum tokens, sessions, cache) may be hard deleted.
- Financial records (posted invoices, payments, journal entries) are never edited or deleted after posting; corrections are made by reversal entries.
- Most fields beyond the identifying ones are nullable (optional), so records can be created quickly and completed later.

## 10. No database foreign-key constraints
- Never add foreign-key constraints: no `->constrained()`, `->foreign()`, `->references()`, or `cascadeOnDelete()` in migrations. Reference columns are plain `unsignedBigInteger` (or `foreignId()` without `constrained()`) and always get an index.
- This keeps data easy to export, import, and move between databases and campuses.
- Integrity is enforced in code: `exists:` validation in Form Requests, soft deletes, and Services that block deleting a record still in use and clean up child rows themselves.
- Tables created before this rule (`role_user`, `permission_role`, `regions`, `currencies`, `system_settings`) still have constraints; do not copy that pattern.

## 11. Everything is logged in the activity log
- Every create, update, delete, restore, status change, approve/reject, post/reverse, assign/return, print, export/import, login/logout, and failed login is written to `activity_logs` (one row: user, module, action, record, readable description, platform, IP, time), with changed fields in `activity_log_changes` (old/new value per field, no JSON).
- Logging happens automatically in the same transaction: a shared model trait/observer for CRUD events, plus one call in Services for business actions. Never skip it for a new model or action.
- Secrets (passwords, tokens) are never logged; activity logs are append-only.

## 12. No static data — everything comes from the database
- Never hard-code business data in controllers, services, views, or JavaScript (no arrays of news, events, quotes, options, stats, etc.). All data is read from the database through Services.
- Master data the system needs (lookups, permissions, roles, reference lists) is created by seeders. Dummy / demo data for development is also created only by seeders (using factories), never typed into code or views.
- Every new module ships with: migration(s), model + factory, a seeder for its master data, and a seeder for demo data when demo records are useful.
- Follow the code structure described in these rules for every module: routes in `routes/erp-*.php` → Form Request → Controller (thin) → Service → Model, API Resource for output, standard envelope response, Yajra grid for lists, activity log on every action.

# FAST Website

Secure, database-driven website for the Faculty of Applied Sciences and
Technology (FAST), Mbarara University of Science and Technology (MUST).

## Features

- Permission-aware administration with department scoping
- Departments, staff, programmes, curricula, and research management
- Innovations, facilities, partnerships, news, events, and editable pages
- Reusable media, public documents, and a database-driven homepage
- CSRF protection, secure authentication, audit history, and publishing workflows

Public wording, institutional identity, navigation labels, service URLs, and
landing-page introductions are managed through **Administration → Site content**.

## Requirements

- PHP 8.1 or newer
- MySQL 8.0+ or MariaDB 10.5+
- PDO and PDO MySQL PHP extensions
- Apache with `mod_rewrite` when using the included `.htaccess` files

## Local setup

1. Clone the repository and enter its directory.
2. Copy `.env.example` to `.env` and enter local database credentials. Keep
   `DB_DATABASE=fast_website_db` — `database/schema.sql` creates and selects
   a database with that exact name itself, regardless of what name you use
   when invoking `mysql`, so every step below assumes that name.
3. Configure the web server document root to the `public/` directory, with
   Apache `mod_rewrite` enabled so the included `.htaccess` files apply.
   PHP's built-in `php -S` server does not process `.htaccess` and will not
   produce working clean URLs — use Apache (or another rewrite-aware server)
   for anything beyond a quick script check.
4. Run the setup script:

   ```powershell
   php bin/setup.php
   ```

   This creates the database schema, seeds roles/permissions/the
   faculty/departments, walks you through creating the first administrator
   (it'll ask for a name, email, and a temporary password), seeds the real
   site content in the required order, and finishes with a health check —
   the one-command version of the exact sequence that was verified end to
   end by hand (fresh clone, empty database, through to a working admin
   login). See "About the seed data" below for what that verification found,
   and `php bin/setup.php --help`-style comments at the top of the script
   itself for flags (e.g. `--skip-content` for schema + RBAC + administrator
   only, or `--mysql-bin` if it can't find your `mysql` binary).

   It's safe to rerun: it skips the schema import and administrator creation
   if they're already done, and won't duplicate staff records on a second
   run. If you'd rather run each step by hand (or need the schema created by
   a different, more privileged account than the one in `.env` — see
   [CONNECTIVITY.md](docs/CONNECTIVITY.md)), the exact commands it runs, in
   order, are:

   ```powershell
   mysql -u root < database/schema.sql
   php database/seeds/000-rbac-and-reference-data.php
   php bin\create-admin.php --name="Your Name" --email="you@example.org"
   php database/seeds/homepage-hero.php
   php database/seeds/deans-office-content-2026.php
   php bin/seed-handbook-batch.php --apply --publish
   php database/seeds/site-settings.php
   php database/seeds/homepage-sections.php
   php database/seeds/about-pages.php
   php database/seeds/about-page-sections.php
   php database/seeds/001-staff-bio-enrichment.php
   php database/seeds/002-website-info-guide-2026.php
   php bin/database-check.php
   ```

   The order matters: `000-rbac-and-reference-data.php` must run before
   `bin/create-admin.php` (which needs the `super_admin` role to already
   exist), `deans-office-content-2026.php` needs an active image to already
   exist (from `homepage-hero.php`), and `seed-handbook-batch.php` only
   updates programmes that already exist by slug rather than creating them
   (that's `deans-office-content-2026.php`'s job). The Dean's Office importer
   and the enrichment seeds are safe to rerun — they update existing rows in
   place by stable slug rather than duplicating them.

5. Run the tests:

   ```powershell
   php tests/run.php
   ```

   Two failures are expected here and are not a sign of a broken setup — see
   "About the seed data" below.

Never commit `.env`, local uploads, runtime logs, or cache files.

## About the seed data

- `database/seeds/003-site-photography.php` onward — site photography, page
  hero galleries, featured innovation, research agenda, research unit photos,
  the testing placeholder photos, and the LinkedIn news posts (`009`–`013`)
  — are **one-time historical imports, not reproducible setup steps**. They
  read real photographs and source documents from paths that only ever
  existed on the original importer's machine or a since-deleted temporary
  working directory. Those paths cannot be recreated by anyone, on any
  machine, including the one they originally ran on. They already ran once
  against the live database; do not add them to a fresh setup, and don't be
  surprised if running one directly fails outright.
- Because of that, `public/uploads/` — deliberately excluded from git, since
  it holds real uploaded photographs rather than code — is the **only
  surviving copy** of a meaningful slice of the site's photography. If it's
  ever lost, that photography cannot be re-seeded from anything in this
  repository. Back it up separately and on a regular schedule; pushing this
  repository to GitHub does not protect it.
- On a correctly-seeded fresh install, `php tests/run.php` and
  `php tests/integration/run.php` report exactly two failures:
  `HandbookSeedTest` (a specific staff portrait referenced by the handbook
  seed isn't present, for the reason above) and `MvpContentFlowTest`
  (content that only the one-time imports above would have added). Any other
  failure means something is genuinely wrong.

## Documentation

- [Database schema](docs/DATABASE_SCHEMA.md)
- [Database connectivity](docs/CONNECTIVITY.md)
- [Authentication and administrator setup](docs/AUTHENTICATION.md)
- [Administration portal](docs/ADMIN_PORTAL.md)
- [Departments](docs/DEPARTMENTS.md)
- [Staff directory](docs/STAFF.md)
- [Programmes and curricula](docs/PROGRAMMES.md)
- [Research](docs/RESEARCH.md)
- [Branding](docs/BRANDING.md)
- [UI/UX](docs/UI_UX.md)
- [Changelog](docs/CHANGELOG.md)

## Testing

Run the unit suite with `php tests/run.php`. Database-backed integration tests
are available through `php tests/integration/run.php` and require a configured
local test database.

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
2. Copy `.env.example` to `.env` and enter local database credentials.
3. Configure the web server document root to the `public/` directory.
4. Initialize managed site defaults:

   ```powershell
   php database/seeds/site-settings.php
   php database/seeds/homepage-sections.php
   php database/seeds/about-pages.php
   php database/seeds/page-hero-images.php
   php database/seeds/about-page-sections.php
   php database/seeds/deans-office-content-2026.php
   ```

   The Dean's Office importer is safe to rerun. It updates authoritative copy
   by stable slug, keeps approved programmes public, stores proposed programmes
   as drafts, and preserves editor-managed media and curricula.

5. Verify the application:

   ```powershell
   php bin/database-check.php
   php tests/run.php
   ```

Use a dedicated, least-privileged database account. Never commit `.env`, local
uploads, runtime logs, or cache files.

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

# Database Connectivity

## Current state

The reusable PDO connection layer and read-only CLI verifier are implemented.
No credentials are committed, and no live database write is performed by the
verifier.

## One-time local account setup

In phpMyAdmin, sign in with a database administrator account and run the
following after replacing the password placeholder with a strong, unique local
password:

```sql
CREATE USER 'fast_app'@'localhost'
IDENTIFIED BY 'REPLACE_WITH_A_STRONG_RANDOM_PASSWORD';

GRANT SELECT, INSERT, UPDATE, DELETE
ON `fast_website_db`.*
TO 'fast_app'@'localhost';
```

Do not grant `CREATE`, `ALTER`, `DROP`, `FILE`, `SUPER`, `GRANT OPTION`, or
`ALL PRIVILEGES` to the application account.

If `fast_app` already exists, do not recreate it blindly. Review its grants and
use `ALTER USER` deliberately if its password must be changed.

## Local environment file

From PowerShell in the repository root:

```powershell
Copy-Item -LiteralPath '.env.example' -Destination '.env'
notepad .env
```

Set:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=fast_website_db
DB_USERNAME=fast_app
DB_PASSWORD="your local password"
DB_CHARSET=utf8mb4
```

The real `.env` is ignored by Git. Do not paste its password into chat or place
it in `.env.example`.

## Run verification

```powershell
php bin\database-check.php
```

Exit codes:

- `0`: expected structure, starter data, and least privilege verified
- `1`: configuration or connection failure
- `2`: connected, but a structure, starter-data, or privilege check needs review

The JSON result reports:

- Selected database and server version
- Connection charset, collation, and session timezone
- Table, foreign-key, and check-constraint counts
- Required authentication/governance tables
- Minimum starter-record counts
- Elevated application-account privilege warnings

It never prints the database password.

To check whether website administrator/editor accounts exist without displaying
password hashes, run:

```powershell
php bin\user-check.php
```

<?php

declare(strict_types=1);

/**
 * One-command local setup for a fresh clone: creates the database schema,
 * seeds RBAC + reference data, creates the first administrator, seeds real
 * site content in the verified working order, then runs the health check.
 *
 * This automates exactly the sequence documented in README.md "Local
 * setup" - that sequence was worked out by actually doing it by hand
 * against an empty database until every step verified clean. See that
 * section (and "About the seed data") for what each step does and why the
 * order matters; this script exists so nobody has to run it by hand again.
 *
 * Usage:
 *   php bin/setup.php [options]
 *
 * Options (all optional - defaults come from .env):
 *   --mysql-bin=PATH     Path to the mysql CLI binary (default: "mysql")
 *   --db-host=HOST       Overrides DB_HOST, for the schema-import step only
 *   --db-port=PORT       Overrides DB_PORT, for the schema-import step only
 *   --db-user=USER       Overrides DB_USERNAME, for the schema-import step only
 *   --db-password=PASS   Overrides DB_PASSWORD, for the schema-import step only
 *   --skip-content       Stop once the first administrator is created -
 *                        schema + RBAC + admin only, no site content
 *
 * The db-* overrides exist because database/schema.sql needs a privileged
 * account (CREATE/ALTER), while the application's own .env credentials are
 * meant to be a least-privilege account without those grants (see
 * docs/CONNECTIVITY.md). For a local dev database where one account (often
 * root) does everything, the .env values already work and none of these
 * flags are needed.
 *
 * The target database itself always comes from .env's DB_DATABASE and is
 * never overridden separately - it must already exist before this script
 * runs (locally that's usually "fast_website_db"; on shared hosting it's
 * whatever name the host assigned, often prefixed with your account
 * username - create it there first, then point DB_DATABASE at that name).
 */

use FastWebsite\Core\Env;

require dirname(__DIR__) . '/bootstrap/autoload.php';

$root = dirname(__DIR__);
$phpBinary = PHP_BINARY;

$options = getopt('', [
    'mysql-bin::', 'db-host::', 'db-port::', 'db-user::', 'db-password::', 'skip-content',
]);

Env::load($root . '/.env');

/**
 * proc_open() on Windows does not search PATH the way a real shell does, so
 * a bare "mysql" that works fine typed into a terminal can still fail here
 * with "CreateProcess failed, error code: 2". Fall back to the common XAMPP
 * install location before giving up and telling the user to pass
 * --mysql-bin explicitly.
 */
function resolveMysqlBinary(?string $override): string
{
    if ($override !== null) {
        return $override;
    }

    if (PHP_OS_FAMILY === 'Windows') {
        foreach (['C:/xampp/mysql/bin/mysql.exe'] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }
    }

    return 'mysql';
}

$mysqlBinary = resolveMysqlBinary(isset($options['mysql-bin']) ? (string) $options['mysql-bin'] : null);
$dbHost = (string) ($options['db-host'] ?? Env::get('DB_HOST', '127.0.0.1'));
$dbPort = (int) ($options['db-port'] ?? Env::integer('DB_PORT', 3306));
$dbUser = (string) ($options['db-user'] ?? Env::get('DB_USERNAME', 'root'));
$dbPassword = (string) ($options['db-password'] ?? Env::get('DB_PASSWORD', ''));
// The database itself is never overridden separately from .env's
// DB_DATABASE - the schema-import connection and the app's runtime
// connection must always target the exact same database. On shared
// hosting (e.g. cPanel) that database is created ahead of time through
// the host's own UI, usually under an account-prefixed name rather than
// this project's own "fast_website_db" convention - DB_DATABASE just
// needs to already say whatever that real name is.
$dbName = (string) Env::get('DB_DATABASE', 'fast_website_db');
$skipContent = array_key_exists('skip-content', $options);

function step(string $label): void
{
    fwrite(STDOUT, PHP_EOL . '==> ' . $label . PHP_EOL);
}

function note(string $message): void
{
    fwrite(STDOUT, '    ' . $message . PHP_EOL);
}

/**
 * Runs a command, streaming its output live, optionally with a file piped
 * in as stdin (for `mysql < schema.sql`) or the real console handed to it
 * (for the interactive admin-creation prompts). Throws on a non-zero exit.
 *
 * @param list<string> $command
 */
function run(array $command, string $cwd, ?string $stdinFile = null, bool $inheritStdin = false): int
{
    $descriptors = [
        1 => STDOUT,
        2 => STDERR,
    ];
    $descriptors[0] = match (true) {
        $stdinFile !== null => ['file', $stdinFile, 'r'],
        $inheritStdin => STDIN,
        default => ['pipe', 'r'],
    };

    $process = @proc_open($command, $descriptors, $pipes, $cwd);

    if (!is_resource($process)) {
        $hint = $command[0] !== PHP_BINARY
            ? ' If this is the mysql CLI, pass --mysql-bin="<full path to mysql.exe>".'
            : '';
        throw new RuntimeException('Could not start: ' . implode(' ', $command) . $hint);
    }

    if ($stdinFile === null && !$inheritStdin && isset($pipes[0])) {
        fclose($pipes[0]);
    }

    return proc_close($process);
}

/**
 * @param list<string> $command
 */
function runOrFail(array $command, string $cwd, ?string $stdinFile = null, bool $inheritStdin = false): void
{
    $exitCode = run($command, $cwd, $stdinFile, $inheritStdin);

    if ($exitCode !== 0) {
        throw new RuntimeException(
            'Command failed with exit code ' . $exitCode . ': ' . implode(' ', $command)
        );
    }
}

function usersTableExists(string $host, int $port, string $user, string $password, string $database): bool
{
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port),
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $statement = $pdo->query('SHOW TABLES FROM `' . $database . "` LIKE 'users'");

        return $statement !== false && $statement->fetchColumn() !== false;
    } catch (Throwable) {
        return false;
    }
}

try {
    if (!Env::get('DB_USERNAME')) {
        throw new RuntimeException(
            '.env is missing or has no DB_USERNAME. Copy .env.example to .env and fill in '
            . 'database credentials first (see README.md "Local setup", step 2).'
        );
    }

    step('Database schema');

    if (usersTableExists($dbHost, $dbPort, $dbUser, $dbPassword, $dbName)) {
        note('"' . $dbName . '" already has a users table - skipping (schema.sql is not safe to rerun).');
    } else {
        note('Importing database/schema.sql into "' . $dbName . '" via the mysql CLI...');
        note(
            'That database must already exist - create it first (locally: '
            . '"CREATE DATABASE ' . $dbName . '"; on shared hosting: through the host\'s '
            . 'database tool, e.g. cPanel\'s MySQL Databases).'
        );
        $schemaPath = $root . '/database/schema.sql';
        runOrFail(
            [
                $mysqlBinary, '-h', $dbHost, '-P', (string) $dbPort, '-u', $dbUser,
                ...($dbPassword !== '' ? ['-p' . $dbPassword] : []),
                $dbName,
            ],
            $root,
            $schemaPath
        );
        note('Schema created.');
    }

    step('RBAC and reference data (roles, permissions, faculty, departments)');
    runOrFail([$phpBinary, 'database/seeds/000-rbac-and-reference-data.php'], $root);

    step('First administrator');
    $status = run([$phpBinary, 'bin/create-admin.php', '--status'], $root);

    if ($status === 0) {
        note('Enter the new administrator\'s details, then a temporary password twice when prompted.');
        runOrFail([$phpBinary, 'bin/create-admin.php'], $root, null, true);
    } else {
        note('An administrator already exists - skipping (bin/create-admin.php disables itself after the first one).');
    }

    if ($skipContent) {
        step('Done (schema + RBAC + administrator only, per --skip-content)');
        exit(0);
    }

    step('Site content (homepage image, Dean\'s Office copy, staff/programme handbook data)');
    runOrFail([$phpBinary, 'database/seeds/homepage-hero.php'], $root);
    runOrFail([$phpBinary, 'database/seeds/deans-office-content-2026.php'], $root);

    $staffCount = (int) (new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName),
        $dbUser,
        $dbPassword
    ))->query('SELECT COUNT(*) FROM staff')->fetchColumn();

    if ($staffCount > 0) {
        note('staff already has rows - skipping bin/seed-handbook-batch.php (it creates staff, not upserts, so rerunning it would duplicate them).');
    } else {
        runOrFail([$phpBinary, 'bin/seed-handbook-batch.php', '--apply', '--publish'], $root);
    }

    runOrFail([$phpBinary, 'database/seeds/site-settings.php'], $root);
    runOrFail([$phpBinary, 'database/seeds/homepage-sections.php'], $root);
    runOrFail([$phpBinary, 'database/seeds/about-pages.php'], $root);
    runOrFail([$phpBinary, 'database/seeds/about-page-sections.php'], $root);
    runOrFail([$phpBinary, 'database/seeds/001-staff-bio-enrichment.php'], $root);
    runOrFail([$phpBinary, 'database/seeds/002-website-info-guide-2026.php'], $root);
    note(
        'database/seeds/003-site-photography.php onward were skipped on purpose - '
        . 'see README.md "About the seed data".'
    );

    step('Health check');
    $healthCheckExit = run([$phpBinary, 'bin/database-check.php'], $root);

    if ($healthCheckExit !== 0) {
        note(
            'database-check.php reported an issue above. A "least_privilege" warning is '
            . 'expected if DB_USERNAME in .env is root or another privileged account - see '
            . 'docs/CONNECTIVITY.md if you want a scoped account instead. Any other failure '
            . 'means something above did not go as expected.'
        );
    }

    step('Setup complete');
    note('Point your web server\'s document root at public/ (with mod_rewrite) and sign in with the administrator you just created.');
    note('php -S does not process .htaccess and will not produce working clean URLs - use Apache for anything beyond a quick check.');

    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, PHP_EOL . 'Setup failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

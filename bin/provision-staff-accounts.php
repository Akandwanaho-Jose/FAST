<?php

declare(strict_types=1);

/**
 * One-time bulk provisioning: creates a login account for every staff
 * member who has an institutional_email but no linked user_id yet, links
 * it via staff.user_id, and forces a password change on first login
 * (must_change_password=1). Idempotent - already-linked staff are skipped.
 *
 * There is no self-service password reset flow in this codebase, so the
 * generated temporary password for each account is written to a CSV file
 * (not printed to the terminal) for an admin to distribute securely and
 * then delete.
 */

use FastWebsite\Core\Database;

/** @var Database $database */
$database = require dirname(__DIR__) . '/bootstrap/database.php';
$connection = $database->connection();

$staff = $connection->query(
    "SELECT id, first_name, last_name, institutional_email
     FROM staff
     WHERE deleted_at IS NULL
       AND user_id IS NULL
       AND institutional_email IS NOT NULL
       AND institutional_email <> ''"
)->fetchAll();

if ($staff === []) {
    echo "No staff need accounts provisioned.\n";
    exit(0);
}

$insertUser = $connection->prepare(
    'INSERT INTO users (name, email, password_hash, is_active, must_change_password)
     VALUES (:name, :email, :password_hash, 1, 1)'
);
$linkStaff = $connection->prepare('UPDATE staff SET user_id = :user_id WHERE id = :id');
$findExistingUser = $connection->prepare('SELECT id FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1');

// Appended, never overwritten - a prior run's undistributed temporary
// passwords must survive a later run (there is no self-service password
// reset flow in this codebase, so losing an unclaimed one is unrecoverable
// short of a manual database fix).
$outputPath = dirname(__DIR__) . '/storage/staff-account-credentials.csv';
$isNewFile = !is_file($outputPath);
$csv = fopen($outputPath, 'a');
if ($isNewFile) {
    fputcsv($csv, ['staff_id', 'name', 'email', 'temporary_password']);
}

$provisioned = 0;

foreach ($staff as $row) {
    $name = trim($row['first_name'] . ' ' . $row['last_name']);
    $email = strtolower(trim($row['institutional_email']));

    $connection->beginTransaction();

    try {
        // An admin account may already exist under this same institutional
        // email (e.g. someone who is both an administrator and a staff
        // member) - link that existing account instead of failing on the
        // email's uniqueness constraint. No new credentials are generated
        // or need distributing in that case; they already have a password.
        $findExistingUser->execute(['email' => $email]);
        $existingUserId = $findExistingUser->fetchColumn();

        if ($existingUserId !== false) {
            $userId = (int) $existingUserId;
            $temporaryPassword = null;
        } else {
            $temporaryPassword = generateTemporaryPassword();
            $insertUser->execute([
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
            ]);
            $userId = (int) $connection->lastInsertId();
        }

        $linkStaff->execute(['user_id' => $userId, 'id' => $row['id']]);

        $connection->commit();
    } catch (Throwable $e) {
        $connection->rollBack();
        fwrite(STDERR, "Skipped staff #{$row['id']} ({$email}): {$e->getMessage()}\n");
        continue;
    }

    if ($temporaryPassword !== null) {
        fputcsv($csv, [$row['id'], $name, $email, $temporaryPassword]);
    }
    $provisioned++;
}

fclose($csv);

echo "Provisioned {$provisioned} staff account(s).\n";
echo "Temporary credentials written to: {$outputPath}\n";
echo "Distribute these securely to each staff member, then delete the file.\n";

function generateTemporaryPassword(): string
{
    $lower = 'abcdefghjkmnpqrstuvwxyz';
    $upper = 'ABCDEFGHJKMNPQRSTUVWXYZ';
    $digits = '23456789';
    $symbols = '!@#$%^&*-_+=';

    $password = $lower[random_int(0, strlen($lower) - 1)]
        . $upper[random_int(0, strlen($upper) - 1)]
        . $digits[random_int(0, strlen($digits) - 1)]
        . $symbols[random_int(0, strlen($symbols) - 1)];

    $all = $lower . $upper . $digits . $symbols;

    for ($i = 0; $i < 10; $i++) {
        $password .= $all[random_int(0, strlen($all) - 1)];
    }

    return str_shuffle($password);
}

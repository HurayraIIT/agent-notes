<?php

// Brings the database to the current schema. Safe to rerun:
//   - an empty database gets schema.sql, and every migration is recorded as already applied
//     (a first import that stopped halfway is resumed);
//   - an existing one gets each migrations/*.sql not yet recorded in schema_migrations, in name order.
// Optional: ADMIN_EMAIL re-points the seeded admin (users.id = 1) on a fresh install.
// Run: php bin/migrate.php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

/** Splits a .sql file on statement-ending semicolons (the files here hold no procedures or string semicolons). */
function sql_statements(string $sql): array
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    return array_values(array_filter(array_map('trim', explode(";\n", $sql . "\n")), fn($s) => $s !== '' && $s !== ';'));
}

/**
 * MySQL DDL auto-commits, so a migration that fails halfway has already applied its first statements.
 * A rerun skips "already exists" errors (table 1050, column 1060, key 1061) and carries on from there.
 */
function run_sql_file(string $file, bool $resuming = false): void
{
    // A resumed fresh import also skips the seed row it already inserted (duplicate key 1062).
    $skip = $resuming ? [1050, 1060, 1061, 1062] : [1050, 1060, 1061];
    foreach (sql_statements(file_get_contents($file)) as $stmt) {
        try {
            db()->exec(rtrim($stmt, ';'));
        } catch (PDOException $e) {
            if (!in_array((int) ($e->errorInfo[1] ?? 0), $skip, true)) {
                throw $e;
            }
        }
    }
}

$pdo = db();
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(190) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)');
$migrations = glob(__DIR__ . '/../migrations/*.sql') ?: [];
sort($migrations);
$record = $pdo->prepare('INSERT IGNORE INTO schema_migrations (name) VALUES (?)');

// A fresh import is marked pending before its first statement and cleared only when it is complete, so
// an import that stops halfway resumes as a fresh import on rerun, not as an existing install.
$pending = '000_fresh_import_pending';
$resuming = (bool) $pdo->query("SELECT 1 FROM schema_migrations WHERE name = '{$pending}'")->fetch();
if ($resuming || !$pdo->query("SHOW TABLES LIKE 'notes'")->fetch()) {
    $record->execute([$pending]);
    run_sql_file(__DIR__ . '/../schema.sql', resuming: true); // already the newest shape
    $pdo->beginTransaction(); // the admin re-point and the completion mark land together
    $admin = strtolower(trim((string) env('ADMIN_EMAIL', '')));
    if ($admin !== '' && filter_var($admin, FILTER_VALIDATE_EMAIL)) {
        $pdo->prepare('UPDATE users SET email = ? WHERE id = 1')->execute([$admin]);
    }
    foreach ($migrations as $m) {
        $record->execute([basename($m)]);
    }
    $pdo->prepare('DELETE FROM schema_migrations WHERE name = ?')->execute([$pending]);
    $pdo->commit();
    echo "migrate: fresh database, imported schema.sql\n";
    exit(0);
}

$done = $pdo->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
foreach ($migrations as $m) {
    if (in_array(basename($m), $done, true)) {
        continue;
    }
    run_sql_file($m);
    $record->execute([basename($m)]);
    echo 'migrate: applied ' . basename($m) . "\n";
}
echo "migrate: up to date\n";

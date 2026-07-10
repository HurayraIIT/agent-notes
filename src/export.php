<?php

declare(strict_types=1);

/** Streams a mysqldump of the app database as a .sql download (admin only). */
function admin_db_export(): never
{
    $candidates = array_filter([
        env('MYSQLDUMP_PATH'),
        trim((string) shell_exec('command -v mysqldump 2>/dev/null')),
        '/usr/local/mysql/bin/mysqldump',
        '/usr/bin/mysqldump',
        '/opt/homebrew/bin/mysqldump',
    ]);
    $bin = null;
    foreach ($candidates as $candidate) {
        if (is_executable($candidate)) {
            $bin = $candidate;
            break;
        }
    }
    if (!$bin) {
        redirect('/admin?export_error=' . rawurlencode('mysqldump binary not found. Set MYSQLDUMP_PATH in .env.'));
    }

    $cmd = sprintf(
        '%s --host=%s --port=%s --user=%s --single-transaction --no-tablespaces %s 2>&1',
        escapeshellcmd($bin),
        escapeshellarg(env('DB_HOST', '127.0.0.1')),
        escapeshellarg(env('DB_PORT', '3306')),
        escapeshellarg(env('DB_USERNAME', 'root')),
        escapeshellarg(env('DB_DATABASE', 'agent_notes'))
    );

    // password via env var, not argv (argv is visible in process lists)
    putenv('MYSQL_PWD=' . env('DB_PASSWORD', ''));
    exec($cmd, $lines, $exit);
    putenv('MYSQL_PWD');

    if ($exit !== 0) {
        error_log('admin_db_export failed: ' . implode("\n", array_slice($lines, 0, 5)));
        redirect('/admin?export_error=' . rawurlencode('Export failed — check the server logs.'));
    }

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . env('DB_DATABASE', 'agent_notes') . '-' . date('Ymd-His') . '.sql"');
    echo implode("\n", $lines) . "\n";
    exit;
}

<?php

declare(strict_types=1);

/**
 * Fixed-window counter backed by the rate_limits table.
 * Returns true when the caller is over the limit for the current window.
 */
function rate_limited(string $key, int $limit, int $windowSeconds = 60): bool
{
    $windowStart = date('Y-m-d H:i:s', intdiv(time(), $windowSeconds) * $windowSeconds);

    $stmt = db()->prepare(
        'INSERT INTO rate_limits (bucket_key, window_start, count) VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE count = count + 1'
    );
    $stmt->execute([$key, $windowStart]);

    $stmt = db()->prepare('SELECT count FROM rate_limits WHERE bucket_key = ? AND window_start = ?');
    $stmt->execute([$key, $windowStart]);
    $count = (int) $stmt->fetchColumn();

    // ponytail: probabilistic cleanup instead of a cron job
    if (random_int(1, 100) === 1) {
        db()->exec("DELETE FROM rate_limits WHERE window_start < NOW() - INTERVAL 1 DAY");
    }

    return $count > $limit;
}

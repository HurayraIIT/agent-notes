#!/bin/sh
# The app reads its settings from /app/.env (src/bootstrap.php), and php-fpm workers start with a clean
# environment. So the container's settings are copied into /app/.env at start, by key name, and nothing
# else from the environment reaches PHP. PHP writes the file: getenv() keeps each value whole, so a value
# containing a newline is refused instead of being split into a second KEY=value line.
set -eu
umask 027
php -r '
$keep = "/^(APP_|DB_|MAIL_|FEATURE_|ASSET_|SHARE_|RATE_LIMIT_|NOTE_MAX_)[A-Z0-9_]*$|^(PUBLIC_URL|MYSQLDUMP_PATH|ADMIN_EMAIL)$/";
$out = "";
foreach (getenv() as $k => $v) {
    if (!preg_match($keep, $k)) {
        continue;
    }
    if (preg_match("/[\r\n]/", $v)) {
        fwrite(STDERR, "agent-notes-entrypoint: refusing $k (value contains a line break)\n");
        exit(1);
    }
    $out .= $k . "=\"" . $v . "\"\n"; // load_env() strips one pair of outer quotes; inner quotes stay literal
}
file_put_contents("/app/.env.tmp", $out) !== false || exit(1);
'
chown root:www-data /app/.env.tmp
chmod 0640 /app/.env.tmp
mv /app/.env.tmp /app/.env
exec "$@"

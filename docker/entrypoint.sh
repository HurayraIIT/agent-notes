#!/bin/sh
# The app reads its settings from /app/.env (src/bootstrap.php), and php-fpm workers start with a clean
# environment. So the container's settings are copied into /app/.env at start, by key prefix, and
# nothing else from the environment reaches PHP. Values are written without echoing them anywhere.
set -eu
umask 027
tmp=/app/.env.tmp
: > "$tmp"
env | grep -E '^(APP_|DB_|MAIL_|FEATURE_|ASSET_|SHARE_|PUBLIC_URL=|RATE_LIMIT_|NOTE_MAX_|MYSQLDUMP_PATH=|ADMIN_EMAIL=)' \
    | while IFS= read -r line; do
        # split on the first "=" only: generated secrets may end in "="
        printf '%s="%s"\n' "${line%%=*}" "${line#*=}" >> "$tmp"
    done
chown root:www-data "$tmp"
chmod 0640 "$tmp"
mv "$tmp" /app/.env
exec "$@"

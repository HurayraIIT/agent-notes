#!/bin/sh
# Runs from the nginx image's /docker-entrypoint.d before nginx starts. With ROBOTS_NOINDEX=true, the
# 4xx/5xx responses nginx makes itself (and any PHP didn't tag) get X-Robots-Tag; otherwise none do.
set -eu
tag=''
# Lowercased, like robots_noindex() in src/bootstrap.php: TRUE and True count too.
[ "$(printf %s "${ROBOTS_NOINDEX:-false}" | tr "[:upper:]" "[:lower:]")" = "true" ] && tag='noindex, nofollow, noarchive'
printf 'map "$status:$upstream_http_x_robots_tag" $agent_notes_error_robots {\n    "~^[45][0-9][0-9]:$" "%s";\n    default "";\n}\n' "$tag" \
    > /etc/nginx/agent-notes-robots.conf

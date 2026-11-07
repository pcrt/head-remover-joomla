#!/usr/bin/env bash
# Runtime verification on the official Joomla images (5.4 on PHP 8.3, 6.2 on PHP 8.4) with MariaDB 11,
# plus php -l on PHP 8.1 and 8.4. Creates its own network and containers (prefix headremover-test-*)
# and removes only them on exit; existing containers are never touched.
# Optional: JEDCHECKER_ZIP=/path/to/com_jedchecker.zip installs JED Checker in each site and runs its
# rules on the plugin package through tests/jedchecker-run.php (fails on errors or warnings).
set -euo pipefail
cd "$(dirname "$0")/.."
root="$PWD"
# Fail before creating resources if the daemon is unavailable.
docker info >/dev/null
./build.sh
mkdir -p tests/results
project="headremover-test-$(date +%s)-$$"
task_tmp=$(mktemp -d)
cleanup() {
    docker rm -fv "${project}-j5" "${project}-j6" "${project}-db" >/dev/null 2>&1 || true
    docker network rm "$project" >/dev/null 2>&1 || true
    rm -rf "$task_tmp"
}
trap cleanup EXIT INT TERM
python3 -I - "$task_tmp" <<'PY'
import secrets, string, sys
from pathlib import Path
r = Path(sys.argv[1])
alnum = string.ascii_letters + string.digits
rnd = lambda n: ''.join(secrets.choice(alnum) for _ in range(n))
db = rnd(32)
(r/'db.env').write_text(f'MARIADB_ROOT_PASSWORD={rnd(32)}\nMARIADB_DATABASE=joomla5\nMARIADB_USER=joomla\nMARIADB_PASSWORD={db}\n')
(r/'init.sql').write_text("CREATE DATABASE IF NOT EXISTS joomla6 CHARACTER SET utf8mb4; GRANT ALL ON joomla6.* TO 'joomla'@'%'; FLUSH PRIVILEGES;\n")
(r/'joomla.env').write_text('JOOMLA_DB_HOST=db\nJOOMLA_DB_USER=joomla\n' + f'JOOMLA_DB_PASSWORD={db}\n'
    + 'JOOMLA_DB_PREFIX=hr_\nJOOMLA_SITE_NAME=Head Remover Test\nJOOMLA_ADMIN_USER=Test Administrator\nJOOMLA_ADMIN_USERNAME=hradmin\n'
    + f'JOOMLA_ADMIN_PASSWORD={rnd(24)}\n' + 'JOOMLA_ADMIN_EMAIL=test@example.com\n')
PY
chmod 600 "$task_tmp"/*.env

echo "== PHP lint"
for version in 8.1 8.4; do
    docker run --rm -v "$root:/work:ro" -w /work "php:${version}-cli" sh -c 'php -v | head -1; find src tests -name "*.php" -print0 | xargs -0 -n1 php -l'
done

docker network create "$project" >/dev/null
docker run -d --name "${project}-db" --network "$project" --network-alias db --env-file "$task_tmp/db.env" \
    -v "$task_tmp/init.sql:/docker-entrypoint-initdb.d/databases.sql:ro" mariadb:11 >/dev/null
for attempt in $(seq 1 120); do
    if docker exec "${project}-db" healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1; then break; fi
    if [ "$attempt" = 120 ]; then echo 'MariaDB startup timed out' >&2; exit 1; fi
    sleep 1
done

# Joomla CLI as the web user, from the site root.
cli() { docker exec -u www-data -w /var/www/html "$1" php cli/joomla.php "${@:2}"; }
setup() { docker exec -u www-data "$1" php /tests/setup.php "${@:2}"; }

for major in 5 6; do
    case "$major" in
        5) image=joomla:5-php8.3-apache; expected=5.4;;
        6) image=joomla:6-php8.4-apache; expected=6.2;;
    esac
    container="${project}-j${major}"
    results="tests/results/joomla-${major}"
    echo "== Joomla ${major}: ${image}"
    docker run -d --name "$container" --network "$project" --env-file "$task_tmp/joomla.env" -e "JOOMLA_DB_NAME=joomla${major}" \
        -p 127.0.0.1::80 -v "$root/tests:/tests:ro" -v "$root/dist:/package:ro" "$image" >/dev/null
    # The image installs Joomla from the JOOMLA_* variables and removes the installation folder.
    for attempt in $(seq 1 300); do
        if docker exec "$container" sh -c 'test -f /var/www/html/configuration.php && ! test -d /var/www/html/installation'; then break; fi
        if [ "$attempt" = 300 ]; then echo 'Joomla installation timed out' >&2; docker logs "$container" | tail -20 >&2; exit 1; fi
        sleep 1
    done
    port=$(docker port "$container" 80/tcp | sed 's/.*://')
    url="http://127.0.0.1:${port}"
    setup "$container" version "$expected" | tee "${results}-version.log"
    # Template fixture and maximum error reporting are applied before the first web request: the image
    # enables opcache with revalidate_freq=2, so a template edited after being compiled stays stale for a while.
    setup "$container" fixture
    docker exec "$container" sed -i "s/public \$error_reporting = 'default';/public \$error_reporting = 'maximum';/" /var/www/html/configuration.php
    docker exec "$container" grep -q "error_reporting = 'maximum'" /var/www/html/configuration.php
    curl --fail --silent --show-error --output /dev/null --retry 20 --retry-delay 1 --retry-connrefused "$url/"
    curl --fail --silent --show-error "$url/" > "${results}-baseline.html"

    # Joomla extracts a package next to the file: it needs a writable copy, /package is read-only.
    docker exec "$container" sh -c 'mkdir -p /tmp/hrpkg && cp /package/plg_system_headremover-2.0.0.zip /tmp/hrpkg/ && chown -R www-data:www-data /tmp/hrpkg'
    cli "$container" extension:install --path=/tmp/hrpkg/plg_system_headremover-2.0.0.zip --no-interaction | tee "${results}-install.log"
    grep -q 'Extension installed successfully' "${results}-install.log"
    setup "$container" check-installed 0
    curl --fail --silent --show-error "$url/" > "${results}-installed.html"
    python3 -I tests/assert_html.py same "${results}-baseline.html" "${results}-installed.html"

    setup "$container" params frontend
    curl --fail --silent --show-error "$url/" > "${results}-enabled.html"
    code=$(curl --silent --output "${results}-error404.html" --write-out '%{http_code}' "$url/headremover-missing-page")
    if [ "$code" != 404 ]; then echo "Expected HTTP 404, got $code" >&2; exit 1; fi
    # The unconditional backend guard is tested with frontend_only=0.
    setup "$container" params backend
    curl --fail --silent --show-error "$url/administrator/" > "${results}-backend.html"
    python3 -I tests/assert_html.py full "${results}-baseline.html" "${results}-enabled.html" "${results}-backend.html" "${results}-error404.html"

    # The backend itself: login, plugin list, edit form rendering and a save through the real form.
    extension_id=$(setup "$container" check-installed 1 | sed -E 's/.*extension ([0-9]+),.*/\1/')
    HR_BASE="$url" HR_USER=hradmin HR_PASS="$(sed -n 's/^JOOMLA_ADMIN_PASSWORD=//p' "$task_tmp/joomla.env")" HR_EXT="$extension_id" python3 -I tests/admin_ui_test.py
    curl --fail --silent --show-error "$url/" > "${results}-ui-saved.html"
    python3 -I tests/assert_html.py uisave "${results}-ui-saved.html"

    # Installing the same package again must keep the configuration and change nothing.
    setup "$container" params frontend
    before=$(setup "$container" check-installed 1)
    cli "$container" extension:install --path=/tmp/hrpkg/plg_system_headremover-2.0.0.zip --no-interaction | tee "${results}-reinstall.log"
    grep -q 'Extension installed successfully' "${results}-reinstall.log"
    after=$(setup "$container" check-installed 1)
    if [ "$before" != "$after" ]; then echo "Reinstall changed the extension record: $before / $after" >&2; exit 1; fi
    echo "Reinstall kept: $after"
    curl --fail --silent --show-error "$url/" > "${results}-reinstalled.html"
    python3 -I tests/assert_html.py same "${results}-enabled.html" "${results}-reinstalled.html"

    setup "$container" params disabled
    curl --fail --silent --show-error "$url/" > "${results}-disabled.html"
    python3 -I tests/assert_html.py same "${results}-baseline.html" "${results}-disabled.html"

    docker logs "$container" > "${results}-container.log" 2>&1
    if grep -E 'PHP (Warning|Notice|Deprecated|Fatal|Parse)|Uncaught' "${results}-container.log"; then echo 'PHP messages in the container log' >&2; exit 1; fi
    if docker exec "$container" sh -c 'ls /var/www/html/administrator/logs/*.php 2>/dev/null | grep -q .'; then
        docker exec "$container" sh -c 'cat /var/www/html/administrator/logs/*.php' > "${results}-joomla.log"
        echo 'Joomla wrote log files, see tests/results' >&2
        exit 1
    fi
    echo "No PHP messages in container and Joomla logs"

    if [ -n "${JEDCHECKER_ZIP:-}" ]; then
        docker cp "$JEDCHECKER_ZIP" "$container:/tmp/hrpkg/com_jedchecker.zip"
        docker exec "$container" chown www-data:www-data /tmp/hrpkg/com_jedchecker.zip
        cli "$container" extension:install --path=/tmp/hrpkg/com_jedchecker.zip --no-interaction >/dev/null
        docker exec -u www-data -e HR_ZIP=/tmp/hrpkg/plg_system_headremover-2.0.0.zip "$container" php /tests/jedchecker-run.php | tee "${results}-jedchecker.log"
    fi
    docker rm -fv "$container" >/dev/null
    echo "== Joomla ${major}: PASS"
done
echo "ALL PASS"

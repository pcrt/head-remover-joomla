#!/usr/bin/env bash
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
python3 - "$task_tmp" <<'PY'
import secrets, sys
from pathlib import Path
r = Path(sys.argv[1]); db = secrets.token_hex(24)
(r/'db.env').write_text(f'MARIADB_ROOT_PASSWORD={secrets.token_hex(24)}\nMARIADB_DATABASE=joomla\nMARIADB_USER=joomla\nMARIADB_PASSWORD={db}\n')
(r/'init.sql').write_text("CREATE DATABASE joomla5; CREATE DATABASE joomla6; GRANT ALL ON joomla5.* TO 'joomla'@'%'; GRANT ALL ON joomla6.* TO 'joomla'@'%';\n")
(r/'joomla.env').write_text(f'JOOMLA_DB_HOST=db\nJOOMLA_DB_NAME=joomla\nJOOMLA_DB_USER=joomla\nJOOMLA_DB_PASSWORD={db}\nHR_ADMIN_PASSWORD={secrets.token_hex(24)}\n')
PY
chmod 600 "$task_tmp"/*.env
docker run --rm -v "$root:/work:ro" -w /work php:8.3-cli sh -c 'find src tests -name "*.php" -print0 | xargs -0 -n1 php -l'
docker network create "$project" >/dev/null
docker run -d --name "${project}-db" --network "$project" --network-alias db --env-file "$task_tmp/db.env" -v "$task_tmp/init.sql:/docker-entrypoint-initdb.d/databases.sql:ro" mariadb:11 >/dev/null
for attempt in $(seq 1 90); do
    if docker exec "${project}-db" healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1; then break; fi
    if [ "$attempt" = 90 ]; then echo 'MariaDB startup timed out' >&2; exit 1; fi
    sleep 1
done
for major in 5 6; do
    case "$major" in 5) expected=5.4;; 6) expected=6.2;; esac
    container="${project}-j${major}"
    docker run -d --name "$container" --network "$project" --env-file "$task_tmp/joomla.env" -e "JOOMLA_DB_NAME=joomla${major}" \
        -p 127.0.0.1::80 -v "$root/tests:/tests:ro" -v "$root/dist:/package:ro" "joomla:${major}" >/dev/null
    for attempt in $(seq 1 90); do
        if docker exec "$container" test -f /var/www/html/installation/joomla.php; then break; fi
        if [ "$attempt" = 90 ]; then echo 'Joomla copy timed out' >&2; exit 1; fi
        sleep 1
    done
    docker exec -w /var/www/html "$container" sh -c 'php installation/joomla.php install --site-name="Head Remover Test" --admin-user="Test Administrator" --admin-username=hradmin --admin-password="$HR_ADMIN_PASSWORD" --admin-email=test@example.invalid --db-type=mysqli --db-host="$JOOMLA_DB_HOST" --db-user="$JOOMLA_DB_USER" --db-pass="$JOOMLA_DB_PASSWORD" --db-name="$JOOMLA_DB_NAME" --db-prefix=hr_ --db-encryption=0' >"tests/results/joomla-${major}-install.log" 2>&1
    docker exec "$container" rm -rf /var/www/html/installation
    docker exec "$container" php /tests/setup.php install "$expected" | tee "tests/results/joomla-${major}-version.log"
    port=$(docker port "$container" 80/tcp | sed 's/.*://')
    curl --fail --silent --show-error --retry 10 --retry-connrefused "http://127.0.0.1:${port}/" > "tests/results/joomla-${major}-baseline.html"
    docker exec "$container" php /tests/setup.php enable
    curl --fail --silent --show-error "http://127.0.0.1:${port}/" > "tests/results/joomla-${major}-enabled.html"
    # Test the unconditional backend guard independently of frontend_only.
    docker exec "$container" php /tests/setup.php backend
    curl --fail --silent --show-error "http://127.0.0.1:${port}/administrator/" > "tests/results/joomla-${major}-backend.html"
    python3 tests/assert_html.py "tests/results/joomla-${major}-baseline.html" "tests/results/joomla-${major}-enabled.html" "tests/results/joomla-${major}-backend.html"
    docker rm -fv "$container" >/dev/null
done

#!/usr/bin/env bash
# Runs the whole test suite (Unit + Integration + E2E over HTTP) against a
# throwaway MySQL and a PHP built-in server, and writes the merged line
# coverage to build/coverage/clover.xml (+ junit.xml) for SonarQube.
#
#   scripts/coverage.sh
#
# Requirements: docker, php with xdebug, composer install (dev dependencies).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

DB_CONTAINER=ioms-coverage-db
DB_PORT="${COVERAGE_DB_PORT:-13320}"
APP_PORT="${COVERAGE_APP_PORT:-18090}"
DB_PASSWORD=coverage
COV_DIR="$ROOT/build/cov"
SERVER_PID=""

cleanup() {
    [ -n "$SERVER_PID" ] && kill "$SERVER_PID" 2>/dev/null || true
    docker rm -f "$DB_CONTAINER" >/dev/null 2>&1 || true
}
trap cleanup EXIT

rm -rf "$COV_DIR" "$ROOT/build/coverage"
mkdir -p "$COV_DIR" "$ROOT/build/coverage"
rm -rf "$ROOT/build/uploads"

docker rm -f "$DB_CONTAINER" >/dev/null 2>&1 || true
docker run -d --rm --name "$DB_CONTAINER" \
    -e MYSQL_ROOT_PASSWORD="$DB_PASSWORD" -e MYSQL_DATABASE=ioms \
    -p "$DB_PORT:3306" \
    --tmpfs /var/lib/mysql \
    -v "$ROOT/tests/support/create-test-db.sql:/docker-entrypoint-initdb.d/00-test-db.sql:ro" \
    -v "$ROOT/database/schema.sql:/docker-entrypoint-initdb.d/01-schema.sql:ro" \
    -v "$ROOT/database/seed.sql:/docker-entrypoint-initdb.d/02-seed.sql:ro" \
    mysql:8.0 --innodb-flush-log-at-trx-commit=0 --skip-log-bin >/dev/null

echo "Waiting for MySQL..."
for _ in $(seq 1 60); do
    if docker exec "$DB_CONTAINER" mysql -h127.0.0.1 -uroot -p"$DB_PASSWORD" -e "select 1 from ioms.users limit 1" >/dev/null 2>&1; then
        break
    fi
    sleep 2
done

# The integration tests expect the schema to be present in ioms_test.
docker exec -i "$DB_CONTAINER" mysql -uroot -p"$DB_PASSWORD" ioms_test <"$ROOT/database/schema.sql"

export XDEBUG_MODE=coverage
export E2E_COVERAGE_DIR="$COV_DIR"
export DB_HOST=127.0.0.1 DB_PORT="$DB_PORT" DB_DATABASE=ioms DB_USERNAME=root DB_PASSWORD="$DB_PASSWORD"
export APP_ENV=testing
export APP_UPLOAD_DIR=build/uploads

php -d variables_order=EGPCS -d upload_max_filesize=3M -d post_max_size=16M -d auto_prepend_file="$ROOT/tests/support/coverage-prepend.php" \
    -S "127.0.0.1:$APP_PORT" -t public public/index.php >"$ROOT/build/server.log" 2>&1 &
SERVER_PID=$!
for _ in $(seq 1 30); do
    curl -s -o /dev/null "http://127.0.0.1:$APP_PORT/login" && break
    sleep 1
done

export E2E_BASE_URL="http://127.0.0.1:$APP_PORT"
export E2E_DB_HOST=127.0.0.1 E2E_DB_PORT="$DB_PORT" E2E_DB_DATABASE=ioms E2E_DB_USERNAME=root E2E_DB_PASSWORD="$DB_PASSWORD"
export TEST_DB_HOST=127.0.0.1 TEST_DB_PORT="$DB_PORT" TEST_DB_USERNAME=root TEST_DB_PASSWORD="$DB_PASSWORD"

# The PHPUnit process itself must not record into the per-request directory.
E2E_COVERAGE_DIR="" vendor/bin/phpunit \
    --coverage-php "$COV_DIR/phpunit.cov" \
    --log-junit "$ROOT/build/coverage/junit.xml" "$@"

kill "$SERVER_PID" 2>/dev/null || true
SERVER_PID=""

# CLI script (cron job) is exercised by the E2E suite through the same prepend file.
vendor/bin/phpcov merge --clover "$ROOT/build/coverage/clover.xml" "$COV_DIR"

# SonarQube's scanner runs in a container that sees the project at /usr/src.
sed -i.bak "s#$ROOT/#/usr/src/#g" "$ROOT/build/coverage/clover.xml" "$ROOT/build/coverage/junit.xml"
rm -f "$ROOT"/build/coverage/*.bak
echo "Coverage written to build/coverage/clover.xml"

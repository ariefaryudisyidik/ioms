#!/usr/bin/env bash
# Resets the local development database to the data in database/schema.sql and
# database/seed.sql. MySQL only runs those files when its volume is empty, so the
# script removes the db volume and starts the stack again.
#
#   scripts/reset-db.sh              # asks for confirmation, keeps uploaded images
#   scripts/reset-db.sh --yes        # no confirmation
#   scripts/reset-db.sh --uploads    # also removes uploaded product images
#
# All orders, stock movements and users created through the app are lost.
set -euo pipefail

cd "$(cd "$(dirname "$0")/.." && pwd)"

ASSUME_YES=0
WIPE_UPLOADS=0
for arg in "$@"; do
    case "$arg" in
        -y|--yes) ASSUME_YES=1 ;;
        --uploads) WIPE_UPLOADS=1 ;;
        -h|--help) sed -n '2,10p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "Unknown option: $arg (use --yes, --uploads or --help)" >&2; exit 1 ;;
    esac
done

PROJECT="$(docker compose config --format json 2>/dev/null | sed -n 's/.*"name": *"\([^"]*\)".*/\1/p' | head -1)"
PROJECT="${PROJECT:-ioms}"

if [ "$ASSUME_YES" -eq 0 ]; then
    echo "This deletes ALL data in the local database and reloads schema.sql + seed.sql."
    [ "$WIPE_UPLOADS" -eq 1 ] && echo "Uploaded product images will be deleted too."
    read -r -p "Continue? [y/N] " answer
    case "$answer" in y|Y|yes|YES) ;; *) echo "Cancelled."; exit 1 ;; esac
fi

echo "Stopping the stack..."
docker compose down >/dev/null 2>&1

echo "Removing the database volume..."
docker volume rm "${PROJECT}_db_data" >/dev/null
if [ "$WIPE_UPLOADS" -eq 1 ]; then
    docker volume rm "${PROJECT}_uploads_data" >/dev/null 2>&1 || true
fi

echo "Starting the stack (MySQL loads schema and seed)..."
docker compose up -d >/dev/null 2>&1

echo "Waiting for MySQL to be healthy..."
for _ in $(seq 1 60); do
    status="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{end}}' "$(docker compose ps -q mysql)" 2>/dev/null || true)"
    [ "$status" = "healthy" ] && break
    sleep 2
done
if [ "${status:-}" != "healthy" ]; then
    echo "MySQL did not become healthy in time. Check: docker compose logs mysql" >&2
    exit 1
fi

echo "Done. App: http://localhost:8080  (seed password: Password123!)"

#!/usr/bin/env bash
# Demo bukti API-01: GET /api/products/{sku}/availability tanpa autentikasi, dengan autentikasi,
# dan untuk SKU yang tidak ditemukan. Menampilkan status HTTP, Content-Type, dan body JSON.
#
#   scripts/demo-api.sh                      # app di http://localhost:8080
#   BASE=http://localhost:8080 SKU=SKU-0001 EMAIL=sari.sales@ioms.test scripts/demo-api.sh
set -euo pipefail

BASE="${BASE:-http://localhost:8080}"
SKU="${SKU:-SKU-0001}"
EMAIL="${EMAIL:-sari.sales@ioms.test}"
PASSWORD="${PASSWORD:-Password123!}"   # password akun demo (lihat README)
JAR="$(mktemp)"
trap 'rm -f "$JAR"' EXIT

call() {  # $1 = judul, $2 = path, $3.. = opsi curl tambahan
    local title="$1" path="$2"; shift 2
    echo
    echo "### $title"
    echo "\$ curl -i $BASE$path"
    curl -s -i "$@" "$BASE$path" | tr -d '\r' | grep -E '^HTTP/|^Content-Type:|^\{|^\[' || true
}

call "1. Tanpa autentikasi (tanpa session)" "/api/products/$SKU/availability"

# Login: ambil token CSRF dari halaman login, lalu kirim email + password.
TOKEN="$(curl -s -c "$JAR" "$BASE/login" | grep -oE 'value="[a-f0-9]{20,}"' | head -1 | cut -d'"' -f2)"
curl -s -o /dev/null -b "$JAR" -c "$JAR" -d "_csrf=$TOKEN&email=$EMAIL&password=$PASSWORD" "$BASE/login"
echo
echo "(login sebagai $EMAIL)"

call "2. Dengan autentikasi, SKU ada ($SKU)" "/api/products/$SKU/availability" -b "$JAR"
call "3. Dengan autentikasi, SKU tidak ditemukan" "/api/products/SKU-TIDAK-ADA/availability" -b "$JAR"

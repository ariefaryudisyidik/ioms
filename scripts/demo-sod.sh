#!/usr/bin/env bash
# Demo segregation of duties (SO-01, brief §1.2): Sales and Warehouse Staff have no Approve button, so the
# rule is shown by sending the approve request by hand. The server must answer 403 and leave the order untouched.
#
#   scripts/demo-sod.sh                 # app di http://localhost:8080, order SO-2026-0011 (id 11, PendingApproval)
#   BASE=http://localhost:8080 SO_ID=11 scripts/demo-sod.sh
set -euo pipefail

BASE="${BASE:-http://localhost:8080}"
SO_ID="${SO_ID:-11}"
PASSWORD="${PASSWORD:-Password123!}"   # password akun demo (lihat README)
JAR="$(mktemp)"
trap 'rm -f "$JAR"' EXIT

token() { grep -oE 'name="csrf-token" content="[a-f0-9]+"' | head -1 | grep -oE '[a-f0-9]{20,}'; }

attempt() {  # $1 = role label, $2 = email
    : >"$JAR"
    local csrf
    csrf="$(curl -s -c "$JAR" "$BASE/login" | grep -oE 'value="[a-f0-9]{20,}"' | head -1 | cut -d'"' -f2)"
    curl -s -o /dev/null -b "$JAR" -c "$JAR" -d "_csrf=$csrf&email=$2&password=$PASSWORD" "$BASE/login"
    csrf="$(curl -s -b "$JAR" -c "$JAR" "$BASE/dashboard" | token)"

    echo
    echo "### $1 ($2) tries to approve SO id $SO_ID"
    echo "\$ curl -X POST $BASE/sales-orders/$SO_ID/approve   (valid session and CSRF token)"
    local status
    status="$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -c "$JAR" -d "_csrf=$csrf" "$BASE/sales-orders/$SO_ID/approve")"
    echo "HTTP $status"
    echo "Order status afterwards: $(curl -s -b "$JAR" "$BASE/sales-orders/$SO_ID" | grep -oE 'class="badge badge-[a-z]+">[^<]+' | head -1 | sed 's/.*>//')"
}

attempt "Sales" "sari.sales@ioms.test"
attempt "Warehouse Staff" "rudi.warehouse@ioms.test"

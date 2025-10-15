#!/usr/bin/env bash
set -euo pipefail
BASE="${BASE:-http://localhost:8081}"
CSRF="${CSRF:-}" # set via browser inspect or response if you expose it via API

hdr=(-H "Content-Type: application/json")
if [ -n "$CSRF" ]; then hdr+=(-H "X-CSRF-Token: $CSRF"); fi

echo "List users"
curl -sS -X POST "${BASE}/endpoints/admin/users/list.php" "${hdr[@]}" -d '{"page":1,"per_page":5}' | jq .

echo "Read user 1"
curl -sS "${BASE}/endpoints/admin/users/read.php?id=1" | jq . || true


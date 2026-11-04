#!/usr/bin/env bash
# dev/rebuild.sh — rebuild the LOCAL test database from scratch and reseed it.
# Safe: operates only on the local Homebrew MySQL `wiyule_motors` DB with fake data.
set -euo pipefail
cd "$(dirname "$0")"
MYSQL="${MYSQL:-/usr/local/bin/mysql}"
echo "── Rebuilding local wiyule_motors schema ──"
"$MYSQL" -uroot --connect-timeout=5 < schema.sql
echo "── Seeding fake data ──"
php seed.php
echo "✓ Local dev DB ready. Serve with:  php -S localhost:8000 -t .. "
echo "  Admin login → admin@wiyulemotors.com / devadmin123"

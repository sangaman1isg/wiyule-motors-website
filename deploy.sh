#!/usr/bin/env bash
# deploy.sh — thin wrapper around deploy.py (uses Python's built-in ftplib).
#
#   ./deploy.sh        # DRY RUN  — shows what would change, uploads nothing
#   ./deploy.sh --go   # actually upload changed files
#
# Credentials come from .deploy.env (gitignored). See .deploy.env.example.
set -euo pipefail
cd "$(dirname "$0")"
exec python3 deploy.py "$@"

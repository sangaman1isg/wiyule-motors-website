#!/usr/bin/env bash
# ---------------------------------------------------------------
# deploy.sh — mirror this repo to the live Wiyule Motors site over FTP.
#
# Credentials come from .deploy.env (gitignored — never committed).
# Copy .deploy.env.example to .deploy.env and fill it in first.
#
# Usage:
#   ./deploy.sh            # DRY RUN — shows what WOULD change, uploads nothing
#   ./deploy.sh --go       # actually upload changed files
# ---------------------------------------------------------------
set -euo pipefail
cd "$(dirname "$0")"

if [[ ! -f .deploy.env ]]; then
  echo "✗ .deploy.env not found. Copy .deploy.env.example to .deploy.env and fill it in." >&2
  exit 1
fi
# shellcheck disable=SC1091
source .deploy.env
: "${FTP_HOST:?set FTP_HOST in .deploy.env}"
: "${FTP_USER:?set FTP_USER in .deploy.env}"
: "${FTP_PASS:?set FTP_PASS in .deploy.env}"
: "${REMOTE_DIR:?set REMOTE_DIR in .deploy.env}"

if ! command -v lftp >/dev/null; then
  echo "✗ lftp is not installed. Install it with:  brew install lftp" >&2
  exit 1
fi

DRY="--dry-run"
MODE="DRY RUN (no files uploaded)"
if [[ "${1:-}" == "--go" ]]; then DRY=""; MODE="LIVE UPLOAD"; fi

echo "── Deploying to ${FTP_HOST}:${REMOTE_DIR}  [${MODE}] ──"

# mirror -R = reverse (local -> remote). --only-newer skips unchanged files.
# NOTE: secrets.php IS uploaded (server needs it). .git/IDE/OS junk is excluded.
lftp -u "${FTP_USER},${FTP_PASS}" "${FTP_HOST}" <<EOF
set ftp:ssl-allow true
set ftp:ssl-protect-data true
set ssl:verify-certificate no
set net:max-retries 2
set net:timeout 15
mirror -R --only-newer --delete=false --verbose ${DRY} \
  --exclude-glob .git* \
  --exclude-glob .idea/ \
  --exclude-glob .vscode/ \
  --exclude-glob .claude/ \
  --exclude-glob .DS_Store \
  --exclude-glob .deploy.env \
  --exclude-glob .deploy.env.example \
  --exclude-glob deploy.sh \
  --exclude-glob secrets.example.php \
  ./ "${REMOTE_DIR}"
bye
EOF

echo "✓ Done (${MODE})."
[[ -n "$DRY" ]] && echo "   Re-run with --go to actually upload."
exit 0

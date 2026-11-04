#!/usr/bin/env python3
"""
deploy.py — mirror this repo to the live Wiyule Motors site over FTP(S).

Uses only the Python standard library (ftplib) — nothing to install.
Credentials come from .deploy.env (gitignored). Copy .deploy.env.example
to .deploy.env and fill it in first.

Usage:
    python3 deploy.py          # DRY RUN  — shows what WOULD change, uploads nothing
    python3 deploy.py --go     # actually upload changed files
"""
import os
import sys
import ftplib
from pathlib import Path

ROOT = Path(__file__).resolve().parent

# Files/dirs never uploaded (dev/meta/local-only). secrets.php IS uploaded.
EXCLUDE_NAMES = {
    ".git", ".gitignore", ".idea", ".vscode", ".claude", ".DS_Store",
    ".deploy.env", ".deploy.env.example", "deploy.py", "deploy.sh",
    "secrets.example.php", "__MACOSX", "__pycache__",
}
# Also skip any file with one of these suffixes.
EXCLUDE_SUFFIX = {".pyc"}
# Source files we always re-upload (a same-size edit must still go up).
ALWAYS_UPLOAD_EXT = {
    ".php", ".html", ".htm", ".css", ".js", ".json", ".txt",
    ".sql", ".xml", ".svg", ".webmanifest", ".htaccess",
}


def load_env(path: Path) -> dict:
    if not path.exists():
        sys.exit("✗ .deploy.env not found. Copy .deploy.env.example to .deploy.env and fill it in.")
    env = {}
    for line in path.read_text().splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, v = line.split("=", 1)
        env[k.strip()] = v.strip()
    for key in ("FTP_HOST", "FTP_USER", "FTP_PASS", "REMOTE_DIR"):
        if not env.get(key):
            sys.exit(f"✗ {key} missing from .deploy.env")
    return env


def local_files():
    """Yield (local_path, rel_posix_path) for everything to consider uploading."""
    for dirpath, dirnames, filenames in os.walk(ROOT):
        dirnames[:] = [d for d in dirnames if d not in EXCLUDE_NAMES]
        for name in filenames:
            if name in EXCLUDE_NAMES or Path(name).suffix in EXCLUDE_SUFFIX:
                continue
            full = Path(dirpath) / name
            rel = full.relative_to(ROOT).as_posix()
            yield full, rel


def connect(env):
    host, user, pw = env["FTP_HOST"], env["FTP_USER"], env["FTP_PASS"]
    # Prefer encrypted FTPS; fall back to plain FTP if the server lacks TLS.
    try:
        ftp = ftplib.FTP_TLS(timeout=20)
        ftp.connect(host, 21)
        ftp.login(user, pw)
        ftp.prot_p()
        print(f"✓ Connected to {host} over encrypted FTPS as {user}")
        return ftp
    except ftplib.all_errors as e:
        print(f"⚠ FTPS unavailable ({e}); falling back to PLAIN FTP (password sent unencrypted).")
        ftp = ftplib.FTP(timeout=20)
        ftp.connect(host, 21)
        ftp.login(user, pw)
        print(f"✓ Connected to {host} over plain FTP as {user}")
        return ftp


def remote_size(ftp, path):
    try:
        return ftp.size(path)
    except ftplib.all_errors:
        return None


def ensure_remote_dir(ftp, base, rel_dir, made):
    """Create nested remote dirs under base as needed; cache what we've made."""
    parts = [p for p in rel_dir.split("/") if p and p != "."]
    cur = base
    for part in parts:
        cur = f"{cur}/{part}"
        if cur in made:
            continue
        try:
            ftp.mkd(cur)
        except ftplib.all_errors:
            pass  # already exists
        made.add(cur)


def main():
    go = "--go" in sys.argv[1:]
    env = load_env(ROOT / ".deploy.env")
    base = env["REMOTE_DIR"].rstrip("/")
    mode = "LIVE UPLOAD" if go else "DRY RUN (no files uploaded)"
    print(f"── Deploy to {env['FTP_HOST']}:{base}  [{mode}] ──")

    ftp = connect(env)
    try:
        try:
            ftp.cwd(base)
        except ftplib.all_errors as e:
            sys.exit(f"✗ Cannot open REMOTE_DIR '{base}': {e}\n"
                     f"  Fix REMOTE_DIR in .deploy.env (try 'public_html' or the full /domains/... path).")

        made_dirs = set()
        changed = skipped = 0
        for full, rel in sorted(local_files(), key=lambda t: t[1]):
            remote_path = f"{base}/{rel}"
            lsize = full.stat().st_size
            rsize = remote_size(ftp, remote_path)
            ext = full.suffix.lower()
            needs = (rsize is None) or (rsize != lsize) or (ext in ALWAYS_UPLOAD_EXT)
            if not needs:
                skipped += 1
                continue
            tag = "NEW" if rsize is None else "UPD"
            changed += 1
            if go:
                ensure_remote_dir(ftp, base, str(Path(rel).parent), made_dirs)
                with open(full, "rb") as f:
                    ftp.storbinary(f"STOR {remote_path}", f)
                print(f"  ↑ {tag}  {rel}")
            else:
                print(f"  • would upload ({tag})  {rel}")

        print(f"\n{'Uploaded' if go else 'Would upload'}: {changed} file(s); skipped (unchanged): {skipped}.")
        if not go:
            print("Re-run with --go to actually upload.")
    finally:
        try:
            ftp.quit()
        except ftplib.all_errors:
            pass


if __name__ == "__main__":
    main()

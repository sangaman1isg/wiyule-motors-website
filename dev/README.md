# Local development harness

Tools for running the Wiyule Motors admin panel locally against a **safe test
database** (fake data) so changes can be verified before deploying to production.

**This folder is never deployed** (excluded by `deploy.py`).

## Prerequisites
- A local MySQL (this repo was set up against Homebrew MySQL on `127.0.0.1:3306`,
  root / no password).
- PHP CLI (for the built-in dev server + seed script).

## One-time / rebuild
```bash
dev/rebuild.sh          # drops + recreates wiyule_motors, loads schema, seeds fake data
```

## Run the site locally
```bash
php -S localhost:8000 -t .      # from the repo root
```
Then open http://localhost:8000/ and http://localhost:8000/admin/

- **Admin login:** `admin@wiyulemotors.com` / `devadmin123`
- Customer accounts use password `customer123`

## How local DB selection works
`includes/db_connection.php` uses production creds from `secrets.php` **except**
when served by `php -S` (SAPI `cli-server`) *and* `includes/db.local.php` exists —
then it uses that local override. Both `db.local.php` and `secrets.php` are
gitignored and never deployed, so production is unaffected.

## Files
- `schema.sql` — table structure mirrored from the live DB + local migrations
  (e.g. `invoices.archived_at` for soft-delete/archive). **Structure only, no data.**
- `seed.php` — inserts synthetic customers, bookings, vehicles, invoices & quotations.
- `rebuild.sh` — convenience wrapper.

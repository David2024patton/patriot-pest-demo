# Skill: IDEMPOTENT_SEED_ENTRYPOINT

Purpose: make a containerized app self-heal its database on every boot with an idempotent seed, so volume resets, fresh clones, and redeploys never strand the app on an empty DB. Proven live on test.patriotpest.pro (25 pests + 3 blog posts re-seeded on boot, ORDER 2 close-out).

## Required Inputs

- Seed script (php bin/seed.php or equivalent) that is safe to re-run: INSERT OR IGNORE / upsert logic, no destructive deletes.
- Container entrypoint script and a Dockerfile that installs it.
- Seed copy that is clean per any standing content doctrine (zero em dashes) so every re-seed ships clean content.

## Build Blocks (modular, swap or scale independently)

1. Entrypoint script (POSIX sh, LF line endings - CRLF breaks the shebang): seed if the file exists, tolerate seed failure (echo + continue), then exec the real supervisor so signals/PID 1 semantics stay correct.
2. Dockerfile: COPY entrypoint, chmod +x, ENTRYPOINT (drop the old CMD), mkdir the DB dir, chmod the writable dirs.
3. Idempotent schema: CREATE TABLE IF NOT EXISTS everywhere; meta table tracks schema_version.
4. .gitignore/.dockerignore: exclude runtime DB files (including -wal/-shm) so containers never ship state.

## Expected Output

- Container boots, seeds when empty, skips harmlessly when populated, then starts php-fpm/nginx.
- Redeploy after a volume reset comes up with full seed data, no manual server access.

## Acceptance Verification Checklist

1. Fresh volume: boot logs show seed line, then supervisord starts; DB row counts = expected.
2. Second boot on populated DB: seed runs again, row counts unchanged (idempotent).
3. Health endpoint 200; DB-driven routes render rows (e.g. pest detail pages 200, not 404).
4. Entrypoint file is LF (git status shows no CRLF), executable bit set in image.

## Lessons

- "Seed once manually" does not survive container restarts; boot-time seeding closes the loop permanently.
- The entrypoint must exec the supervisor, not background it, or the container exits when the entrypoint returns.
- Guard the seed with `set -e` but let seed failure continue: an app that boots with old data beats a container that never starts.

Source: ORDER 2 close-out thread client-patriot-pest-control, PLANS/PATRIOT_PEST_DEPLOYMENT_RUNBOOK.md.

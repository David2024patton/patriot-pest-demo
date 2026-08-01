# Skill: VOLUME_PERMISSION_SELF_HEAL

## Purpose
Fix the classic Docker failure where an app boots fine but cannot write its mounted database volume: the Dockerfile chowns the dir at image build time, the mounted volume overrides that with host ownership, and the runtime worker user (not the image's named user) cannot write the DB file. Proven live on test.patriotpest.pro (ORDER C: every seeded login 500, zero OTP, until the entrypoint fix).

## Required Inputs
- Container entrypoint script (POSIX sh, LF line endings) and the Dockerfile that wires it (COPY + chmod +x + ENTRYPOINT).
- The actual runtime worker identity: check php-fpm www.conf / /proc/<pid>/status Uid inside a running container. On php:*-fpm-alpine the worker is www-data uid 82, NOT whatever user the Dockerfile created (e.g. ppc uid 1001). Verify, never assume.
- A faithful repro: a named volume mounted over the DB dir, first boot, and the same login probe you use on production.

## Build Blocks
1. `fix_database_perms()` shell function: chown -R <app-user> DIR + chmod -R 777 DIR, each `2>/dev/null || true` so a read-only mount cannot kill boot.
2. Pre-seed application of the fix (repairs an existing host-owned volume).
3. Post-seed re-application: the seed often runs as root and (re)creates the DB file as root:root 644, which no runtime worker can write. The post-seed re-apply is the load-bearing step.
4. Guard with `if [ "$(id -u)" -eq 0 ]` (entrypoint as root; supervisor drops privileges after).
5. Tolerant failure: echo + continue, then exec the real supervisor, so pages keep serving even when the mount is truly read-only.

## Expected Output
- Before: seeded identities POST /login 500, no OTP rows, "attempt to write a readonly database" in app logs.
- After: seeded identities 302 to the verify route, OTP rows written, codes in the mail log, read routes 200, second boot green with no duplicate rows.

## Acceptance Verification Checklist
1. Control image (pre-fix) on a fresh named volume reproduces production exactly.
2. Fix image on a fresh named volume: all seeded logins 302, OTP rows + mail-log codes present.
3. docker restart on the same volume: perms persist, seed idempotent (row counts unchanged).
4. Blob is LF, zero em dashes, shellcheck/syntax clean.
5. Entrypoint runs the fix BEFORE and AFTER the seed step.

## Lessons
- Image-time chown does not survive a mounted volume: host ownership wins at runtime.
- The seed-as-root file creation is the subtle second half: pre-seed chown alone is not enough.
- chmod 775 on the wrong owner is WORSE than 777: it can break previously-working reads (-wal/-shm creation).
- Production heals the moment a real deploy cycles the container; a paper no-op deploy (Dokploy without a registered server) does NOT. Verify the wire, not the deploy log.

Source: ORDER C close thread client-patriot-pest-control, WORK_LOGS/2026-08-01_ORDERC_PROOF_ATTEMPT.md, PR #8 (77a126a).

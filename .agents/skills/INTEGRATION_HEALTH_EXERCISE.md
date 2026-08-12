# Skill: INTEGRATION_HEALTH_EXERCISE

Purpose: exercise every third-party integration end to end through the app's own
transports (not just credential ping) and produce the API health matrix line for
an audit. Proven on Patriot Pest (ORDER D, 2026-08-01): Twilio SMS send
delivered, FieldRoutes sync 698 rows, Titan mail send verified by IMAP receipt.

## Required Inputs

- CREDENTIALS.md (or the vault) with the keys for each integration under test.
- The app's integration client(s) (for example app/Integrations/FieldRoutes.php,
  app/Auth/Mailer.php, bin/fr-sync-customers.php) so the exercise runs the SAME
  code path that production runs, not a bespoke test harness.
- Recipient strategy per channel (see the matrix below; never spam a third
  party - use owned numbers/mailboxes or the client's own test addresses).
- A scratch DB path when the sync writes local state (keep the repo pristine).

## Build Blocks (each is an independent, reusable check)

1. Twilio SMS: authenticate with the API key + secret (not just the main auth
   token) via GET /2010-04-01/Accounts/{SID}.json (expect 200, status=active),
   then POST a real Message from an owned long code to ANOTHER owned long code
   (WA -> AZ), poll GET /Messages/{SID}.json until status=delivered. Report
   HTTP 201 + queued -> delivered. This proves auth AND carrier delivery with
   zero third-party contact. Keep SMS_ENABLED=false in the deployed env; the
   exercise does not flip the flag.
2. FieldRoutes: run the app's own sync script (php bin/fr-sync-customers.php)
   with FIELDROUTES_* env vars injected and DB_PATH pointed at a scratch file.
   Expect per-district fetched/inserted/updated/skipped rows and
   meta.fr_last_sync stamped. WA + AZ both must succeed independently.
3. Titan mail: call the app's Mailer::send() (app/Auth/Mailer.php) over SSL
   465 with the production MAIL_* env vars to a mailbox the team can read back
   (agent_zero@itak.live in this org), then verify RECEIPT over IMAP (search
   Subject) - SMTP 250 alone is not delivery proof.
4. Matrix line: one row per integration: result (PASS/FAIL/FLAG), evidence
   (HTTP statuses, row counts, message SIDs), and any open gate (for example
   A2P 10DLC pending, SMS_ENABLED=false). Deliver to the principal per the
   standing order (here: email the matrix to david@itak.live).

## Expected Output

- A table of PASS/FAIL/FLAG rows with HTTP-level evidence for each integration.
- No secrets printed (redact SIDs are fine; tokens never).
- A WORK_LOGS entry and (when a novel workflow) a skill blueprint committed to
  .agents/skills with a Skillz SITREP.

## Hazards

- Never send to a number not owned by the account/client - use the owned-number
  self-loop.
- Never print AUTH_TOKEN/API_SECRET/MAIL_PASSWORD to logs or chat.
- SMTP 250 does not mean the recipient got the mail; IMAP-verify receipt.
- A bare credential ping (HTTP 200) does not prove the integration; the app's
  own client must run against the live API.

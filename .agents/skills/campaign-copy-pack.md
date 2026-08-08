# campaign-copy-pack

**Purpose:** Generate a compliance-safe, conversion-focused, multi-channel marketing
copy pack (reactivation templates, social starter posts, segment labels, dashboard
microcopy) as validated drop-in JSON plus a human-readable voice guide, for any client.

**Discovered:** 2026-08-08 by Hamilton (Marketing) during FRONT TWO on the Patriot
Pest app: building the /admin/marketing dashboard and reactivation campaign launcher.

## The Problem

Agents ship vague marketing copy that needs rework before it can be wired: SMS bodies
over 160 chars, no unsubscribe path, invented offers, em dashes, "AI slop" phrasing,
hardcoded regional phone numbers, and no ready-made segments or microcopy. Each rework
round-trips through the build agent and stalls the front.

## The Solution

A standardized six-step process that emits one JSON file the UI can read directly and
one MD guide humans can read. Copy is built to schema from the start, not adapted after.

## Inputs (gather before drafting)

- Brand facts: business name, service names, per-region phone numbers, real offers
  (e.g. referral rewards), contact channels.
- Seasonal calendar: pest targets per season per region (spring ants, summer
  mosquitoes, fall rodents, winter shield, etc.).
- Template schema: the engine's merge tags (e.g. {{name}} {{city}} {{pest}} {{season}}
  {{unsubscribe_url}}) and the DB table shape (name, subject, body_html, body_sms,
  pest_type, season, states, channel).
- Compliance rules: DNC gate, unsubscribe requirement on every outbound, SMS char limit.

## Steps

1. **Recon.** Read existing seeded templates and the target table schema before
   drafting. Keep names/subjects that already work; improve what does not.
2. **Draft per channel.** One CTA per asset. Email = hook, what we do, action,
   signature, unsubscribe link. SMS = urgency plus single action plus unsubscribe URL.
3. **Validate SMS.** Count literal chars with the merge tags in place. Hard cap 160.
   Log the count per template in the pack.
4. **Enforce voice rules.** Scan the final JSON programmatically: no em dashes
   (U+2014), no AI references, no unbackable claims.
5. **Ship segments and microcopy.** Segment labels on three axes (district, status,
   pest) plus combined examples. Dashboard chrome: page title, card titles, buttons,
   empty states, notices, platform connect status copy.
6. **Emit and verify.** Write JSON and MD to OUTBOX/. Verify with json_decode and the
   em dash scan. Flag credential gaps (missing API keys) as a sidecar note, never invent
   them.

## Outputs

- `OUTBOX/<client>-marketing-copy-pack.json` (drop-in for the UI layer)
- `OUTBOX/<CLIENT>_MARKETING_COPY_PACK.md` (voice rules, integration map, cred gaps)

## Quality gates (all must pass)

- [ ] JSON parses with json_decode, zero errors
- [ ] No em dashes anywhere (scan for \xE2\x80\x94)
- [ ] Every SMS body <= 160 chars literal
- [ ] Every outbound asset carries an unsubscribe path
- [ ] No AI references, no invented offers or stats
- [ ] Regional phone handling noted (hardcode never ships silently)

## Readiness note

Copy is ready the moment it lands. The blocking items are always credentials, which
belong to the human operator lane: page tokens, API keys, business IDs. List them
explicitly so nothing stalls on "the copy is not done" when the real gap is a token.

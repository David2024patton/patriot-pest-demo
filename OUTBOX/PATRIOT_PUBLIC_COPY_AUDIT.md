# Patriot Pest Public Copy Audit

**Owner:** Hamilton (Marketing) | **Date:** 2026-08-08 | **Status:** ready to apply
**Audited against:** feat/production-foundation (HEAD c52fe44, what is deployed at demo.patriotpest.pro)
**Scope:** every public-facing page template plus user-visible meta copy and JS strings.
**Companion:** OUTBOX/PATRIOT_PUBLIC_COPY_PATCH.json (machine-applicable find/replace pairs, 62 patches, validated against c52fe44).

## Directive (from David)

1. Zero em dashes. Not one. Commas, colons, or parentheses only.
2. No fluff. Plain language. People do not talk in corporate buzzwords.
3. A pest control company run by a combat veteran should sound like one.

## Executive summary

Counted **45 user-visible em dashes** across the public templates, controllers, and JS.
Every one has a replacement in the patch file (62 find/replace pairs total; some patches also rewrite fluff around the dashes). Beyond the dashes, the copy has a
distinct AI-slop signature: triple-word virtue stacking ("dedication, precision, and
integrity"), "uncompromising excellence", "protecting what matters most", and cadence
that no human speaks. The worst offender is the founder quote on the homepage, which
is a textbook AI-generated testimonial.

**What I kept:** the tactical visual theme (threat files, mission brief, HUD labels,
SEC. 01 headers). That is the brand David approved. What I changed is the spoken copy,
so it reads like a person instead of a script.

## Em dash inventory (user-visible only)

| File | Line | Current | Replacement |
|------|------|---------|-------------|
| layouts/main.php | footer | `(509) 471-5767 — WASHINGTON` | `(509) 471-5767 · WASHINGTON` |
| pages/home.php | hud | `117.4260° W — SPOKANE, WA` | `117.4260° W / SPOKANE, WA` |
| pages/home.php | hero-sub | `family and pets — with same-day service` | `family and pets. Same-day service` |
| pages/home.php | dossier | `ACTIVE — SAME-DAY RESPONSE` | `ACTIVE: SAME-DAY RESPONSE` |
| pages/home.php | operator | `Skyler Rose</b> — bringing military discipline...` | full rewrite (see below) |
| pages/home.php | quote | `eliminating pests — we're protecting what matters most` | full rewrite (see below) |
| pages/home.php | gold | `Our best-value plan — continuous year-round defense` | `Our best value. Year-round coverage...` |
| pages/home.php | platinum | `Maximum coverage — perimeter, interior...` | `Everything in Gold, plus interior...` |
| pages/home.php | promise | `insured — with eco-friendly, low-toxicity products` | `insured. Products are low-toxicity...` |
| pages/home.php | final CTA | `call the line — same-day service available` | `Call the line or send a request online.` |
| pages/about.php | story | `eliminating pests — we're protecting what matters most` | full rewrite (see below) |
| pages/about.php | dossier | `ACTIVE — SAME-DAY RESPONSE` | `ACTIVE: SAME-DAY RESPONSE` |
| pages/about.php | precision | `at the source — not just the symptoms` | `at the source, not just the bugs you see` |
| pages/areas.php | lead | `below — if we're not listed` | `below. Not listed? Call us.` |
| pages/blog-index.php | lead | `technicians — written for homeowners` | `technicians. Written for homeowners` |
| pages/contact.php | lead | `send the form — we respond within` | `send the form. We respond within` |
| pages/faqs.php | x4 | `Yes — same-day`, `fees — get`, `service — often`, `Reach out</a> — we're` | comma, period, or colon |
| pages/help.php | account | `passwordless — we email a secure code` | `No passwords. We email you a secure code` |
| pages/legal.php | x2 | `directly — such as...information — when` | commas |
| pages/legal.php | x2 | `this site — including...images — is the property` | commas |
| pages/login.php | x3 | `plan — everything`, `aboard — enter`, `on file — we'll` | periods |
| pages/pest.php | treat | `at its source — not just the insects` | `at its source, not just the bugs` |
| pages/pest.php | wildlife | `can't return — with cleanup` | `can't return. We clean up...` |
| AuthController | meta | `No password — we email you a code` | `No password. We email you a code` |
| PageController | meta x5 | `About Us — Veteran-Owned`, `Services — Every Pest`, `Pricing & Plans — Transparent`, `Areas — WA, ID`, `Contact Us — Free Quotes`, `Referral — Earn $25` | `:` separator |
| BlogController | meta | `Blog & Tips — Seasonal Guides` | `Blog & Tips: Seasonal Guides` |
| main.js | ticker | `SPOKANE — SAME-DAY AVAILABLE` | `SPOKANE / SAME-DAY AVAILABLE` |

## Fluff rewrites (the lines that sound like AI)

### Homepage hero sub
**Before:** "Military-precision pest control for homes and businesses across Washington, Idaho, Oregon & Arizona. Eco-friendly treatments safe for your family and pets — with same-day service when it can't wait."
**After:** "Pest control for homes and businesses across Washington, Idaho, Oregon & Arizona. Products are safe for your family and pets. Same-day service when it can't wait."

### Homepage threat board lead
**Before:** "X hostile categories operate in your region right now. Each one is identified, tracked, and eliminated with eco-friendly, family-safe treatments."
**After:** "X types of pests are active in your region right now. We know each one and treat them with products that are safe for your family."

### Homepage operator section
**Before:** "Patriot Pest Control was founded by U.S. Military Veteran Skyler Rose — bringing military discipline, integrity, and uncompromising excellence to pest control across four states. Over a decade of field experience. Thousands of homes and businesses protected."
**After:** "Patriot Pest Control was founded by U.S. military veteran Skyler Rose. He runs the company the way he was trained: on time, honest, and thorough. Over a decade in the field, thousands of homes and businesses protected."

### Homepage founder quote (worst offender)
**Before:** "After serving our country, I founded Patriot Pest Control to continue serving American families and businesses with the same dedication, precision, and integrity I learned in the military. We're not just eliminating pests — we're protecting what matters most."
**After:** "I served this country, and I built this company on the same rules: show up when you say you will, do the job right, and don't sell people things they don't need. We get rid of pests, and we treat your home with respect while we're in it."

### About page
**Before:** "Veteran-owned. Mission-driven." / "to bring military discipline, integrity, and uncompromising excellence..."
**After:** "Veteran-owned. Done right." / "to bring military standards to pest control across four states: on time, honest, and thorough."

### Plan cards (home + prices)
**Before:** "A single targeted strike on an active infestation. Fast, focused, guaranteed."
**After:** "One visit for an active infestation. Fast, thorough, guaranteed."

**Before:** "Scheduled seasonal treatments that keep the perimeter secure through every pest season."
**After:** "Quarterly treatments that keep pests out through every season."

**Before:** "Our best-value plan — continuous year-round defense, priority scheduling, and free re-treatments."
**After:** "Our best value. Year-round coverage, priority scheduling, and free re-treatments."

**Before:** "Maximum coverage — perimeter, interior, and specialty pests, every angle locked down."
**After:** "Everything in Gold, plus interior and specialty pest coverage."

### Guarantee section
**Before:** "We stand behind every mission." / "No hassles, no excuses. Licensed, bonded, and insured — with eco-friendly, low-toxicity products safe for kids, pets, and the environment."
**After:** "We stand behind every treatment." / "No hassles, no excuses. Licensed, bonded, and insured. Products are low-toxicity and safe for kids, pets, and the environment."

### Final CTA
**Before:** "Book online in minutes or call the line — same-day service available across all four states. Free quotes, transparent pricing, zero hidden fees."
**After:** "Call the line or send a request online. Same-day service is available across all four states. Free quotes, no hidden fees."

### Login page
**Before:** "Your appointments, your technician, your plan — everything about your pest-free home, right here." / "Welcome aboard — enter your details..."
**After:** "Your appointments, your technician, and your plan, all in one place." / "New here? Enter your details and we'll get you set up in seconds."

## Flagged for David (decision needed, no patch applied)

1. **Homepage testimonials are fabricated.** Three "VERIFIED" reviews with report
   numbers (#WA-0117, #WA-0242, #ID-0089) and initials-only names. There is no
   evidence these are real customers. Fake reviews are an FTC exposure and a trust
   risk. Recommended: replace with real Google/Facebook reviews, or strip the
   "VERIFIED" badge and report numbers until real ones exist.
2. **"Book online" claims.** No online booking exists; the contact form is a quote
   request. I changed the copy to "send a request online". If real booking is coming,
   build it, then the copy can say "book".
3. **"24/7 line" claim.** Contact page hours are Mon-Fri 9a-5p, Sat-Sun 10a-4p, and
   the site says "24/7 line". Confirm there is a real after-hours line before keeping
   the claim.
4. **"Same-day service across all four states."** Including OR (2 cities) and AZ
   (Phoenix). Confirm real same-day coverage in those markets before the hero keeps
   claiming it.
5. **"Over a decade" and "thousands of homes and businesses".** Confirm the numbers
   or soften them. Claims we cannot back get cut.
6. **Internal app placeholders.** Staff/customer tables render "—" for missing values
   (account.php, staff templates). Not public-facing, but for full em-dash compliance
   they should become "N/A". Listed for Engineering, not part of this public audit.

## Validation deltas (added 2026-08-08, byte-verified)

The first byte-level sweep after assembly found 9 more user-visible em dashes the
original pass missed. All are now patched and verified:

| File | Line | Current | Replacement |
|------|------|---------|-------------|
| pages/socials.php | FB card | `@pestmgtpros — reviews...` | `@pestmgtpros: reviews...` |
| pages/socials.php | IG card | `@patriot_pest — behind the scenes...` | `@patriot_pest: behind the scenes...` |
| errors/403.php | title | `403 — Restricted` | `403 Restricted` |
| errors/404.php | title | `404 — Target Not Found` | `404 Target Not Found` |
| cost/index.php | sub | `No fluff, no hidden fees — just the raw numbers.` | `No hidden fees, no surprises. Just the raw numbers.` |
| cost/index.php | grand total | `$75,000 — $150,000` | `$75,000 to $150,000` |
| cost/index.php | timeline | `</span> — Full agency team deployment.` | `</span>. Full agency team deployment.` |
| cost/assets/js/cost.js | runtime | `' \u2014 '` in renderTotals | `' to '` (JS re-injected the dash after 2s) |
| PageController | flash | `Thanks — we received your message...` | `Thanks. We received your message...` |

**"No fluff" catch:** David's directive banned the phrase "no fluff". The cost page
subhead said exactly that ("No fluff, no hidden fees"). Rewritten to "No hidden fees,
no surprises. Just the raw numbers."

**Still out of scope (Engineering lane, already listed below):** authenticated app
shell (templates/account.php, admin/*, dashboard/*, staff/*, layouts/app.php,
public/assets/app.js) and staff flash strings in StaffController still contain em
dashes; they are behind login, not public-facing.

## How to apply

The JSON patch file (62 pairs, 22 files) is keyed by file with exact find/replace
strings from the source. Apply in the copy-cleanup commit of the responsive retrofit. Do not hand-retranslate;
use the exact strings so nothing drifts. Docblock comments were left untouched; they
are not user-facing.

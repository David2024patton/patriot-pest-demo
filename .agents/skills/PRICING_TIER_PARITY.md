# Skill: PRICING_TIER_PARITY

Purpose: lock pricing copy from a production site into an app or test site verbatim, dollar for dollar, when an owner rules that the app must publish the same tiers as the main website. Proven in production use on the Patriot Pest test site (ORDER B, four tiers: Bronze $35.95, Silver $59.99, Gold $79.99, Platinum $95.94).

## Required Inputs

- Owner ruling that the app publishes the same pricing as the main site (tier names, amounts, tags).
- The production pricing source. Prefer the source repo/HTML the main site is built from (for example REPOS/patriot-pest-scrollytelling/prices.html) over scraping a live URL: the live site may sit behind a bot check, but the source HTML is exact and grep-able.
- The current app template that needs replacing (for example templates/pages/prices.php).

## Build Blocks (modular, swap or scale independently)

1. Tier data block: one table of plan name, tag line, monthly price, description, includes list, bonus list, excludes list, most-popular flag. Source of truth for every downstream artifact.
2. Shared badges: No Hidden Fees / 90-Day Warranty / Free Re-Treatments (or per brand).
3. Header copy: H1, sub line. Strip em dashes (standing doctrine) with a colon or period so the locked copy still reads clean.
4. Compare table: feature rows x tier columns, with a Gold/featured highlight and a recommended note line.
5. CTA band: keep existing structure, swap copy only, quote stays secondary per the ruling.
6. Cross-lane handoff: copy lock to the builder (publish tiers), the industry specialist (verify tier for tier), and the data scientist (re-scope any audit win note that claimed the app was quote-based).

## Expected Output

- A locked copy spec file (YAML frontmatter, ALL_CAPS name) in PLANS that the builder implements verbatim.
- Deployed /prices on the test site showing every tier with exact main-site amounts, zero em dashes.
- Verification chain: builder publishes, specialist verifies dollar for dollar, data scientist re-scopes the audit note, validator re-runs clean.

## Acceptance Verification Checklist (run on the live wire, not on faith)

1. All tier amounts present on the live /prices page and match the main site exactly ($35.95 / $59.99 / $79.99 / $95.94 in the Patriot case).
2. Plan names and tag lines verbatim (Bronze Exterior-Only Protection, Silver Interior + Exterior, Gold Priority Interior & Exterior, Platinum Full Coverage).
3. Includes / bonus / excludes lists match the locked spec.
4. Gold flagged Most Popular, compare table rows match, recommended note present.
5. U+2014 count on the rendered /prices page = 0.
6. Quote CTA present but secondary.

## Lessons

- Never scrape a live URL for canonical pricing when the source repo exists: the live site can be behind a bot-check challenge page (it was here), while the source HTML is exact.
- Watch for broken search wrappers in the shell environment: if a grep tool exits nonzero with zero output on files you can read, verify with a second tool before trusting a "not found" result.
- Keep the pricing source of truth in one table so the builder, specialist, and data scientist all diff against the same numbers.

Source: PLANS/PATRIOT_PEST_CONTROL/PHASE_3_CONVERSION/PRICING_TIERS_LOCKED.md, ORDER B thread client-patriot-pest-control.

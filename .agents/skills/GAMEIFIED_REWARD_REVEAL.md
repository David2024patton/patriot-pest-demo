# Skill: GAMEIFIED_REWARD_REVEAL

Purpose: build a hidden, gameified reward element (Easter egg) on any site that converts clicks into a branded reveal moment and a tracked conversion event. Proven live in production use on the Patriot Pest test site ($25 jackpot reveal, ORDER 1).

## Required Inputs

- Reward value and payout type (promo code, credit, referral). Example: $25 off via promo code PATRIOT25.
- Reveal copy in brand tone, zero em dashes (standing doctrine).
- Target page(s) and template file where the element mounts.

## Build Blocks (modular, swap or scale independently)

1. Session counter: sessionStorage key, increments per click, survives navigation. Reset on session end by design.
2. Toast ladder: fixed copy per click count before the reveal. Example: click 3 "SIGNAL ACQUIRED", click 4 "TARGET COMPROMISED", click 5 jackpot.
3. Jackpot config object: one JS object holding type, code, terms, per-click strings, relocation and already-claimed copy. Swapping payout type changes only this object.
4. Reveal: modal or toast on the final click, brand burst, then auto-relocate the element to a different page section and reset the counter.
5. Session guard: one reward per session; later cycles show the already-claimed variant.
6. Settings toggle: on/off per doctrine.
7. Event firing: reserved analytics event names (easter_egg_click, easter_egg_reveal) so the game instruments the retention funnel.
8. Safety: mobile touch targets, fixed or absolute positioning, z-index above content, zero layout breakage.

## Expected Output

- Deployed hidden element with idle motion, click ladder, reveal with reward copy and code, relocation, once-per-session guard, settings toggle.
- Verified live: animates, 5 clicks reveals and relocates, counter survives navigation, toggle works.
- Zero em dashes in all copy and UI text.

## Acceptance Verification Checklist (run on the live wire, not on faith)

1. Rendered home HTML carries egg markers (easter, jackpot, PATRIOT25, pp_egg keys). Grep the live page, expect hits.
2. The shipped JS/template contains the locked config object and every string verbatim.
3. The jackpot modal h2 textContent equals the full payline string as one contiguous run (styling spans live inside the heading, never splitting DOM text).
4. sessionStorage keys exist (pp_egg_hits counter, pp_egg_claimed guard) and the counter survives navigation.
5. Reserved analytics events fire: easter_egg_click per hit, easter_egg_reveal on jackpot. Beacon endpoints accept them (POST /api/track/event).
6. Beacon endpoint live: POST /api/track/view and session_end return non-error; GET /api/retention/summary returns the summary shape.
7. Settings toggle present and functional (on/off per doctrine).
8. U+2014 count on live pages = 0. Copy reads clean.
9. Mobile: touch target sized, no layout breakage, z-index above content.

## Lessons

- Lock mechanics first, payout second: the build runs while the payout ruling is pending.
- Keep all strings in one config object so a payout change is a one-line swap.
- Fire analytics events from day one; never retrofit instrumentation.
- Put the jackpot payline in the DOM as one text run; style spans go inside the heading so acceptance is a single textContent assert.

Source: PLANS/PATRIOT_PEST_MARKETING/CAMPAIGNS/ALWAYS_ON/EASTER_EGG_PAYOUT_STRINGS.md, ORDER 1 thread client-patriot-pest-control.

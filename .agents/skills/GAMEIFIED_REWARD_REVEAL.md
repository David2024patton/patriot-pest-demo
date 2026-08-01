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

## Lessons

- Lock mechanics first, payout second: the build runs while the payout ruling is pending.
- Keep all strings in one config object so a payout change is a one-line swap.
- Fire analytics events from day one; never retrofit instrumentation.

Source: PLANS/PATRIOT_PEST_MARKETING/CAMPAIGNS/ALWAYS_ON/EASTER_EGG_PAYOUT_STRINGS.md, ORDER 1 thread client-patriot-pest-control.

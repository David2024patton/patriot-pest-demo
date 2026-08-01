# Skill: COMPETITOR_CRAWL_PIPELINE

Purpose: crawl the top 10+ competitor websites through a locally hosted
Crawl4AI container before ANY audit report, and ship a structured, validated
dossier (pricing, service tiers, guarantees, CTAs, SEO meta, em-dash sweep
hooks) so nothing is missed and the report can show where the client does it
better. Proven on the Patriot Pest 40-domain crawl (2026-08-01): 42 entries
COMP-01..COMP-42, WA + AZ tracks, validator green on the re-scoped dossier.

## Required Inputs

- A curated competitor list (>= 10 domains, split by market track when the
  client serves multiple markets). Each entry needs: company, domain, market
  track, and 1-3 candidate URLs (homepage plus the pricing/pest pages).
- The local Crawl4AI container (unclecode/crawl4ai:latest, port 11235).
  Verify first: `docker ps` shows crawl4ai Up, and GET /health returns 200.
- Credentials from CREDENTIALS.md: CRAWL4AI_API_TOKEN (Bearer) and
  CRAWL4AI_ENDPOINT (http://127.0.0.1:11235).
- The validator tool (REPOS/pp-audit-report/tools/validate_competitors.py) and
  the dossier contract it enforces.

## Build Blocks (modular, swap or scale independently)

1. List curation + purge loop. Curate the master list, then verify every
   domain LIVE before crawling. Sanity-pass the domains (whois/parked checks,
   market fit). Parked domains (GoDaddy/HugeDomains for-sale), ad-server
   login shells (MAZMO), and out-of-market operators (a Utah pest firm on the
   WA track) must be purged and replaced BEFORE the crawl, or the dossier
   fails the honest-crawl floor.
2. Crawl master (crawl_master.py). POST {urls:[url]} to
   {endpoint}/crawl with Authorization: Bearer {token}, timeout 180s.
   - Save each success as raw JSON per domain in
     RESEARCH/PP_MARKET_INTEL/COMPETITORS/CRAWL_RAW/{domain}.{STAMP}.json.
   - Write a MASTER_{STAMP}.json summary: stamp, total, ok count, per-domain
     {ok, status_code, title, md_len, market, company}.
   - Track blocked entries separately (documented manual fallback: real
     operator but crawl-blocked stays, with crawl_error + manual fallback).
3. Retry loop (crawl_master_retry.py). For domains that 500 or fail on the
   first URL, retry alternate URLs (www prefix, /prices, /pest-control, the
   market-specific path). Retries recovered varsity/insectek/mantis/fox/
   scorpion/altapest on the proven run.
4. Structured extraction contract. From each raw markdown build evidence
   arrays with {text, source_url, evidence_method:"crawl"} for: services,
   pricing, guarantees, CTAs, and the em-dash sweep hook (count U+2014 on
   their pricing pages). Include digital_presence summary and per-domain
   threat_level.
5. Dossier assembly. Ship data/competitors.json shaped
   {meta, competitors:[{id:COMP-NN, name, domain, market, threat_level,
   status, crawl_error, evidence, digital_presence}], gaps, wins}. Every
   evidence item MUST carry source_url + evidence_method. Set meta:
   wa_count, az_count, domain_count, validation_status=pending.
6. Validator gate. Run tools/validate_competitors.py; the six rules are:
   verdict validated, track coverage, crawl-ok floor (>= 75-80% honest),
   claim coverage, source-domain integrity, no fabrication. Fix the dossier
   until all six PASS, then flip validation_status and push.

## Expected Output

- RESEARCH/PP_MARKET_INTEL/COMPETITORS/CRAWL_RAW/ raw JSON + MASTER stamps.
- data/competitors.json dossier: every competitor with crawl-sourced evidence
  (source_url + evidence_method), per-track counts, gaps and wins sections.
- Validator exit 0 / all six rules PASS on the shipped file.
- The AUDIT REPORT STANDARD: every audit report includes a competitor-crawl
  section summarizing the crawl (domains, method, date) so no report ships
  without the crawler having run.

## Hazards

- Never trust a transport-level 200 as proof the site is a competitor:
  parked domains and login shells pass TCP but fail content. The content
  crawl is the truth layer (caught 3 parked + 1 wrong-market on the proven
  run that every transport check missed).
- Do not fabricate. A blocked/error entry keeps its crawl_error and manual
  fallback marker; dropping it silently fails rule 5.
- Crawl4AI is rate-sensitive: 1s delay between URLs, retry with alternates,
  and keep the container healthy (auto-restart policy).
- No em dashes in the shipped skill or report copy; sweep U+2014 across the
  crawl evidence too, because competitors' pages are the exact place an em
  dash can leak into a client deliverable.

# Skill: BLOG_MIGRATION_RSS

Purpose: migrate a content corpus from a legacy site into a new DB-driven app and stand up a fresh RSS 2.0 feed from the app's own table, so the new site reaches content parity without depending on the legacy site's broken or empty article pages. Proven live on test.patriotpest.pro (81 articles migrated, RSS restored, 2026-08-01).

## Required Inputs

- Legacy blog index HTML (parseable cards: slug, title, excerpt, category, pest tag, date) and per-article JSON-LD (datePublished, dateModified) when article pages exist.
- New app's posts table schema and its seed conventions (idempotent skip-if-slug-exists inserts).
- Router that is first-match-wins, and the new site's canonical base URL.

## Build Blocks (modular, swap or scale independently)

1. Corpus extractor (scrape.py pattern): fetch index, regex-parse cards, fetch each article page for meta/JSON-LD enrichment, emit normalized JSON with telemetry (counts per stage, missing-field summary). Save the corpus into the repo (bin/data/blog-corpus.json) so the importer runs anywhere, including at container boot.
2. Idempotent importer (bin/import-blog.php pattern): seed.php conventions, skip existing slugs, map legacy tags to the new photo library where they match (unmatched tags stay NULL), derive season from publish month, preserve original published_at/date_modified, generate body_html from real title/excerpt in the seed's short paragraph + list style when the legacy body does not exist. Print insert/skip/generic counts and exit non-zero on errors.
3. RSS endpoint: controller method (rss()) that selects published posts newest first, a template emitting RSS 2.0 with perma-link GUIDs (link == guid), XML-escaped content (ENT_XML1), atom self-link, and Content-Type application/rss+xml; charset=UTF-8.
4. Route ordering: register the literal feed path (/blogs/rss.xml) BEFORE the {slug} catch-all (first-match-wins router). Add the legacy feed path (/blog/rss.xml) as an alias so old subscriptions keep resolving.
5. Boot-time self-heal: call the importer from the container entrypoint after the seed, guarded and tolerant (failure continues boot), so redeploys and volume resets converge on full content.

## Expected Output

- New site serves all migrated posts under the legacy slugs (SEO continuity) with richer body content than the legacy shell pages.
- /blogs/rss.xml (and legacy alias) return 200, valid well-formed RSS with one item per published post.
- Post count = previous seeds + migrated corpus; re-running the importer inserts 0 and skips everything.

## Acceptance Verification Checklist

1. Run importer twice: first run inserts N, second run skips all N (idempotent).
2. /blogs renders every post; sample legacy slugs return 200.
3. /blogs/rss.xml and alias return 200 with Content-Type application/rss+xml.
4. Feed parses as well-formed XML; item count == published post count; link == guid for every item; no unescaped entities.
5. Production wire check after merge + auto-deploy: same 200s on the live test domain.

## Lessons

- Legacy article pages may be metadata-only shells; scrape the JSON-LD for dates and be explicit in the report that the new site is a content superset, not a byte copy.
- The PHP built-in dev server 404s .xml paths (treats them as static files); verify feed routes through the app's real router (php -S ... public/index.php or nginx try_files) before shipping.
- Emit RSS GUIDs as the permanent article URL so feed readers dedupe correctly across rebuilds.

Source: ORDER 2 close-out thread client-patriot-pest-control, PLANS/PATRIOT_PEST_DEPLOYMENT_RUNBOOK.md.

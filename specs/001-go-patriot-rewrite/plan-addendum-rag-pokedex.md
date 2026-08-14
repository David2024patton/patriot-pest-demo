## Goal
Complete SurrealDB graph-RAG + Ask AI + tech PWA/GPS + internal messaging/notifications + OCR Pokedex on top of the all-in-one hot stop (103 routes, 7-channel inbox+email, kanban shared, supply, workflows n8n/Zapier) — all editable via `admin/settings`.

## Success Criteria
- `POST /api/knowledge/ask` returns RAG answer with citations from `kb_nodes` graph+vector in <800ms
- Ask AI FAB middle-right streams answer, toggle in `admin/knowledge` controls memory `kb_memories`
- `GET /tech/routes?tech_id=me` shows GPS `tech_locations`, `POST /api/customers/{id}/notes` stores GPS, offline `sw.js`
- `GET /api/notifications/stream` SSE bell + `@mentions` work; `GET /tech/scan` → `POST /api/ai/scan` returns Pokedex card `{type,seasonality,treatment,natural}` via local `AI_BASE_URL` or external fallback

## Context And Current Facts
- SurrealDB chosen (C1) with 41 records already; need vector `MTREE` + graph `RELATE`
- Existing `pest_photos`, `posts`, `cases` are KB sources; `portal`/`board` needs notifications; techs need PWA routes

## Key Decisions
- Surreal vector `MTREE` on `kb_nodes.embedding` + `RELATE kb_edges` for graph traversal (vs external Qdrant — keep single DB)
- Ask AI as `internal/view/components/ask_ai.templ` FAB, SSE streamed, per-workspace memory editable
- Tech PWA `sw.js` offline queue for notes/scan; GPS `tech_locations` separate table for live map
- OCR `tesseract` local + `vision` model via `AI_BASE_URL` OpenAI-compatible, `AI_PROVIDER` switch in settings

## Recommended Approach
Migrate `kb_*` + `tech_*` + `notifications` + `pest_identifications` in `migrations/*.surql`, wire `modules/knowledge` + `tech` + `messaging` + `ai`, settings `admin/knowledge` for `AI_BASE_URL/MODEL/KEY` + `kb_memories`

## Work Plan
- T0 `research.md` RAG/Pokedex spike; T7 knowledge; T8 tech/messaging/pokedex; T9 hardening

## Validation Plan
- `curl -X POST /api/knowledge/ask -d '{"q":"wasps in Spokane"}'` → citation IDs
- `open /tech/scan` upload wasp.jpg → Pokedex card shows seasonality Jul-Sep, treatment Bifenthrin, natural peppermint

## Risks / Rollback
- Vector index build cost — batch embed background worker; rollback disable RAG via flag

## Open Questions
None

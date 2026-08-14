# Research: 001-go-patriot-rewrite

**Date**: 2026-08-13

## SurrealDB Go SDK
- SDK: `github.com/surrealdb/surrealdb.go` — supports `ws://` remote and embedded `mem://`/`rocksdb://`. Use `ws://surreal:8000` in Dokploy, `mem://` for tests via testcontainers `surrealdb/surrealdb:latest`.
- Migrations: SurrealQL `DEFINE TABLE`, `DEFINE FIELD`, `DEFINE INDEX` in `migrations/*.surql`; run on `db.Open` via `client.Query("DEFINE ... IF NOT EXISTS")`.
- 41 tables → 41 `DEFINE TABLE SCHEMAFULL` + indexes `key_prefix unique`, `email NOCASE`, `fingerprint`.

## FieldRoutes Supply
- FieldRoutes API docs: customers/appointments/invoices/subscriptions well-documented; inventory `supplies` not always exposed. Spike: `GET /api/supplies` if 404 → native `supplies`+`supply_moves` table, `GET /admin/supplies` CRUD + low-stock `qty < reorder_point` alert, link to kanban cards via `card_id`.

## Kanbn/Trello Parity
- `kanbn` (kan) is file-based kanban; mapping: `boards` (root), `columns` (lists with WIP), `cards` (title, description.md, labels[], status, position, assignee, due). Extend with `checklist[]`, `cover`, `customer_id|case_id`, `activity[]` → `case_timeline`. MCP `kanban:read/write` same verbs.

## Email Inbox
- Titan `smtp.titan.email` already in .env; IMAP `imap.titan.email:993` — use `go-imap` + OAuth for Gmail. Store `email_threads` by `Message-ID` threading, `email_messages` per fetch, show in unified `GET /staff/messages` with email logo badge.

## Competitive Gap (top 20)
- Orkin/Terminix/Ehrlich/Viking/Massey/Modern/Truly Nolen/Arrow/Aptive/Bulwark/Hawx/Plunkett's/Cook's/Turner/Home Paramount/Dodson/HiCare/Sanix — gaps to close: unified social inbox, Surreal relations, kanban, supply, GEO llms.txt — tracked in `docs/competitor-matrix.md`.

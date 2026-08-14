# Patriot Pest Go Constitution

## Core Principles

### I. Library-First, Module-Isolation (NON-NEGOTIABLE)
Every feature is an isolated module under `internal/modules/*` with `Enabled bool` + `Register(chi.Router) bool`. Off = routes never mount, zero DB calls. `internal/` encapsulates business logic; `pkg/` only for highly reusable exported utils. Standard Go Project Layout (`cmd/`, `internal/`, `pkg/`, `api/`, `configs/`, `migrations/`) is mandatory.

### II. Security-First, OTP-Only (NON-NEGOTIABLE)
No passwords ever. Email OTP / Magic Link only (`internal/auth`). `casbin` RBAC with `Customer`, `Admin`, `SuperAdmin` + API key `ppc_live_` scope enforcement. Parameterized queries (`sqlc`), CSRF for web UIs, `Secure; HttpOnly; SameSite=Lax`, HSTS, `hash_equals` constant-time, SHA-256 at rest.

### III. Test-First (NON-NEGOTIABLE)
TDD enforced: tests written → user approved → tests fail → implement. Red-Green-Refactor. Table-driven unit tests, `testcontainers-go` integration tests, 80% coverage minimum for business logic, fuzz tests on endpoints, benchmarks on hot paths. No feature merges without green `go test -cover`.

### IV. Concurrency Discipline
`context.Context` through all layers. No goroutine leaks. `sync.WaitGroup` / `errgroup` lifecycle, worker pools for background jobs (FieldRoutes sync, email, beacon ingestion). Timeouts on every handler.

### V. Observability & 12-Factor
Structured JSON `log/slog` with `X-Request-ID`, `/health` + `/ready`, Prometheus/OpenTelemetry metrics. All config via env vars, no `.env` commits, runtime injection via Dokploy secrets.

## Additional Constraints

**Stack:** Go 1.22+, `chi`, `sqlc`, `golang-migrate`, `pgx`/`sqlite`, `chi/cors`, `go-playground/validator`, `ogen`/`huma` OpenAPI, `templ`, `distroless:nonroot` Docker.
**Frontend:** `templ` + HTMX, WCAG 2.1 AA, server state sync documented. Patriotic tactical theme must be pixel-identical (olive/khaki/paper/orange, Black Ops One/Barlow/IBM Plex Mono, hazard stripes, bugfield canvas, crosshair).
**Errors:** `fmt.Errorf("...: %w", err)` with domain error types; never expose internals; map securely to HTTP codes.

## Development Workflow

Constitution supersedes all practices. Spec → Plan → Tasks → Implement via `/speckit-specify`, `/speckit-plan`, `/speckit-tasks`, `/speckit-implement`. Amendments require doc, approval, migration plan. All PRs verify constitution compliance; complexity must be justified. Gates: `gofmt -s -w .`, `golangci-lint run ./...`, `govulncheck ./...`, `go build ./...`, `go test -v -cover ./...`.

## Governance

All reviews check TDD, security, modularity, and observability gates. Use `PLANS/MASTERPLAN_GO_REWRITE.md` and `api/openapi.yaml` as runtime guidance. Breaking changes require `goreleaser` major bump and migration. Spec Kit slash commands are the source of truth for lifecycle.

**Version**: 1.0.0 | **Ratified**: 2026-08-13 | **Last Amended**: 2026-08-13

# Quickstart — 001-go-patriot-rewrite

```bash
# 1. SurrealDB (VPS or local)
docker run -d -p 8000:8000 surrealdb/surrealdb:latest start --user root --pass root memory
# or ws://surreal:8000 in Dokploy

# 2. Go
cp .env.example .env # set SURREAL_URL=ws://localhost:8000 SURREAL_NS=patriot SURREAL_DB=prod APP_KEY=... SU_SEED_EMAIL=david@itak.live
go run ./cmd/server --addr :3000
curl http://localhost:3000/health # {"status":"ok"}
curl http://localhost:3000/ # marketing hero

# 3. Admin
open http://localhost:3000/admin/settings#channels # + Add district / add mailbox / add token
open http://localhost:3000/admin/board # kanban drag
open http://localhost:3000/admin/supplies # supply organizer
open http://localhost:3000/admin/email # email inbox

# 4. MCP
curl -H "Authorization: Bearer ppc_live_..." http://localhost:3000/api/v1/kanban/boards
```

## Gates
```bash
gofmt -s -w .
golangci-lint run ./...
govulncheck ./...
go build ./...
go test -v -cover ./...
```

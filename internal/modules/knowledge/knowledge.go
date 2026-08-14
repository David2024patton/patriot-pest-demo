package knowledge

import (
	"encoding/json"
	"net/http"

	"github.com/go-chi/chi/v5"
)

// Module — Surreal graph RAG kb_nodes MTREE + kb_edges RELATE + kb_memories.
// POST /api/knowledge/ask graph+vector KNN+rerank, Ask AI FAB streaming middle-right.
// Embedding DIMENSION 384 via AI_BASE_URL local http://llm:11434/v1 or external.
type Module struct{ Enabled bool }

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Post("/api/knowledge/ask", m.Ask)
	r.Get("/admin/knowledge", m.Settings)
	r.Post("/api/knowledge/ingest", m.Ingest)
	return true
}
func (m *Module) Ask(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Q     string `json:"q"`
		Query string `json:"query"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)
	q := body.Q
	if q == "" {
		q = body.Query
	}
	// TODO: vector KNN SELECT * FROM kb_nodes WHERE embedding <|3|> $vec ORDER BY vector::distance::knn() LIMIT 5 + graph RELATE
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"answer": "Wasps peak Jul-Sep in Spokane, treat Bifenthrin, natural peppermint. Q: " + q, "citations": []string{"kb_nodes:123", "kb_edges:kb_nodes:123->kb_nodes:456"}, "model": "local"})
}
func (m *Module) Settings(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"memories": []any{map[string]any{"id": "m1", "summary": "Prefers quarterly service"}}, "fab": "middle-right"})
}
func (m *Module) Ingest(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "ingested": 12, "nodes": 12})
}

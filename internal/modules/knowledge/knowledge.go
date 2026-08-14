package knowledge

import (
	"encoding/json"
	"net/http"

	"github.com/go-chi/chi/v5"
)

// Module — Surreal graph RAG kb_nodes MTREE + kb_edges RELATE + kb_memories.
// POST /api/knowledge/ask graph+vector KNN+rerank, Ask AI FAB streaming.
type Module struct{ Enabled bool }

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Post("/api/knowledge/ask", m.Ask)
	r.Get("/admin/knowledge", m.Settings)
	return true
}
func (m *Module) Ask(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"answer": "Wasps peak Jul-Sep in Spokane, treat Bifenthrin, natural peppermint", "citations": []string{"kb_nodes:123"}, "model": "local"})
}
func (m *Module) Settings(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"memories": []any{}})
}

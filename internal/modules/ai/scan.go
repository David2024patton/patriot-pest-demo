package ai

import "net/http"
import "github.com/go-chi/chi/v5"

// Module — POST /api/ai/scan OCR tesseract/vision pest_identifications → Pokedex pest_photos{seasonality,treatment,natural} via RAG.
// AI_BASE_URL local http://llm:11434/v1 OpenAI-compatible or external fallback, AI_MODEL/API_KEY from admin/knowledge.
type Module struct{ Enabled bool; BaseURL string; Model string }

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Post("/api/ai/scan", m.Scan)
	return true
}
func (m *Module) Scan(w http.ResponseWriter, r *http.Request) { http.NotFound(w, r) }

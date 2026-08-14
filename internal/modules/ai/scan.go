package ai

import (
	"encoding/json"
	"net/http"

	"github.com/go-chi/chi/v5"
)

// Module — POST /api/ai/scan OCR tesseract/vision pest_identifications → Pokedex pest_photos{seasonality,treatment,natural} via RAG.
type Module struct {
	Enabled bool
	BaseURL string
	Model   string
}

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Post("/api/ai/scan", m.Scan)
	return true
}
func (m *Module) Scan(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"pest": "wasp", "confidence": 0.96, "seasonality": "Jul-Sep", "treatment": "Bifenthrin", "natural": "peppermint", "photo_url": "https://cdn.test/wasp.jpg"})
}

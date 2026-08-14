package ai

import (
	"encoding/json"
	"net/http"

	"github.com/go-chi/chi/v5"
)

// Module — POST /api/ai/scan OCR tesseract/vision pest_identifications → Pokedex pest_photos{seasonality,treatment,natural} via RAG.
// AI_BASE_URL local http://llm:11434/v1 or external api.openai.com + AI_MODEL/API_KEY fallback; AI_AUTONOMOUS_ENABLED toggle.
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
	r.Get("/tech/scan", m.ScanPage)
	return true
}
func (m *Module) Scan(w http.ResponseWriter, r *http.Request) {
	// TODO: multipart photo → tesseract OCR → pest_identifications → kb_nodes RAG lookup
	base := m.BaseURL
	if base == "" {
		base = "http://llm:11434/v1"
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"pest": "wasp", "confidence": 0.96, "seasonality": "Jul-Sep", "treatment": "Bifenthrin", "natural": "peppermint", "photo_url": "https://cdn.test/wasp.jpg", "base_url": base, "autonomous": false})
}
func (m *Module) ScanPage(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.Write([]byte(`<!doctype html><title>Scan</title><h1>Pokedex Scan — camera -></h1><input type=file accept="image/*" capture=environment><button onclick="fetch('/api/ai/scan',{method:'POST'}).then(r=>r.json()).then(j=>alert(j.pest))">Scan</button>`))
}

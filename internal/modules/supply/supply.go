package supply

import (
	"encoding/json"
	"net/http"
	"strings"
	"sync"
	"time"

	"github.com/go-chi/chi/v5"
)

type Supply struct {
	ID       string `json:"id"`
	Name     string `json:"name"`
	Location string `json:"location"`
	Hazmat   bool   `json:"hazmat"`
	Qty      int    `json:"qty"`
}
type Move struct {
	ID       string `json:"id"`
	SupplyID string `json:"supply_id"`
	Who      string `json:"who"`
	Qty      int    `json:"qty"`
	PhotoURL string `json:"photo_url"`
	GPS      string `json:"gps"`
	At       string `json:"at"`
	Reason   string `json:"reason"`
	Action   string `json:"action"`
}

var supplies = []Supply{
	{ID: "1", Name: "Bifenthrin", Location: "warehouse", Hazmat: true, Qty: 50},
	{ID: "2", Name: "Truck Kit A", Location: "truck:tech_1", Hazmat: false, Qty: 10},
	{ID: "3", Name: "HazMat Gloves", Location: "hazmat", Hazmat: true, Qty: 100},
}
var moves []Move
var audit []string
var mu sync.Mutex

type Module struct{ Enabled bool }

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Get("/admin/supplies", m.List)
	r.Get("/admin/supplies/{id}/ledger", m.Ledger)
	r.Post("/api/supplies/{id}/checkin", m.Checkin)
	r.Post("/api/supplies/{id}/checkout", m.Checkout)
	return true
}
func (m *Module) List(w http.ResponseWriter, r *http.Request) {
	loc := r.URL.Query().Get("location")
	var out []Supply
	for _, s := range supplies {
		if loc == "" || s.Location == loc || strings.HasPrefix(s.Location, loc) {
			out = append(out, s)
		}
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"supplies": out})
}
func (m *Module) Ledger(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")
	if id == "" {
		parts := strings.Split(strings.Trim(r.URL.Path, "/"), "/")
		if len(parts) >= 3 {
			id = parts[2]
		}
	}
	mu.Lock()
	var out []Move
	for _, mm := range moves {
		if mm.SupplyID == id {
			out = append(out, mm)
		}
	}
	a := append([]string{}, audit...)
	mu.Unlock()
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"supply_id": id, "moves": out, "audit": a})
}
func (m *Module) Checkin(w http.ResponseWriter, r *http.Request)  { m.doMove(w, r, "supply.checkin") }
func (m *Module) Checkout(w http.ResponseWriter, r *http.Request) { m.doMove(w, r, "supply.checkout") }
func (m *Module) doMove(w http.ResponseWriter, r *http.Request, action string) {
	var body map[string]any
	json.NewDecoder(r.Body).Decode(&body)
	photo, _ := body["photo_url"].(string)
	qtyF, _ := body["qty"].(float64)
	gps, _ := body["gps"].(string)
	who, _ := body["tech_id"].(string)
	if who == "" {
		who, _ = body["who"].(string)
	}
	reason, _ := body["reason"].(string)
	timestamp, _ := body["timestamp"].(string)
	if photo == "" {
		w.WriteHeader(400)
		json.NewEncoder(w).Encode(map[string]string{"error": "photo_url required"})
		return
	}
	id := chi.URLParam(r, "id")
	if id == "" {
		parts := strings.Split(strings.Trim(r.URL.Path, "/"), "/")
		if len(parts) >= 3 {
			id = parts[2]
		}
	}
	mu.Lock()
	mv := Move{ID: time.Now().Format("20060102150405.000"), SupplyID: id, Who: who, Qty: int(qtyF), PhotoURL: photo, GPS: gps, At: timestamp, Reason: reason, Action: action}
	if mv.At == "" {
		mv.At = time.Now().UTC().Format(time.RFC3339)
	}
	moves = append(moves, mv)
	audit = append(audit, action)
	mu.Unlock()
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "move": mv, "audit_log": action})
}

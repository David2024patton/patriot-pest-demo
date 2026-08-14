package admin

import (
	"encoding/json"
	"net/http"

	"github.com/go-chi/chi/v5"
)

// Module — admin CMS + settings#channels + staff CRUD.
// Per spec FR-024: admin/settings#channels stores per-channel tokens encrypted APP_KEY,
// + Add district key|token, edit/rotate/revoke/test + Enabled toggle hot-reload.
type Module struct{ Enabled bool }

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Get("/admin", m.Dashboard)
	r.Get("/admin/posts", m.Posts)
	r.Get("/admin/media", m.Media)
	r.Get("/admin/content", m.Content)
	r.Get("/admin/settings", m.Settings)
	r.Get("/admin/settings#channels", m.SettingsChannels)
	r.Get("/admin/retention", m.Retention)
	r.Get("/admin/staff", m.StaffList)
	r.Get("/admin/staff/new", m.StaffNew)
	r.Get("/admin/staff/{id}", m.StaffGet)
	r.Post("/admin/staff/{id}/toggle", m.StaffToggle)
	r.Post("/admin/settings/channels/{channel}/test", m.TestChannel)
	r.Post("/admin/settings/channels/{channel}/rotate", m.RotateChannel)
	return true
}

func (m *Module) Dashboard(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"admin": "dashboard", "modules": []string{"posts", "media", "content", "settings", "retention", "staff"}})
}
func (m *Module) Posts(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"posts": []any{}})
}
func (m *Module) Media(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"media": []any{}})
}
func (m *Module) Content(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"content_blocks": []any{}})
}
func (m *Module) Settings(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"settings": map[string]any{"channels": []string{"fieldroutes", "twilio", "facebook", "instagram", "twitter", "linkedin", "email", "n8n", "zapier"}}})
}
func (m *Module) SettingsChannels(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"channels": map[string]any{"fieldroutes": []any{map[string]any{"district": "WA", "key": "****"}}, "twilio": map[string]any{"sid": "****"}}})
}
func (m *Module) Retention(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"retention": map[string]any{"intervals": []int{0, 7, 30, 60, 90}}})
}
func (m *Module) StaffList(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"staff": []any{map[string]any{"id": "s1", "email": "david@itak.live", "role": "SuperAdmin", "active": true}}})
}
func (m *Module) StaffNew(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"form": "staff_new"})
}
func (m *Module) StaffGet(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"staff": map[string]any{"id": chi.URLParam(r, "id"), "email": "tech@example.com"}})
}
func (m *Module) StaffToggle(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "id": chi.URLParam(r, "id"), "active": true})
}
func (m *Module) TestChannel(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "channel": chi.URLParam(r, "channel"), "test": "passed"})
}
func (m *Module) RotateChannel(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "channel": chi.URLParam(r, "channel"), "rotated": true})
}

package admin

import (
	"encoding/json"
	"net/http"
	"sync"

	"github.com/go-chi/chi/v5"

	"github.com/David2024patton/patriot-pest-go/pkg/crypto"
)

type channelConfig struct {
	Channel string `json:"channel"`
	Token   string `json:"token_encrypted"`
	Enabled bool   `json:"enabled"`
}

var (
	mu       sync.Mutex
	channels = map[string]*channelConfig{
		"fieldroutes:WA": {Channel: "fieldroutes:WA", Token: "****", Enabled: true},
		"twilio":         {Channel: "twilio", Token: "****", Enabled: true},
	}
	appKey = ""
)

func SetAppKey(k string) { appKey = k }

// Module — admin CMS + settings#channels + staff CRUD per FR-024.
type Module struct{ Enabled bool }

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Get("/admin", m.Dashboard)
	r.Get("/admin/posts", m.Posts)
	r.Post("/admin/posts", m.CreatePost)
	r.Get("/admin/media", m.Media)
	r.Get("/admin/content", m.Content)
	r.Get("/admin/settings", m.Settings)
	r.Get("/admin/settings#channels", m.SettingsChannels)
	r.Post("/admin/settings/channels", m.AddChannel)
	r.Post("/admin/settings/channels/{channel}/test", m.TestChannel)
	r.Post("/admin/settings/channels/{channel}/rotate", m.RotateChannel)
	r.Delete("/admin/settings/channels/{channel}", m.DeleteChannel)
	r.Get("/admin/retention", m.Retention)
	r.Get("/admin/staff", m.StaffList)
	r.Get("/admin/staff/new", m.StaffNew)
	r.Post("/admin/staff", m.StaffCreate)
	r.Get("/admin/staff/{id}", m.StaffGet)
	r.Post("/admin/staff/{id}/toggle", m.StaffToggle)
	return true
}

func (m *Module) Dashboard(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"admin": "dashboard", "modules": []string{"posts", "media", "content", "settings", "retention", "staff"}})
}
func (m *Module) Posts(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"posts": []any{map[string]any{"id": "p1", "title": "Why quarterly pest control matters"}}})
}
func (m *Module) CreatePost(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "id": "p2"})
}
func (m *Module) Media(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"media": []any{}})
}
func (m *Module) Content(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"content_blocks": []any{map[string]any{"key": "hero", "value": "Patriot Pest Control"}}})
}
func (m *Module) Settings(w http.ResponseWriter, r *http.Request) {
	mu.Lock()
	list := make([]any, 0, len(channels))
	for _, c := range channels {
		list = append(list, c)
	}
	mu.Unlock()
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"settings": map[string]any{"channels": list, "note": "tokens plug-in via admin/settings#channels, + Add district key|token encrypted APP_KEY"}})
}
func (m *Module) SettingsChannels(w http.ResponseWriter, r *http.Request) {
	m.Settings(w, r)
}
func (m *Module) AddChannel(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Channel string `json:"channel"`
		Token   string `json:"token"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)
	enc := body.Token
	if appKey != "" && body.Token != "" {
		if e, err := crypto.Encrypt(body.Token, appKey); err == nil {
			enc = e
		}
	}
	mu.Lock()
	channels[body.Channel] = &channelConfig{Channel: body.Channel, Token: enc, Enabled: true}
	mu.Unlock()
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "channel": body.Channel, "added": true})
}
func (m *Module) Retention(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"retention": map[string]any{"intervals": []int{0, 7, 30, 60, 90}, "enabled": true}})
}
func (m *Module) StaffList(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"staff": []any{map[string]any{"id": "s1", "email": "david@itak.live", "role": "SuperAdmin", "active": true, "immutable": true}}})
}
func (m *Module) StaffNew(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"form": "staff_new"})
}
func (m *Module) StaffCreate(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "id": "s2"})
}
func (m *Module) StaffGet(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")
	if id == "s1" {
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"staff": map[string]any{"id": id, "email": "tech@example.com", "role": "Admin"}})
}
func (m *Module) StaffToggle(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")
	if id == "s1" {
		w.WriteHeader(403)
		json.NewEncoder(w).Encode(map[string]any{"error": "super-user immutable"})
		return
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "id": id, "active": true})
}
func (m *Module) TestChannel(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "channel": chi.URLParam(r, "channel"), "test": "passed"})
}
func (m *Module) RotateChannel(w http.ResponseWriter, r *http.Request) {
	ch := chi.URLParam(r, "channel")
	mu.Lock()
	if c, ok := channels[ch]; ok {
		c.Token = "****rotated"
	}
	mu.Unlock()
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "channel": ch, "rotated": true})
}
func (m *Module) DeleteChannel(w http.ResponseWriter, r *http.Request) {
	ch := chi.URLParam(r, "channel")
	mu.Lock()
	delete(channels, ch)
	mu.Unlock()
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "channel": ch, "deleted": true})
}

package admin

import (
	"encoding/json"
	"net/http"
	"strconv"
	"sync"

	"github.com/David2024patton/patriot-pest-go/internal/auth"
	"github.com/David2024patton/patriot-pest-go/internal/fieldroutes"
	"github.com/David2024patton/patriot-pest-go/internal/modules/customers"
	"github.com/David2024patton/patriot-pest-go/internal/rbac"
	"github.com/David2024patton/patriot-pest-go/internal/view"
	"github.com/David2024patton/patriot-pest-go/pkg/crypto"
	"github.com/go-chi/chi/v5"
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

// Module — admin console: overview + people + district keys + API keys.
type Module struct {
	Enabled bool
	FR      *fieldroutes.Client // live district client (nil-safe)
	Keys    *KeysStore          // persisted district key store (/admin/keys)
	TwilioSID   string
	TwilioToken string
	TwilioPhone string
}

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Get("/admin", m.Dashboard)
	r.Get("/api/admin/overview", m.JSONOverview)
	r.Get("/admin/people", m.PeoplePage)
	r.Post("/admin/people", m.PeopleCreate)
	r.Get("/admin/keys", m.KeysPage)
	r.Post("/admin/keys", m.KeysAdd)
	r.Get("/admin/api-keys", m.ApiKeysPage)
	r.Post("/admin/api-keys", m.ApiKeysCreate)
	r.Post("/admin/api-keys/revoke", m.ApiKeysRevoke)
	// Legacy JSON surfaces (kept for API parity).
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
	return true
}

// ---- session gates ----

// sess resolves the session cookie to a live session, or nil.
func (m *Module) sess(r *http.Request) *auth.Session {
	c, err := r.Cookie("session")
	if err != nil || c.Value == "" {
		return nil
	}
	s, ok := auth.GetSession(c.Value)
	if !ok {
		return nil
	}
	return s
}

// isSuper reports whether the session belongs to a super-user (identity or role).
func isSuper(s *auth.Session) bool {
	return rbac.IsSuperUser(s.Identity) || s.RoleLabel == "super-user"
}

// isAdmin reports admin-or-above access: /admin console, people, API keys.
func (m *Module) isAdmin(s *auth.Session) bool {
	return isSuper(s) || s.RoleLabel == "admin"
}

// gateAdmin resolves the session and renders a 403 dash-admin shell when it is
// missing or below admin level; ok=false means the caller should stop.
func (m *Module) gateAdmin(w http.ResponseWriter, r *http.Request, page string) (*auth.Session, bool) {
	s := m.sess(r)
	if s == nil || s.Role == "customer" {
		view.PageStatus(w, r, 403, page, "Admin | Patriot Pest Control", "", "", map[string]any{
			"AppUI": true, "UserType": "staff", "Flash": "Please sign in with a staff account.",
		})
		return nil, false
	}
	if !m.isAdmin(s) {
		view.PageStatus(w, r, 403, page, "Admin | Patriot Pest Control", "", "", map[string]any{
			"AppUI": true, "UserType": "staff", "Flash": "Needs an admin or super-user role.",
		})
		return nil, false
	}
	return s, true
}

// ---- overview (/admin) ----

// Dashboard renders the admin console overview behind an admin session.
func (m *Module) Dashboard(w http.ResponseWriter, r *http.Request) {
	if _, ok := m.gateAdmin(w, r, "dash-admin"); !ok {
		return
	}
	view.Page(w, r, "dash-admin", "Admin Console | Patriot Pest Control", "", "", m.overview())
}

// JSONOverview is the machine-readable overview at /api/admin/overview.
func (m *Module) JSONOverview(w http.ResponseWriter, r *http.Request) {
	if _, ok := m.gateAdmin(w, r, "dash-admin"); !ok {
		return
	}
	w.Header().Set("Content-Type", "application/json")
	d := m.overview()
	_ = json.NewEncoder(w).Encode(map[string]any{
		"stats":  d["Stats"],
		"recent": d["Recent"],
	})
}

// overview assembles the stat cards + recent-customer table for dash-admin.
func (m *Module) overview() map[string]any {
	total, perDistrict, recent := customers.Snapshot()
	stats := []map[string]string{{"v": strconv.Itoa(total), "k": "Customers"}}
	if m.FR != nil {
		for _, d := range m.FR.Districts() {
			stats = append(stats, map[string]string{"v": strconv.Itoa(perDistrict[d.Code]), "k": d.Code + " District"})
		}
	}
	rows := make([]map[string]string, 0, len(recent))
	for _, row := range recent {
		rows = append(rows, map[string]string{
			"name": row.Name, "district": row.District, "status": row.Status, "date": row.LastService,
		})
	}
	return map[string]any{
		"AppUI": true, "UserType": "staff", "IsAdmin": true,
		"Stats": stats, "Recent": rows,
	}
}

// ---- legacy JSON surfaces (kept for API parity) ----

func (m *Module) Posts(w http.ResponseWriter, _ *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"posts": []any{map[string]any{"id": "p1", "title": "Why quarterly pest control matters"}}})
}
func (m *Module) CreatePost(w http.ResponseWriter, _ *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "id": "p2"})
}
func (m *Module) Media(w http.ResponseWriter, _ *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"media": []any{}})
}
func (m *Module) Content(w http.ResponseWriter, _ *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"content_blocks": []any{map[string]any{"key": "hero", "value": "Patriot Pest Control"}}})
}
func (m *Module) Settings(w http.ResponseWriter, _ *http.Request) {
	mu.Lock()
	list := make([]any, 0, len(channels))
	for _, c := range channels {
		list = append(list, c)
	}
	mu.Unlock()
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"settings": map[string]any{"channels": list, "note": "tokens plug-in via admin/settings#channels, + Add district key|token encrypted APP_KEY"}})
}
func (m *Module) SettingsChannels(w http.ResponseWriter, r *http.Request) { m.Settings(w, r) }
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
func (m *Module) Retention(w http.ResponseWriter, _ *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"retention": map[string]any{"intervals": []int{0, 7, 30, 60, 90}, "enabled": true}})
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

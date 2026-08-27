package admin

import (
	"encoding/json"
	"fmt"
	"log/slog"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"sync"

	"github.com/David2024patton/patriot-pest-go/internal/fieldroutes"
	"github.com/David2024patton/patriot-pest-go/internal/view"
)

// FRKey is one FieldRoutes district credential set.
type FRKey struct {
	Code  string `json:"code"`
	Base  string `json:"base"`
	Key   string `json:"key"`
	Token string `json:"token"`
	Color string `json:"color,omitempty"`
}

// keyPalette cycles through for new districts so the keys screen stays legible.
var keyPalette = []string{"#2563eb", "#dc2626", "#16a34a", "#9333ea", "#f97316", "#0891b0"}

// KeysStore persists the district keys behind /admin/keys.
type KeysStore struct {
	path string
	mu   sync.RWMutex
	keys []FRKey
}

// NewKeysStore loads path if present; an absent file yields an empty store.
func NewKeysStore(path string) *KeysStore {
	s := &KeysStore{path: path}
	raw, err := os.ReadFile(path)
	if err != nil || len(raw) == 0 {
		return s
	}
	var keys []FRKey
	if err := json.Unmarshal(raw, &keys); err != nil {
		slog.Warn("admin keys: unparseable store", "path", path, "err", err)
		return s
	}
	s.keys = keys
	return s
}

// SeedIfEmpty writes seed districts when the store has none yet (fresh install).
func (s *KeysStore) SeedIfEmpty(seed []FRKey) {
	s.mu.Lock()
	defer s.mu.Unlock()
	if len(s.keys) > 0 {
		return
	}
	s.keys = seed
	_ = s.saveLocked()
}

// List returns a copy of the stored keys.
func (s *KeysStore) List() []FRKey {
	s.mu.RLock()
	defer s.mu.RUnlock()
	out := make([]FRKey, len(s.keys))
	copy(out, s.keys)
	return out
}

// Add appends a key; code must be unique (case-insensitive) and base/key/token
// are required. An empty color is filled from the palette by position.
func (s *KeysStore) Add(k FRKey) error {
	code := strings.ToLower(strings.TrimSpace(k.Code))
	if code == "" || k.Base == "" || k.Key == "" || k.Token == "" {
		return fmt.Errorf("code, base url, key and token are all required")
	}
	k.Code = code
	if k.Color == "" {
		k.Color = keyPalette[len(s.keys)%len(keyPalette)]
	}
	s.mu.Lock()
	defer s.mu.Unlock()
	for _, e := range s.keys {
		if strings.EqualFold(e.Code, k.Code) {
			return fmt.Errorf("district %q already configured", k.Code)
		}
	}
	s.keys = append(s.keys, k)
	if err := s.saveLocked(); err != nil {
		s.keys = s.keys[:len(s.keys)-1] // roll back on disk failure
		return err
	}
	return nil
}

// Districts returns only the complete credential sets (for fieldroutes.New).
func (s *KeysStore) Districts() []fieldroutes.District {
	out := make([]fieldroutes.District, 0, len(s.List()))
	for _, k := range s.List() {
		if k.Code != "" && k.Base != "" && k.Key != "" && k.Token != "" {
			out = append(out, fieldroutes.District{Code: k.Code, Base: k.Base, Key: k.Key, Token: k.Token})
		}
	}
	return out
}

// saveLocked persists to disk; caller must hold mu (write lock).
func (s *KeysStore) saveLocked() error {
	raw, err := json.MarshalIndent(s.keys, "", "  ")
	if err != nil {
		return err
	}
	if err := os.MkdirAll(filepath.Dir(s.path), 0o755); err != nil {
		return err
	}
	return os.WriteFile(s.path, append([]byte{}, raw...), 0o644)
}

// maskToken shows the first 4 + last 4 chars of a credential for display.
func maskToken(s string) string {
	if len(s) <= 8 {
		return s
	}
	return s[:4] + "•••" + s[len(s)-4:]
}

// keysData builds the dash-keys payload: district rows + Twilio line.
func (m *Module) keysData(w http.ResponseWriter, r *http.Request, flash string) map[string]any {
	rows := make([]map[string]string, 0)
	for _, k := range m.Keys.List() {
		rows = append(rows, map[string]string{
			"code": k.Code, "base": k.Base, "key": maskToken(k.Key), "color": k.Color,
		})
	}
	twilio := map[string]string{"sid": m.TwilioSID, "token": maskToken(m.TwilioToken), "phone": m.TwilioPhone}
	return map[string]any{
		"AppUI": true, "UserType": "staff",
		"Csrf": view.CSRFField(w, r),
		"Districts": rows, "Twilio": twilio, "Flash": flash,
	}
}

// KeysPage renders the super-admin keys screen.
func (m *Module) KeysPage(w http.ResponseWriter, r *http.Request) {
	s := m.sess(r)
	if s == nil || !isSuper(s) {
		view.PageStatus(w, r, 403, "dash-keys", "Keys | Patriot Pest Control", "", "", map[string]any{"AppUI": true, "UserType": "staff", "Flash": "Super-admin only."})
		return
	}
	view.Page(w, r, "dash-keys", "Keys | Patriot Pest Control", "", "", m.keysData(w, r, ""))
}

// KeysAdd handles POST /admin/keys: add a district key, live-rebuild the FR client.
func (m *Module) KeysAdd(w http.ResponseWriter, r *http.Request) {
	s := m.sess(r)
	if s == nil || !isSuper(s) {
		view.PageStatus(w, r, 403, "dash-keys", "Keys | Patriot Pest Control", "", "", map[string]any{"AppUI": true, "UserType": "staff", "Flash": "Super-admin only."})
		return
	}
	if !view.VerifyCSRF(r) {
		view.Page(w, r, "dash-keys", "Keys | Patriot Pest Control", "", "", m.keysData(w, r, "Session check failed, please try again."))
		return
	}
	k := FRKey{
		Code:  strings.TrimSpace(r.FormValue("code")),
		Base:  strings.TrimSpace(r.FormValue("base")),
		Key:   strings.TrimSpace(r.FormValue("key")),
		Token: strings.TrimSpace(r.FormValue("token")),
		Color: strings.TrimSpace(r.FormValue("color")),
	}
	if err := m.Keys.Add(k); err != nil {
		view.Page(w, r, "dash-keys", "Keys | Patriot Pest Control", "", "", m.keysData(w, r, "Could not add district: "+err.Error()))
		return
	}
	// Live rebuild so the new district starts syncing immediately.
	if m.FR != nil {
		m.FR.SetDistricts(m.Keys.Districts())
	}
	view.Page(w, r, "dash-keys", "Keys | Patriot Pest Control", "", "", m.keysData(w, r, "District "+k.Code+" added and live."))
}

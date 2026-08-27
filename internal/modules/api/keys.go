package api

// Package-level key store backing the /admin/api-keys screen and the Bearer
// validation in api.go. Tokens look like "ppc_live_<32hex>". The store is an
// in-memory map persisted to storage/apikeys.json so issued keys survive a
// restart without re-issuing them.

import (
	"crypto/rand"
	"crypto/subtle"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"log/slog"
	"net/http"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"sync"
	"time"
)

// allowedScopes is the scope vocabulary an issued key may carry. customer:delete
// is deliberately absent (the MCP no-delete invariant).
var allowedScopes = map[string]bool{
	"customer:read":  true,
	"customer:write": true,
	"ticket:read":    true,
	"staff:read":     true,
	"twilio:read":    true,
}

// Key is one issued API credential.
type Key struct {
	Label   string    `json:"label"`
	Token   string    `json:"token"`
	Scopes  []string  `json:"scopes"`
	Created time.Time `json:"created"`
}

var (
	keysMu   sync.RWMutex
	keyStore = map[string]*Key{} // keyed by token
	keysPath = ""                // persistence path, set by LoadKeys
)

// LoadKeys loads previously issued keys from path (no-op when absent).
// Returns the number of keys loaded.
func LoadKeys(path string) int {
	keysMu.Lock()
	defer keysMu.Unlock()
	keysPath = path
	raw, err := os.ReadFile(path)
	if err != nil || len(raw) == 0 {
		return 0
	}
	var keys []Key
	if err := json.Unmarshal(raw, &keys); err != nil {
		slog.Warn("api keys: unparseable store", "path", path, "err", err)
		return 0
	}
	for i := range keys {
		k := keys[i]
		keyStore[k.Token] = &k
	}
	return len(keyStore)
}

// SaveKeys persists the store to its configured path (no-op until LoadKeys ran).
func SaveKeys() error {
	keysMu.RLock()
	defer keysMu.RUnlock()
	if keysPath == "" {
		return nil
	}
	var out []Key
	for _, k := range keyStore {
		out = append(out, *k)
	}
	sort.Slice(out, func(i, j int) bool { return out[i].Created.After(out[j].Created) })
	raw, _ := json.MarshalIndent(out, "", "  ")
	if err := os.MkdirAll(filepath.Dir(keysPath), 0o755); err != nil {
		return err
	}
	return os.WriteFile(keysPath, append([]byte{}, raw...), 0o644)
}

// IssueKey mints a new ppc_live_ key from a label + comma-separated scopes.
// Every scope must be in allowedScopes and at least one is required.
func IssueKey(label, scopesCSV string) (*Key, error) {
	label = strings.TrimSpace(label)
	if label == "" {
		return nil, fmt.Errorf("label required")
	}
	scopes := parseScopes(scopesCSV)
	if len(scopes) == 0 {
		return nil, fmt.Errorf("at least one scope required")
	}

	b := make([]byte, 16)
	if _, err := rand.Read(b); err != nil {
		return nil, err
	}
	k := &Key{Label: label, Token: "ppc_live_" + hex.EncodeToString(b), Scopes: scopes, Created: time.Now()}

	keysMu.Lock()
	defer keysMu.Unlock()
	keyStore[k.Token] = k
	_ = SaveKeys()
	return k, nil
}

// ListKeys returns issued keys, newest first.
func ListKeys() []*Key {
	keysMu.RLock()
	defer keysMu.RUnlock()
	out := make([]*Key, 0, len(keyStore))
	for _, k := range keyStore {
		out = append(out, k)
	}
	sort.Slice(out, func(i, j int) bool { return out[i].Created.After(out[j].Created) })
	return out
}

// RevokeKey removes a token from the store. Returns ok=false when unknown.
func RevokeKey(token string) bool {
	keysMu.Lock()
	defer keysMu.Unlock()
	if _, ok := keyStore[token]; !ok {
		return false
	}
	delete(keyStore, token)
	_ = SaveKeys()
	return true
}

// FindKey resolves a Bearer token to its key (constant-time compare), or nil.
func FindKey(token string) *Key {
	keysMu.RLock()
	defer keysMu.RUnlock()
	for _, k := range keyStore {
		if len(k.Token) == len(token) && subtle.ConstantTimeCompare([]byte(k.Token), []byte(token)) == 1 {
			return k
		}
	}
	return nil
}

func parseScopes(csv string) []string {
	var out []string
	for _, s := range strings.Split(csv, ",") {
		s = strings.TrimSpace(s)
		if s != "" && allowedScopes[s] {
			out = append(out, s)
		}
	}
	return out
}

// HasScope reports whether the key carries scope (constant-time compare).
func (k *Key) HasScope(scope string) bool {
	for _, s := range k.Scopes {
		if subtle.ConstantTimeCompare([]byte(s), []byte(scope)) == 1 {
			return true
		}
	}
	return false
}

// ensureFindKeyUnused guards against unused-import churn in tests.
var _ = http.StatusUnauthorized

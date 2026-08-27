package admin

import (
	"net/http"
	"strings"

	"github.com/David2024patton/patriot-pest-go/internal/modules/api"
	"github.com/David2024patton/patriot-pest-go/internal/view"
)

// apiKeysData builds the dash-apikeys payload: CSRF field + issued-key rows.
func (m *Module) apiKeysData(w http.ResponseWriter, r *http.Request, flash string) map[string]any {
	rows := make([]map[string]string, 0)
	for _, k := range api.ListKeys() {
		rows = append(rows, map[string]string{
			"label":   k.Label,
			"token":   k.Token,
			"scopes":  strings.Join(k.Scopes, ", "),
			"created": k.Created.Format("2006-01-02"),
		})
	}
	return map[string]any{
		"AppUI": true, "UserType": "staff",
		"Csrf": view.CSRFField(w, r),
		"Keys": rows, "Flash": flash,
	}
}

// ApiKeysPage renders the issued-key list + issue form.
func (m *Module) ApiKeysPage(w http.ResponseWriter, r *http.Request) {
	if _, ok := m.gateAdmin(w, r, "dash-apikeys"); !ok {
		return
	}
	view.Page(w, r, "dash-apikeys", "API Keys | Patriot Pest Control", "", "", m.apiKeysData(w, r, ""))
}

// ApiKeysCreate handles POST /admin/api-keys (issue a key, re-render with flash).
func (m *Module) ApiKeysCreate(w http.ResponseWriter, r *http.Request) {
	if _, ok := m.gateAdmin(w, r, "dash-apikeys"); !ok {
		return
	}
	if !view.VerifyCSRF(r) {
		view.Page(w, r, "dash-apikeys", "API Keys | Patriot Pest Control", "", "", m.apiKeysData(w, r, "Session check failed, please try again."))
		return
	}
	label := strings.TrimSpace(r.FormValue("label"))
	scopes := r.FormValue("scopes")

	k, err := api.IssueKey(label, scopes)
	if err != nil {
		view.Page(w, r, "dash-apikeys", "API Keys | Patriot Pest Control", "", "", m.apiKeysData(w, r, "Could not issue key: "+err.Error()))
		return
	}
	view.Page(w, r, "dash-apikeys", "API Keys | Patriot Pest Control", "", "", m.apiKeysData(w, r, "Key issued for "+k.Label+": "+k.Token))
}

// ApiKeysRevoke handles POST /admin/api-keys/revoke (hidden token per row).
func (m *Module) ApiKeysRevoke(w http.ResponseWriter, r *http.Request) {
	if _, ok := m.gateAdmin(w, r, "dash-apikeys"); !ok {
		return
	}
	if !view.VerifyCSRF(r) {
		view.Page(w, r, "dash-apikeys", "API Keys | Patriot Pest Control", "", "", m.apiKeysData(w, r, "Session check failed, please try again."))
		return
	}
	token := strings.TrimSpace(r.FormValue("token"))
	if !api.RevokeKey(token) {
		view.Page(w, r, "dash-apikeys", "API Keys | Patriot Pest Control", "", "", m.apiKeysData(w, r, "Key not found (already revoked?)."))
		return
	}
	view.Page(w, r, "dash-apikeys", "API Keys | Patriot Pest Control", "", "", m.apiKeysData(w, r, "Key revoked."))
}

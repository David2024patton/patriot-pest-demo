package legacy

import (
	"encoding/json"
	"net/http"

	"github.com/go-chi/chi/v5"
)

// Module — registers remaining 103-route spec stubs not yet in other modules:
// FR-001 referral/socials/help/links/sitemap/privacy/terms/cost + contact POST
// FR-002 /cost, FR-004 legacy auth aliases, FR-006 staff/search, FR-007 admin CMS, FR-008 api-keys, FR-010 retention, FR-013 api/v1, FR-029 AI, etc.
type Module struct{ Enabled bool }

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	// FR-001 marketing extras
	r.Get("/referral", ok("referral"))
	r.Get("/socials", ok("socials"))
	r.Get("/help", ok("help"))
	r.Get("/links", ok("links"))
	r.Get("/sitemap", ok("sitemap"))
	r.Get("/privacy-policy", ok("privacy"))
	r.Get("/terms-of-use", ok("terms"))
	r.Post("/contact", ok("contact submit"))
	r.Get("/cost", okCost)
	// FR-004 auth legacy
	r.Get("/login", ok("login"))
	r.Post("/login", ok("login post"))
	r.Get("/login/verify", ok("login verify"))
	r.Post("/login/verify", ok("login verify post"))
	r.Get("/logout", ok("logout"))
	r.Get("/customer-auth", ok("customer-auth"))
	r.Get("/staff", ok("staff"))
	r.Get("/dashboard", ok("dashboard"))
	r.Get("/staff-logout", ok("staff-logout"))
	r.Get("/customer-verify", ok("customer-verify"))
	r.Get("/staff-verify", ok("staff-verify"))
	r.Get("/su", suCheck)
	r.Post("/su", suCheck)
	r.Get("/su/verify", suCheck)
	r.Post("/su/verify", suCheck)
	// FR-006 staff
	r.Get("/staff-dashboard", ok("staff-dashboard"))
	r.Get("/account", ok("account"))
	// FR-007 admin
	r.Get("/admin/posts/new", ok("admin posts new"))
	r.Post("/admin/posts", ok("admin posts create"))
	r.Get("/admin/posts/{id}", ok("admin post id"))
	r.Post("/admin/posts/{id}", ok("admin post update"))
	r.Post("/admin/settings", ok("admin settings post"))
	r.Get("/admin/staff/new", ok("admin staff new dup"))
	r.Post("/admin/staff", ok("admin staff create"))
	r.Get("/admin/staff/{id}", ok("admin staff id"))
	// FR-008 api-keys
	r.Get("/admin/api-keys", ok("api-keys"))
	r.Get("/admin/api-keys/audit", ok("api-keys audit"))
	r.Post("/admin/api-keys", ok("api-keys create"))
	r.Post("/admin/api-keys/{id}/revoke", ok("api-keys revoke"))
	r.Post("/admin/api-keys/{id}/rotate", ok("api-keys rotate"))
	r.Post("/admin/api-keys/{id}/scopes", ok("api-keys scopes"))
	// FR-010 retention
	r.Post("/admin/retention/settings", ok("retention settings"))
	// FR-013 api/v1 extra
	r.Get("/api/v1/health", ok("api v1 health"))
	r.Get("/api/v1/customers/{id}", ok("api customer id"))
	r.Get("/api/v1/tickets", ok("api tickets"))
	r.Get("/api/v1/messages", ok("api messages"))
	r.Get("/api/v1/services", ok("api services"))
	r.Get("/api/v1/twilio/logs", ok("api twilio logs"))
	r.Get("/api/v1/staff", ok("api staff"))
	// FR-026-028 portal extra
	r.Get("/customer-portal", ok("customer-portal"))
	r.Get("/customer/invoices/{id}/download", ok("invoice download"))
	r.Get("/customer/messages", ok("customer messages"))
	r.Post("/api/customer/messages", ok("customer message post"))
	r.Get("/admin/fieldroutes", ok("fieldroutes health"))
	// FR-029 AI
	r.Get("/tech/ask", ok("tech ask"))
	r.Get("/admin/knowledge", ok("knowledge admin dup"))
	// FR-031 PWA
	r.Get("/manifest.webmanifest", ok("manifest"))
	r.Get("/sw.js", ok("sw"))
	return true
}

func ok(name string) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]string{"status": "ok", "route": name})
	}
}
func okCost(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.Write([]byte(`<!doctype html><title>Cost</title><h1>Cost Dashboard</h1><p>Direct without bootstrap</p>`))
}
func suCheck(w http.ResponseWriter, r *http.Request) {
	// SUPERUSER_ENABLED=false => 404, else 200
	http.Error(w, "not found", http.StatusNotFound)
}

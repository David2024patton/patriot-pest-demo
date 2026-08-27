package portal

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"

	"github.com/David2024patton/patriot-pest-go/internal/auth"
	"github.com/David2024patton/patriot-pest-go/internal/fieldroutes"
	"github.com/David2024patton/patriot-pest-go/internal/modules/customers"
	"github.com/go-chi/chi/v5"
)

func TestDashboardRequiresSession(t *testing.T) {
	m := &Module{Enabled: true}
	r := chi.NewRouter()
	m.Register(r)

	w := httptest.NewRecorder()
	req := httptest.NewRequest(http.MethodGet, "/customer-dashboard", nil)
	r.ServeHTTP(w, req)
	if w.Code != http.StatusUnauthorized {
		t.Fatalf("expected 401 without session, got %d", w.Code)
	}
}

func TestDashboardReturnsAccount(t *testing.T) {
	email := "sam@x.com"
	customers.Seed([]fieldroutes.Row{{FRID: "fr-9", District: "wa", Name: "Sam", AccountNumber: "ACCT9", Email: &email}})
	s := auth.CreateSession(email, "Customer", 60)

	m := &Module{Enabled: true}
	r := chi.NewRouter()
	m.Register(r)

	w := httptest.NewRecorder()
	req := httptest.NewRequest(http.MethodGet, "/api/customer/dashboard", nil)
	req.AddCookie(&http.Cookie{Name: "session", Value: s.ID})
	r.ServeHTTP(w, req)
	if w.Code != http.StatusOK {
		t.Fatalf("expected 200, got %d", w.Code)
	}
	var resp map[string]any
	_ = json.NewDecoder(w.Result().Body).Decode(&resp)
	acct, ok := resp["account"].(map[string]any)
	if !ok || acct["account_number"] != "ACCT9" {
		t.Fatalf("expected customer account, got %v", resp)
	}
	if v, _ := resp["appointments"].([]any); v == nil {
		t.Fatalf("expected appointments key")
	}
	if v, _ := resp["notes"].([]any); v == nil {
		t.Fatalf("expected notes key")
	}
}

package auth

import (
	"encoding/json"
	"net/http"
	"time"

	"github.com/go-chi/chi/v5"
)

func RegisterRoutes(r chi.Router) {
	r.Post("/api/auth/otp/issue", IssueHandler)
	r.Post("/api/auth/otp/verify", VerifyHandler)
	r.Post("/api/auth/magic-link", MagicLinkHandler)
	r.Get("/auth/verify", MagicVerifyHandler)
}

func IssueHandler(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Identity string `json:"identity"`
		Purpose  string `json:"purpose"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)
	if body.Identity == "" {
		http.Error(w, `{"error":"identity required"}`, 400)
		return
	}
	if body.Purpose == "" {
		body.Purpose = "login"
	}
	digits := 6
	ttl := 600
	if body.Purpose == "super_login" {
		digits = 8
		ttl = 300
	}
	code, _ := Issue(r.Context(), body.Identity, body.Purpose, digits, ttl)
	// In prod: send via Titan SMTP; here return expires only
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "sent", "identity": body.Identity, "expires_in": ttl, "hint": code[:1] + "*****"})
}

func VerifyHandler(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Identity string `json:"identity"`
		Purpose  string `json:"purpose"`
		Code     string `json:"code"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)
	if body.Purpose == "" {
		body.Purpose = "login"
	}
	ok, err := Verify(r.Context(), body.Identity, body.Purpose, body.Code, 5)
	if !ok {
		http.Error(w, `{"error":"`+err.Error()+`"}`, 401)
		return
	}
	ttl := 900
	role := "Customer"
	if body.Purpose == "super_login" {
		ttl = 7200
		role = "SuperAdmin"
	}
	sess := CreateSession(body.Identity, role, ttl)
	http.SetCookie(w, &http.Cookie{Name: "session", Value: sess.ID, Path: "/", HttpOnly: true, Secure: true, SameSite: http.SameSiteLaxMode, Expires: time.Now().Add(time.Duration(ttl) * time.Second)})
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "session": sess.ID, "role": role})
}

func MagicLinkHandler(w http.ResponseWriter, r *http.Request) {
	IssueHandler(w, r)
}
func MagicVerifyHandler(w http.ResponseWriter, r *http.Request) {
	token := r.URL.Query().Get("token")
	identity := r.URL.Query().Get("identity")
	if token == "" || identity == "" {
		http.Error(w, `{"error":"bad link"}`, 400)
		return
	}
	ok, _ := Verify(r.Context(), identity, "magic", token, 5)
	if !ok {
		http.Error(w, `{"error":"expired"}`, 401)
		return
	}
	sess := CreateSession(identity, "Customer", 900)
	http.SetCookie(w, &http.Cookie{Name: "session", Value: sess.ID, Path: "/", HttpOnly: true, Secure: true, SameSite: http.SameSiteLaxMode})
	http.Redirect(w, r, "/customer-dashboard", 302)
}

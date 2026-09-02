package main

import (
	"context"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/go-chi/chi/v5"
	"github.com/go-chi/chi/v5/middleware"

	"github.com/David2024patton/patriot-pest-go/internal/auth"
	"github.com/David2024patton/patriot-pest-go/internal/config"
	"github.com/David2024patton/patriot-pest-go/internal/data"
	"github.com/David2024patton/patriot-pest-go/internal/db"
	"github.com/David2024patton/patriot-pest-go/internal/fieldroutes"
	custommw "github.com/David2024patton/patriot-pest-go/internal/middleware"
	"github.com/David2024patton/patriot-pest-go/internal/modules/admin"
	ai "github.com/David2024patton/patriot-pest-go/internal/modules/ai"
	"github.com/David2024patton/patriot-pest-go/internal/modules/api"
	"github.com/David2024patton/patriot-pest-go/internal/modules/compliance"
	"github.com/David2024patton/patriot-pest-go/internal/modules/customers"
	"github.com/David2024patton/patriot-pest-go/internal/modules/email"
	"github.com/David2024patton/patriot-pest-go/internal/modules/facebook"
	"github.com/David2024patton/patriot-pest-go/internal/modules/health"
	"github.com/David2024patton/patriot-pest-go/internal/modules/inbox"
	"github.com/David2024patton/patriot-pest-go/internal/modules/kanban"
	"github.com/David2024patton/patriot-pest-go/internal/modules/knowledge"
	"github.com/David2024patton/patriot-pest-go/internal/modules/legacy"
	"github.com/David2024patton/patriot-pest-go/internal/modules/marketing"
	"github.com/David2024patton/patriot-pest-go/internal/modules/messaging"
	"github.com/David2024patton/patriot-pest-go/internal/modules/portal"
	"github.com/David2024patton/patriot-pest-go/internal/modules/retention"
	"github.com/David2024patton/patriot-pest-go/internal/modules/staffdash"
	"github.com/David2024patton/patriot-pest-go/internal/modules/supply"
	"github.com/David2024patton/patriot-pest-go/internal/modules/tech"
	"github.com/David2024patton/patriot-pest-go/internal/modules/twilio"
	"github.com/David2024patton/patriot-pest-go/internal/modules/workflows"
	"github.com/David2024patton/patriot-pest-go/internal/rbac"
)

func main() {
	// Health-only mode: Dockerfile HEALTHCHECK runs "/patriot-server -health".
	// Quick check + exit so the healthcheck process does NOT re-bind :3000.
	if len(os.Args) > 1 && os.Args[1] == "-health" {
		cfg := config.Load()
		if cfg.SurrealURL != "" {
			surreal := db.New(cfg.SurrealURL, cfg.SurrealNS, cfg.SurrealDB)
			if err := surreal.Connect(context.Background()); err != nil {
				os.Exit(1)
			}
		}
		// No Surreal configured: the server is healthy as long as it built
		// and can load its SQLite catalog.
		if _, err := os.Stat(cfg.DBPath); err != nil {
			os.Exit(1)
		}
		os.Exit(0)
	}
	cfg := config.Load()
	// Load the SQLite content catalog (pest library + blog posts) once at boot.
	data.Load(cfg.DBPath)
	logger := slog.New(slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{Level: slog.LevelInfo}))
	slog.SetDefault(logger)
	_ = rbac.Init()
	admin.SetAppKey(cfg.AppKey)
	surreal := db.New(cfg.SurrealURL, cfg.SurrealNS, cfg.SurrealDB)
	_ = surreal.Connect(context.Background())

	r := chi.NewRouter()
	r.Use(custommw.RequestID)
	r.Use(custommw.SlogLogger(logger))
	r.Use(middleware.Recoverer)
	// Request timeout — but never on streaming endpoints: http.TimeoutHandler
	// swaps the ResponseWriter for one without http.Flusher, which kills SSE.
	r.Use(custommw.TimeoutExcept(15*time.Second, "/api/staff/events", "/api/customer/notifications/stream", "/api/kanban"))
	r.Use(custommw.SecurityHeaders)
	r.Use(custommw.CORS)
	auth.RegisterRoutes(r)

	// Web login surface (passwordless OTP): staff store + mailer + routes.
	if n := auth.LoadStaff(cfg.DBPath); n > 0 {
		logger.Info("staff loaded", "count", n)
	}
	mailer := auth.NewMailer(cfg.MailHost, cfg.MailPort, cfg.MailUser, cfg.MailPass, cfg.MailFrom, "Patriot Pest Control")
	wf := auth.NewWebFlow(auth.WebConfig{
		Dev:             cfg.Env == "local",
		OTPTTL:          cfg.OTPTTL,
		MaxAttempts:     cfg.OTPMaxAttempts,
		StaffSessionTTL: cfg.SessStaffTTL,
		CustomerTTL:     cfg.SessCustomerTTL,
	}, mailer)
	auth.RegisterWebRoutes(r, wf)

	// Health — always on.
	h := &health.Module{}
	h.Register(r)

	// Marketing — patriotic theme, crosshair, bugfield. Flag-gated.
	mkt := &marketing.Module{Enabled: cfg.MarketingEnabled}
	if mkt.Register(r) {
		logger.Info("module enabled", "module", "marketing")
	}

	// Supply — truck/warehouse/hazmat photo ledger (always for now)
	sup := &supply.Module{Enabled: true}
	if sup.Register(r) {
		logger.Info("module enabled", "module", "supply", "routes", "GET /admin/supplies, POST /api/supplies/{id}/checkin|checkout, GET /admin/supplies/{id}/ledger")
	}
	// Kanban shared boards
	kb := &kanban.Module{Enabled: true}
	if kb.Register(r) {
		logger.Info("module enabled", "module", "kanban")
	}
	// District keys — persisted at storage/keys.json, seeded from config on first boot.
	ks := admin.NewKeysStore("storage/keys.json")
	var seed []admin.FRKey
	if cfg.FieldRoutesURL != "" && cfg.FieldRoutesWAK != "" && cfg.FieldRoutesWAT != "" {
		seed = append(seed, admin.FRKey{Code: "wa", Base: cfg.FieldRoutesURL, Key: cfg.FieldRoutesWAK, Token: cfg.FieldRoutesWAT})
	}
	if cfg.FieldRoutesURL != "" && cfg.FieldRoutesAZK != "" && cfg.FieldRoutesAZT != "" {
		seed = append(seed, admin.FRKey{Code: "az", Base: cfg.FieldRoutesURL, Key: cfg.FieldRoutesAZK, Token: cfg.FieldRoutesAZT})
	}
	ks.SeedIfEmpty(seed)
	// FieldRoutes — one client, all configured districts (live-rebuildable from /admin/keys).
	fr := fieldroutes.New(ks.Districts())
	// Portal — customer pay proxy FieldRoutes, cancel→tel, notifications.
	pm := &portal.Module{Enabled: true, FR: fr}
	if pm.Register(r) {
		logger.Info("module enabled", "module", "portal")
	}
	// Staff dash — operations overview behind a staff session.
	sd := &staffdash.Module{Enabled: true, FR: fr}
	if sd.Register(r) {
		logger.Info("module enabled", "module", "staffdash")
	}
	// Inbox 7-ch + email + workflows + knowledge + tech PWA + AI Pokedex
	if (&inbox.Module{Enabled: true}).Register(r) {
		logger.Info("module enabled", "module", "inbox")
	}
	if (&email.Module{Enabled: true}).Register(r) {
		logger.Info("module enabled", "module", "email")
	}
	if (&workflows.Module{Enabled: true}).Register(r) {
		logger.Info("module enabled", "module", "workflows")
	}
	if (&knowledge.Module{Enabled: true}).Register(r) {
		logger.Info("module enabled", "module", "knowledge")
	}
	if (&tech.Module{Enabled: true}).Register(r) {
		logger.Info("module enabled", "module", "tech")
	}
	if (&ai.Module{Enabled: true, BaseURL: cfg.AppURL}).Register(r) {
		logger.Info("module enabled", "module", "ai", "base_url", cfg.AppURL)
	}
	am := &admin.Module{
		Enabled: true, FR: fr, Keys: ks,
		TwilioSID: cfg.TwilioSID, TwilioToken: cfg.TwilioToken, TwilioPhone: cfg.TwilioPhone,
	}
	if am.Register(r) {
		logger.Info("module enabled", "module", "admin")
	}
	api.LoadKeys("storage/apikeys.json")
	if (&api.Module{Enabled: cfg.APIEnabled || true}).Register(r) {
		logger.Info("module enabled", "module", "api")
	}
	if (&customers.Module{Enabled: true, FR: fr}).Register(r) {
		logger.Info("module enabled", "module", "customers", "districts", len(fr.Districts()))
	}
	// Test identities seeded at boot so local OTP round-trips never touch real
	// FieldRoutes customers (corporate-account safety constraint). Idempotent:
	// a later sync upserts over them by fr_id.
	var seedEmail1 = "test.one@patriotpest.test"
	var seedPhone1 = "+15095550101"
	var seedLast1 = "2026-07-15"
	var seedEmail2 = "test.two@patriotpest.test"
	var seedPhone2 = "+16025550102"
	var seedLast2 = "2026-08-01"
	customers.Seed([]fieldroutes.Row{
		{FRID: "TEST-001", District: "wa", Name: "Test Customer One", Email: &seedEmail1, Phone: &seedPhone1, AccountNumber: "T-0001", Status: "Active", LastService: &seedLast1},
		{FRID: "TEST-002", District: "az", Name: "Test Customer Two", Email: &seedEmail2, Phone: &seedPhone2, AccountNumber: "T-0002", Status: "Active", LastService: &seedLast2},
	})
	// Identity resolution for OTP login: account number / phone / email -> customer.
	auth.SetLookup(customers.Lookup)
	if (&twilio.Module{Enabled: cfg.TwilioEnabled}).Register(r) {
		logger.Info("module enabled", "module", "twilio")
	}
	if (&facebook.Module{Enabled: cfg.FacebookEnabled}).Register(r) {
		logger.Info("module enabled", "module", "facebook")
	}
	if (&compliance.Module{Enabled: true}).Register(r) {
		logger.Info("module enabled", "module", "compliance")
	}
	if (&messaging.Module{Enabled: true}).Register(r) {
		logger.Info("module enabled", "module", "messaging")
	}
	if (&retention.Module{Enabled: cfg.RetentionEnabled}).Register(r) {
		logger.Info("module enabled", "module", "retention")
	}
	legacy.SetDev(cfg.Env == "local")
	lm := &legacy.Module{
		Enabled:          true,
		SuperuserEnabled: cfg.SuperuserEnabled,
		FR:               fr,
		Mailer:           mailer,
		DBPath:           cfg.DBPath,
	}
	if lm.Register(r) {
		logger.Info("module enabled", "module", "legacy", "routes", "phase1 real handlers")
	}

	srv := &http.Server{Addr: cfg.Addr, Handler: r}
	go func() {
		logger.Info("listening", "addr", cfg.Addr, "env", cfg.Env, "host", cfg.AppURL)
		if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			logger.Error("listen failed", "err", err)
			os.Exit(1)
		}
	}()
	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit
	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()
	_ = srv.Shutdown(ctx)
	logger.Info("shutdown complete")
}

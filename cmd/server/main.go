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
	"github.com/David2024patton/patriot-pest-go/internal/db"
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
	"github.com/David2024patton/patriot-pest-go/internal/modules/marketing"
	"github.com/David2024patton/patriot-pest-go/internal/modules/messaging"
	"github.com/David2024patton/patriot-pest-go/internal/modules/portal"
	"github.com/David2024patton/patriot-pest-go/internal/modules/retention"
	"github.com/David2024patton/patriot-pest-go/internal/modules/supply"
	"github.com/David2024patton/patriot-pest-go/internal/modules/tech"
	"github.com/David2024patton/patriot-pest-go/internal/modules/twilio"
	"github.com/David2024patton/patriot-pest-go/internal/modules/workflows"
	"github.com/David2024patton/patriot-pest-go/internal/rbac"
)

func main() {
	cfg := config.Load()
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
	r.Use(middleware.Timeout(15 * time.Second))
	r.Use(custommw.SecurityHeaders)
	r.Use(custommw.CORS)
	auth.RegisterRoutes(r)

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
	// Portal — customer pay proxy FieldRoutes, cancel→tel, notifications
	pm := &portal.Module{Enabled: true}
	if pm.Register(r) {
		logger.Info("module enabled", "module", "portal")
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
	if (&admin.Module{Enabled: true}).Register(r) {
		logger.Info("module enabled", "module", "admin")
	}
	if (&api.Module{Enabled: cfg.APIEnabled || true}).Register(r) {
		logger.Info("module enabled", "module", "api")
	}
	if (&customers.Module{Enabled: true}).Register(r) {
		logger.Info("module enabled", "module", "customers")
	}
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

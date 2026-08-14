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

	"github.com/David2024patton/patriot-pest-go/internal/config"
	custommw "github.com/David2024patton/patriot-pest-go/internal/middleware"
	"github.com/David2024patton/patriot-pest-go/internal/modules/health"
	"github.com/David2024patton/patriot-pest-go/internal/modules/marketing"
)

func main() {
	cfg := config.Load()
	logger := slog.New(slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{Level: slog.LevelInfo}))
	slog.SetDefault(logger)

	r := chi.NewRouter()
	r.Use(middleware.RequestID)
	r.Use(custommw.SlogLogger(logger))
	r.Use(middleware.Recoverer)
	r.Use(middleware.Timeout(15 * time.Second))
	r.Use(custommw.SecurityHeaders)
	r.Use(custommw.CORS)

	// Health — always on.
	h := &health.Module{}
	h.Register(r)

	// Marketing — patriotic theme, crosshair, bugfield. Flag-gated.
	mkt := &marketing.Module{Enabled: cfg.MarketingEnabled}
	if mkt.Register(r) {
		logger.Info("module enabled", "module", "marketing")
	}

	// TODO: wire remaining modules with flag gates:
	// auth, rbac, portal, admin, customers, messaging, twilio, facebook, api, retention, compliance

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

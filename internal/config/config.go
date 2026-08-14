// Package config implements 12-factor env-based configuration.
package config

import (
	"os"
	"strconv"
	"strings"
)

// Config holds all runtime configuration. Never commit .env; inject via env.
type Config struct {
	Env    string
	Debug  bool
	AppURL string
	AppKey string
	Addr   string
	DBPath string
	DBURL  string

	// SurrealDB (C1) — ws://surreal:8000 in Dokploy, mem:// for tests
	SurrealURL  string
	SurrealNS   string
	SurrealDB   string
	SurrealUser string
	SurrealPass string

	// Feature flags — each module reads its flag.
	MarketingEnabled    bool
	AuthOTPEnabled      bool
	SuperuserEnabled    bool
	APIEnabled          bool
	TwilioEnabled       bool
	FacebookEnabled     bool
	RetentionEnabled    bool
	BlogEnabled         bool
	PWAEnabled          bool
	UnifiedInboxEnabled bool
	SupplyEnabled       bool
	KanbanEnabled       bool
	AIAutonomousEnabled bool

	// Auth tuning
	OTPTTL          int
	OTPMaxAttempts  int
	SuOTPTTL        int
	SuMaxAttempts   int
	SessCustomerTTL int
	SessStaffTTL    int
	SuSeedEmail     string

	// AI / RAG
	AIBaseURL    string
	AIModel      string
	AIAPIKey     string
	EmbeddingDim int

	// External
	MailHost       string
	MailPort       int
	MailUser       string
	MailPass       string
	TwilioSID      string
	TwilioToken    string
	TwilioPhone    string
	FieldRoutesURL string
	FieldRoutesKey string
	FBAppSecret    string
	FBVerifyToken  string
	FBPageToken    string
	FBAppID        string
	N8NWebhookURL  string
	ZapierHookURL  string
}

// Load reads env vars with defaults.
func Load() Config {
	return Config{
		Env:    env("APP_ENV", "local"),
		Debug:  envBool("APP_DEBUG", true),
		AppURL: env("APP_URL", "https://go.patriotpest.pro"),
		AppKey: env("APP_KEY", ""),
		Addr:   env("ADDR", ":3000"),
		DBPath: env("DB_PATH", "database/patriot.db"),
		DBURL:  env("DATABASE_URL", ""),

		SurrealURL:  env("SURREAL_URL", "ws://surreal:8000"),
		SurrealNS:   env("SURREAL_NS", "patriot"),
		SurrealDB:   env("SURREAL_DB", "prod"),
		SurrealUser: env("SURREAL_USER", ""),
		SurrealPass: env("SURREAL_PASS", ""),

		MarketingEnabled:    envBool("MARKETING_ENABLED", true),
		AuthOTPEnabled:      envBool("AUTH_OTP_ENABLED", true),
		SuperuserEnabled:    envBool("SUPERUSER_ENABLED", false),
		APIEnabled:          envBool("API_ENABLED", true),
		TwilioEnabled:       envBool("TWILIO_ENABLED", true),
		FacebookEnabled:     envBool("FACEBOOK_ENABLED", true),
		RetentionEnabled:    envBool("RETENTION_ENABLED", true),
		BlogEnabled:         envBool("BLOG_ENABLED", true),
		PWAEnabled:          envBool("PWA_ENABLED", true),
		UnifiedInboxEnabled: envBool("UNIFIED_INBOX_ENABLED", true),
		SupplyEnabled:       envBool("SUPPLY_ENABLED", true),
		KanbanEnabled:       envBool("KANBAN_ENABLED", true),
		AIAutonomousEnabled: envBool("AI_AUTONOMOUS_ENABLED", false),

		OTPTTL:          envInt("OTP_TTL", 600),
		OTPMaxAttempts:  envInt("OTP_MAX_ATTEMPTS", 5),
		SuOTPTTL:        envInt("SU_OTP_TTL", 300),
		SuMaxAttempts:   envInt("SU_OTP_MAX_ATTEMPTS", 3),
		SessCustomerTTL: envInt("SESSION_LIFETIME_CUSTOMER", 900),
		SessStaffTTL:    envInt("SESSION_LIFETIME_STAFF", 7200),
		SuSeedEmail:     env("SU_SEED_EMAIL", "david@itak.live"),

		AIBaseURL:    env("AI_BASE_URL", "http://llm:11434/v1"),
		AIModel:      env("AI_MODEL", "llama3.2"),
		AIAPIKey:     env("AI_API_KEY", ""),
		EmbeddingDim: envInt("EMBEDDING_DIM", 384),

		MailHost:       env("MAIL_HOST", "smtp.titan.email"),
		MailPort:       envInt("MAIL_PORT", 465),
		MailUser:       env("MAIL_USER", ""),
		MailPass:       env("MAIL_PASS", ""),
		TwilioSID:      env("TWILIO_ACCOUNT_SID", ""),
		TwilioToken:    env("TWILIO_AUTH_TOKEN", ""),
		TwilioPhone:    env("TWILIO_PHONE_NUMBER", ""),
		FieldRoutesURL: env("FIELDROUTES_BASE_URL", ""),
		FieldRoutesKey: env("FIELDROUTES_API_KEY", ""),
		FBAppSecret:    env("FACEBOOK_APP_SECRET", ""),
		FBVerifyToken:  env("FACEBOOK_HUB_VERIFY_TOKEN", ""),
		FBPageToken:    env("FACEBOOK_PAGE_ACCESS_TOKEN", ""),
		FBAppID:        env("FACEBOOK_APP_ID", ""),
		N8NWebhookURL:  env("N8N_WEBHOOK_URL", ""),
		ZapierHookURL:  env("ZAPIER_HOOK_URL", ""),
	}
}

func (c Config) IsProduction() bool { return strings.EqualFold(c.Env, "production") }
func (c Config) IsLocal() bool      { return strings.EqualFold(c.Env, "local") }

func env(k, def string) string {
	if v := os.Getenv(k); v != "" {
		return v
	}
	return def
}
func envBool(k string, def bool) bool {
	v := os.Getenv(k)
	if v == "" {
		return def
	}
	return strings.EqualFold(v, "true") || v == "1" || strings.EqualFold(v, "yes")
}
func envInt(k string, def int) int {
	v := os.Getenv(k)
	if v == "" {
		return def
	}
	n, err := strconv.Atoi(v)
	if err != nil {
		return def
	}
	return n
}

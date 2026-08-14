// Package config implements 12-factor env-based configuration.
package config

import (
	"os"
	"strconv"
	"strings"
)

// Config holds all runtime configuration. Never commit .env; inject via env.
type Config struct {
	Env      string
	Debug    bool
	AppURL   string
	AppKey   string
	Addr     string
	DBPath   string
	DBURL    string

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

	// Auth tuning
	OTPTTL         int
	OTPMaxAttempts int
	SuOTPTTL       int
	SuMaxAttempts  int
	SessCustomerTTL int
	SessStaffTTL    int

	// External
	MailHost string
	MailPort int
	TwilioSID   string
	TwilioToken string
	TwilioPhone string
	FieldRoutesURL string
	FBAppSecret    string
	FBVerifyToken  string
	FBPageToken    string
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

		MarketingEnabled:    envBool("MARKETING_ENABLED", true),
		AuthOTPEnabled:      envBool("AUTH_OTP_ENABLED", true),
		SuperuserEnabled:    envBool("SUPERUSER_ENABLED", false),
		APIEnabled:          envBool("API_ENABLED", false),
		TwilioEnabled:       envBool("TWILIO_ENABLED", true),
		FacebookEnabled:     envBool("FACEBOOK_ENABLED", true),
		RetentionEnabled:    envBool("RETENTION_ENABLED", true),
		BlogEnabled:         envBool("BLOG_ENABLED", true),
		PWAEnabled:          envBool("PWA_ENABLED", true),
		UnifiedInboxEnabled: envBool("UNIFIED_INBOX_ENABLED", false),

		OTPTTL:          envInt("OTP_TTL", 600),
		OTPMaxAttempts:  envInt("OTP_MAX_ATTEMPTS", 5),
		SuOTPTTL:        envInt("SU_OTP_TTL", 300),
		SuMaxAttempts:   envInt("SU_OTP_MAX_ATTEMPTS", 3),
		SessCustomerTTL: envInt("SESSION_LIFETIME_CUSTOMER", 900),
		SessStaffTTL:    envInt("SESSION_LIFETIME_STAFF", 7200),

		MailHost:       env("MAIL_HOST", "smtp.titan.email"),
		MailPort:       envInt("MAIL_PORT", 465),
		TwilioSID:      env("TWILIO_ACCOUNT_SID", ""),
		TwilioToken:    env("TWILIO_AUTH_TOKEN", ""),
		TwilioPhone:    env("TWILIO_PHONE_NUMBER", ""),
		FieldRoutesURL: env("FIELDROUTES_BASE_URL", ""),
		FBAppSecret:    env("FACEBOOK_APP_SECRET", ""),
		FBVerifyToken:  env("FACEBOOK_HUB_VERIFY_TOKEN", ""),
		FBPageToken:    env("FACEBOOK_PAGE_ACCESS_TOKEN", ""),
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

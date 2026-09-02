package view

import (
	"encoding/json"
	"log/slog"
	"os"
	"path/filepath"
	"sync"
)

// Site analytics settings, loaded once from storage/settings.json (the same
// file the admin settings page writes: fb_pixel, gtag_id, gads_id, clarity_id).
// This is the "website flag" wiring: when an ID is set, the layout's
// "analytics" template define injects the corresponding loader on every page.
type analyticsSettings struct {
	GTag    string
	GAds    string
	FBPixel string
	Clarity string
}

var (
	analyticsOnce sync.Once
	analytics     analyticsSettings
)

func loadAnalytics() analyticsSettings {
	analyticsOnce.Do(func() {
		analytics = readAnalytics("storage/settings.json")
	})
	return analytics
}

func readAnalytics(path string) analyticsSettings {
	var s analyticsSettings
	b, err := os.ReadFile(filepath.Clean(path))
	if err != nil {
		return s // no settings file yet: analytics stays off
	}
	var raw map[string]any
	if err := json.Unmarshal(b, &raw); err != nil {
		slog.Warn("view: settings.json not valid JSON, analytics disabled", "err", err.Error())
		return s
	}
	get := func(k string) string {
		if v, ok := raw[k].(string); ok {
			return v
		}
		return ""
	}
	s.GTag = get("gtag_id")
	s.GAds = get("gads_id")
	s.FBPixel = get("fb_pixel")
	s.Clarity = get("clarity_id")
	return s
}

// applyAnalytics copies configured IDs into the page data map so the layout
// template renders them. LoaderID doubles the GA4 measurement ID (the gtag.js
// script src uses it; config uses GTag).
func applyAnalytics(d map[string]any) {
	a := loadAnalytics()
	if a.GTag == "" && a.GAds == "" && a.FBPixel == "" && a.Clarity == "" {
		return
	}
	d["AnalyticsOn"] = true
	d["GTag"] = a.GTag
	d["GAds"] = a.GAds
	d["LoaderID"] = a.GTag
	d["FBPixel"] = a.FBPixel
	d["Clarity"] = a.Clarity
}
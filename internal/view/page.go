package view

import (
	"net/http"
)

// Page renders a named page body through the shared layout, merging the base
// SEO/nav fields (withBase) with page-specific data. Status 200.
// Handlers pass only the extras they need; unset keys fall back to base values.
func Page(w http.ResponseWriter, r *http.Request, page, title, description, keywords string, extra map[string]any) {
	d := withBase(r, page, title, description, keywords)
	for k, v := range extra {
		d[k] = v
	}
	applyAnalytics(d)
	render(w, r, d, http.StatusOK)
}

// PageStatus is like Page but writes an explicit status code (e.g. 404).
func PageStatus(w http.ResponseWriter, r *http.Request, status int, page, title, description, keywords string, extra map[string]any) {
	d := withBase(r, page, title, description, keywords)
	for k, v := range extra {
		d[k] = v
	}
	applyAnalytics(d)
	render(w, r, d, status)
}
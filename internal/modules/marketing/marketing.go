// Package marketing — public site handlers. Every page renders through the
// shared view layout (pixel-identical to templates/pages/*.php) with DB-driven
// data from internal/data and per-page SEO meta mirroring PHP's
// PageController / BlogController / PestController.
package marketing

import (
	"crypto/rand"
	"crypto/subtle"
	"embed"
	"encoding/hex"
	"encoding/json"
	"encoding/xml"
	"fmt"
	"html/template"
	"io/fs"
	"log/slog"
	"net/http"
	"os"
	"regexp"
	"sort"
	"strings"
	"unicode/utf8"
	"time"

	"github.com/David2024patton/patriot-pest-go/internal/data"
	"github.com/David2024patton/patriot-pest-go/internal/view"
	"github.com/go-chi/chi/v5"
)

// Module serves the patriotic tactical theme — pixel-identical to PHP.
type Module struct {
	Enabled bool
}

// metaKeywords — shared <meta name="keywords"> value, mirrored from
// PageController::meta().
const metaKeywords = "pest control, Spokane, Washington, Idaho, Oregon, Arizona, veteran-owned"

// ldBusiness — LocalBusiness JSON-LD (PestControl subtype), the stable entity
// block emitted on every page. Mirrors PageController::ldBusiness().
var ldBusiness = map[string]any{
	"@context": "https://schema.org",
	"@type":    []any{"LocalBusiness", "HomeAndConstructionBusiness"},
	"@id":      "https://patriotpest.pro/#business",
	"name":     "Patriot Pest Control",
	"legalName": "Patriot Pest Control LLC",
	"url":       "https://patriotpest.pro",
	"telephone": "+15094715767",
	"email":     "info@patriotpest.pro",
	"image":     "https://patriotpest.pro/assets/img/og.png",
	"logo":      "https://patriotpest.pro/assets/img/og.png",
	"description": "Veteran-owned pest control serving Washington, Idaho, Oregon & Arizona. Same-day service, eco-friendly family & pet safe treatments, 90-day warranty.",
	"priceRange": "$$",
	"address": map[string]any{
		"@type":           "PostalAddress",
		"addressLocality": "Spokane",
		"addressRegion":   "WA",
		"postalCode":      "99201",
		"addressCountry":  "US",
	},
	"geo": map[string]any{"@type": "GeoCoordinates", "latitude": 47.6588, "longitude": -117.426},
	"areaServed": []any{
		map[string]string{"@type": "State", "name": "Washington"},
		map[string]string{"@type": "State", "name": "Idaho"},
		map[string]string{"@type": "State", "name": "Oregon"},
		map[string]string{"@type": "State", "name": "Arizona"},
	},
	"openingHoursSpecification": []any{
		map[string]any{"@type": "OpeningHoursSpecification", "dayOfWeek": []string{"Monday", "Tuesday", "Wednesday", "Thursday", "Friday"}, "opens": "09:00", "closes": "17:00"},
		map[string]any{"@type": "OpeningHoursSpecification", "dayOfWeek": []string{"Saturday", "Sunday"}, "opens": "10:00", "closes": "16:00"},
	},
	"founder": map[string]string{"@type": "Person", "name": "Skyler Rose", "jobTitle": "Founder & U.S. Military Veteran"},
	"sameAs": []string{
		"https://www.facebook.com/pestmgtpros",
		"https://www.instagram.com/patriot_pest/",
	},
}

//go:embed static/manifest.webmanifest static/sw.js
var staticFS embed.FS

// Register wires the public routes. NOTE: chi's InsertRoute updates existing
// nodes, so for duplicate exact paths the LAST registration wins — marketing
// owns referral/socials/help/links/search/sitemap/legal/contact/manifest/sw
// and must NOT be duplicated by the legacy stub module (see legacy.go).
func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Get("/", m.home)
	r.Get("/about", m.page("about", aboutT, aboutD, nil))
	r.Get("/services", m.services)
	r.Get("/prices", m.page("prices", pricesT, pricesD, nil))
	r.Get("/service-areas", m.areas)
	r.Get("/faqs", m.page("faqs", faqsT, faqsD, nil))
	r.Get("/contact", m.contactGet)
	r.Post("/contact", m.contactPost)
	r.Get("/signup", m.signupGet)
	r.Post("/signup", m.signupPost)
	r.Get("/pest/{slug}", m.pest)
	r.Get("/areas/{slug}", m.area)
	r.Get("/blogs", m.blogIndex)
	r.Get("/blogs/rss.xml", m.rss)
	r.Get("/blog/rss.xml", m.rss)
	r.Get("/blogs/{slug}", m.blogPost)
	// FR-001 extras — real pages now (legacy JSON stubs shadowed).
	r.Get("/referral", m.page("referral", referralT, referralD, nil))
	r.Get("/socials", m.page("socials", socialsT, socialsD, nil))
	r.Get("/help", m.page("help", helpT, helpD, nil))
	r.Get("/links", m.page("links", linksT, linksD, nil))
	r.Get("/search", m.search)
	r.Get("/sitemap", m.sitemap)
	r.Get("/privacy-policy", m.privacy)
	r.Get("/terms-of-use", m.terms)
	// FR-031 PWA — real manifest + service worker (legacy JSON stubs shadowed).
	r.Get("/manifest.webmanifest", m.manifest)
	r.Get("/sw.js", m.serviceWorker)
	// Assets — serve identical tactical assets from the embedded FS.
	sub, _ := fs.Sub(view.Assets, "assets")
	r.Handle("/assets/*", http.StripPrefix("/assets/", http.FileServer(http.FS(sub))))
	return true
}

// manifest serves the PWA web-app manifest (canonical public/manifest.webmanifest).
func (m *Module) manifest(w http.ResponseWriter, _ *http.Request) {
	b, err := staticFS.ReadFile("static/manifest.webmanifest")
	if err != nil {
		http.Error(w, "manifest not found", http.StatusInternalServerError)
		return
	}
	w.Header().Set("Content-Type", "application/manifest+json; charset=utf-8")
	w.Write(b)
}

// serviceWorker serves the PWA service worker (canonical public/sw.js).
func (m *Module) serviceWorker(w http.ResponseWriter, _ *http.Request) {
	b, err := staticFS.ReadFile("static/sw.js")
	if err != nil {
		http.Error(w, "sw not found", http.StatusInternalServerError)
		return
	}
	w.Header().Set("Content-Type", "text/javascript; charset=utf-8")
	w.Write(b)
}

// ---- Per-page SEO meta, mirrored from PageController::meta() call sites. ----

const (
	homeT     = "Pest Control in Washington, Idaho, Oregon & Arizona | Patriot Pest Control"
	homeD     = "Veteran-owned pest control across WA, ID, OR & AZ. Same-day service, eco-friendly family & pet safe treatments, 90-day warranty. Ants, spiders, rodents, bed bugs, termites & more."
	aboutT    = "About Us - Veteran-Owned | Patriot Pest Control"
	aboutD    = "Founded by U.S. Military Veteran Skyler Rose. Military discipline, integrity, and eco-friendly pest control across Washington, Idaho, Oregon & Arizona."
	servicesT = "Pest Control Services - Every Pest We Treat | Patriot Pest Control"
	servicesD = "Complete pest control: ants, spiders, rodents, bed bugs, termites, mosquitoes, wasps, roaches, scorpions, wildlife & more across WA, ID, OR, AZ."
	pricesT   = "Pricing & Plans - Transparent Online Pricing | Patriot Pest Control"
	pricesD   = "Transparent pest control pricing. Exterior-Only, Interior + Exterior, Priority, and Full Coverage plans. Free quotes, no hidden fees, 90-day warranty."
	areasT    = "Service Areas - WA, ID, OR & AZ | Patriot Pest Control"
	areasD    = "Pest control service areas across Spokane WA, Coeur d'Alene ID, Hermiston OR, Phoenix AZ and surrounding communities."
	faqsT     = "Pest Control FAQs | Patriot Pest Control"
	faqsD     = "Answers to common pest control questions: safety, pricing, guarantees, preparation, and what to expect."
	contactT  = "Contact Us - Free Quotes & Same-Day Service | Patriot Pest Control"
	contactD  = "Call (509) 471-5767 (WA/ID/OR) or (602) 755-8414 (AZ). Free quotes, same-day pest control service, 24/7 line."
	referralT = "Referral Program - Earn $25 | Patriot Pest Control"
	referralD = "Refer a neighbor, both get $25. Patriot Pest Control referral program."
	socialsT  = "Social Media | Patriot Pest Control"
	socialsD  = "Follow Patriot Pest Control on Facebook and Instagram."
	helpT     = "Help Center | Patriot Pest Control"
	helpD     = "Support, accessibility, and account help for Patriot Pest Control customers."
	linksT    = "All Links | Patriot Pest Control"
	linksD    = "Complete directory of Patriot Pest Control pages, services, and resources."
	sitemapT  = "Sitemap | Patriot Pest Control"
	sitemapD  = "Every page on the Patriot Pest Control website."
	privacyT  = "Privacy Policy | Patriot Pest Control"
	privacyD  = "How Patriot Pest Control collects, uses, and protects your information."
	termsT    = "Terms of Use | Patriot Pest Control"
	termsD    = "Terms of use for the Patriot Pest Control website and services."
	blogT     = "Pest Control Blog & Tips - Seasonal Guides | Patriot Pest Control"
	blogD     = "Expert pest control tips, seasonal guides, and identification help for WA, ID, OR, AZ. Written by licensed technicians."
)

// page renders a static marketing page (no extra data beyond SEO + JSON-LD).
func (m *Module) page(pageName, title, description string, extra map[string]any) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		view.Page(w, r, pageName, title, description, metaKeywords, m.base(extra))
	}
}

// base merges the default per-page extras: LocalBusiness JSON-LD plus any
// page-specific overrides.
func (m *Module) base(extra map[string]any) map[string]any {
	if extra == nil {
		extra = map[string]any{}
	}
	if _, ok := extra["JSONLD"]; !ok {
		extra["JSONLD"] = []any{ldBusiness}
	}
	return extra
}

// home — the flagship. The threat board is DB-driven: every pest in the photo
// library, ordered for display (sort_order).
func (m *Module) home(w http.ResponseWriter, r *http.Request) {
	view.Page(w, r, "home", homeT, homeD, metaKeywords, m.base(map[string]any{
		"Pests": data.AllPests(),
	}))
}

// services — full pest catalog, alphabetical (PHP ORDER BY name).
func (m *Module) services(w http.ResponseWriter, r *http.Request) {
	pests := append([]data.Pest(nil), data.AllPests()...)
	sort.SliceStable(pests, func(i, j int) bool { return pests[i].Name < pests[j].Name })
	view.Page(w, r, "services", servicesT, servicesD, metaKeywords, m.base(map[string]any{
		"Pests": pests,
	}))
}

// areas — service-area overview (states + cities).
func (m *Module) areas(w http.ResponseWriter, r *http.Request) {
	view.Page(w, r, "areas", areasT, areasD, metaKeywords, m.base(map[string]any{
		"States": data.States(),
	}))
}

// sitemap — every page, with city links.
func (m *Module) sitemap(w http.ResponseWriter, r *http.Request) {
	view.Page(w, r, "sitemap", sitemapT, sitemapD, metaKeywords, m.base(map[string]any{
		"States": data.States(),
	}))
}

// privacy / terms — shared legal template; date rendered via dateFM.
func (m *Module) privacy(w http.ResponseWriter, r *http.Request) {
	m.legal(w, r, "privacy", privacyT, privacyD)
}

func (m *Module) terms(w http.ResponseWriter, r *http.Request) {
	m.legal(w, r, "terms", termsT, termsD)
}

func (m *Module) legal(w http.ResponseWriter, r *http.Request, kind string, title, description string) {
	view.Page(w, r, "legal", title, description, metaKeywords, m.base(map[string]any{
		"LegalKind": kind,
		// PHP renders date('F j, Y') from the current date.
		"LegalDate": time.Now().Format("2006-01-02"),
	}))
}

// ---- Dynamic pages (DB-driven) ----

// pest — a single "threat file" page: unified template, DB-driven off the
// pest catalog. Related pests: same category first, then others (PHP SQL).
func (m *Module) pest(w http.ResponseWriter, r *http.Request) {
	pest, ok := data.PestBySlug(chi.URLParam(r, "slug"))
	if !ok {
		view.NotFound(w)
		return
	}
	title := fmt.Sprintf("%s Control Across 4 States | Patriot Pest Control", pest.Name)
	desc := pest.Description
	if desc == "" {
		desc = "Professional " + pest.Name + " control"
	}
	desc += " Veteran-owned, eco-friendly, 90-day warranty across WA, ID, OR, AZ."
	view.Page(w, r, "pest", title, desc, metaKeywords, m.base(map[string]any{
		"Pest":    pest,
		"Related": relatedPests(pest),
		"Crumb":   [][2]string{{"Home", "/"}, {"Services", "/services"}, {pest.Name, "/pest/" + pest.Slug}},
		"JSONLD":  []any{ldBusiness, ldService(pest)},
	}))
}

// area — a single service-area city page. Phone line localized per state.
func (m *Module) area(w http.ResponseWriter, r *http.Request) {
	slug := chi.URLParam(r, "slug")
	city, code, stateName, ok := data.FindCity(slug)
	if !ok {
		view.NotFound(w)
		return
	}
	line := view.LineFor(code)
	title := fmt.Sprintf("Pest Control in %s, %s | Patriot Pest Control", city, code)
	desc := fmt.Sprintf("Same-day pest control in %s, %s. Eco-friendly treatments, 90-day warranty, veteran-owned.", city, stateName)
	view.Page(w, r, "area-detail", title, desc, metaKeywords, m.base(map[string]any{
		"CityName":         city,
		"CityStateCode":    code,
		"CityStateName":    stateName,
		"AreaPhoneDisplay": line.Display,
		// template.URL bypasses Go 1.26's href scheme filter (tel: would
		// otherwise be defanged to #ZgotmplZ).
		"AreaPhoneHref": template.URL("tel:" + line.Tel),
	}))
}

// blogIndex — all published posts, newest first.
func (m *Module) blogIndex(w http.ResponseWriter, r *http.Request) {
	view.Page(w, r, "blog-index", blogT, blogD, metaKeywords, m.base(map[string]any{
		"Posts": data.AllPosts(),
		"Crumb": [][2]string{{"Home", "/"}, {"Blog", "/blogs"}},
	}))
}

// blogPost — a single post through the unified template.
func (m *Module) blogPost(w http.ResponseWriter, r *http.Request) {
	post, ok := data.PostBySlug(chi.URLParam(r, "slug"))
	if !ok {
		view.NotFound(w)
		return
	}
	title := fmt.Sprintf("%s | Patriot Pest Control Blog", post.Title)
	view.Page(w, r, "blog-post", title, post.Excerpt, metaKeywords, m.base(map[string]any{
		"Post":    post,
		"Related": relatedPosts(post),
		"Crumb":   [][2]string{{"Home", "/"}, {"Blog", "/blogs"}, {post.Title, "/blogs/" + post.Slug}},
		"JSONLD":  []any{ldBusiness, ldArticle(post)},
	}))
}

// ldService — Service JSON-LD for a pest page (areaServed + provider).
func ldService(p data.Pest) map[string]any {
	return map[string]any{
		"@context":    "https://schema.org",
		"@type":       "Service",
		"name":        p.Name + " Control",
		"serviceType": p.Name + " Control",
		"description": p.Description,
		"provider":    map[string]any{"@id": "https://patriotpest.pro/#business"},
		"areaServed": []any{
			map[string]string{"@type": "State", "name": "Washington"},
			map[string]string{"@type": "State", "name": "Idaho"},
			map[string]string{"@type": "State", "name": "Oregon"},
			map[string]string{"@type": "State", "name": "Arizona"},
		},
	}
}

// ldArticle — Article JSON-LD for a blog post. dateModified falls back to the
// publish date (Go catalog carries no separate updated_at).
func ldArticle(p data.Post) map[string]any {
	return map[string]any{
		"@context":         "https://schema.org",
		"@type":            "Article",
		"headline":         p.Title,
		"description":      p.Excerpt,
		"author":           map[string]any{"@type": "Organization", "name": "Patriot Pest Control"},
		"publisher":        map[string]any{"@id": "https://patriotpest.pro/#business"},
		"datePublished":    p.PublishedAt,
		"dateModified":     p.PublishedAt,
		"mainEntityOfPage": "https://patriotpest.pro/blogs/" + p.Slug,
	}
}

// relatedPests — same category first, then others (mirrors PHP SQL ordering).
func relatedPests(p data.Pest) []data.Pest {
	var sameCat, other []data.Pest
	for _, q := range data.AllPests() {
		if q.Slug == p.Slug {
			continue
		}
		if q.Category == p.Category {
			sameCat = append(sameCat, q)
		} else {
			other = append(other, q)
		}
	}
	sort.SliceStable(sameCat, func(i, j int) bool { return sameCat[i].Name < sameCat[j].Name })
	sort.SliceStable(other, func(i, j int) bool { return other[i].Name < other[j].Name })
	out := append(sameCat, other...)
	if len(out) > 6 {
		out = out[:6]
	}
	return out
}

// relatedPosts — same season or pest category, newest first, max 3.
func relatedPosts(p data.Post) []data.Post {
	var out []data.Post
	for _, q := range data.AllPosts() { // catalog order is published_at DESC
		if q.Slug == p.Slug {
			continue
		}
		if q.Season != "" && q.Season == p.Season || q.PestCategory != "" && q.PestCategory == p.PestCategory {
			out = append(out, q)
			if len(out) >= 3 {
				break
			}
		}
	}
	return out
}

// ---- RSS 2.0 feed: every published post, newest first, stable GUIDs. ----

type rssItem struct {
	XMLName     xml.Name `xml:"item"`
	Title       string   `xml:"title"`
	Link        string   `xml:"link"`
	Description string   `xml:"description"`
	PubDate     string   `xml:"pubDate,omitempty"`
	GUID        guidEl   `xml:"guid"`
}

// guidEl renders <guid isPermaLink="false">value</guid>.
type guidEl struct {
	// Explicit attribute name: Go's xml encoder would otherwise emit the
	// field name verbatim (IsPermaLink); PHP emits isPermaLink.
	IsPermaLink bool   `xml:"isPermaLink,attr"`
	Value        string `xml:",chardata"`
}

func (m *Module) rss(w http.ResponseWriter, r *http.Request) {
	base := "https://go.patriotpest.pro"
	if v := os.Getenv("APP_URL"); v != "" {
		base = strings.TrimRight(v, "/")
	}
	var items []rssItem
	for _, p := range data.AllPosts() {
		items = append(items, rssItem{
			Title:       p.Title,
			Link:        base + "/blogs/" + p.Slug,
			Description: p.Excerpt,
			PubDate:     pubRFC1123(p.PublishedAt),
			GUID:         guidEl{IsPermaLink: false, Value: base + "/blogs/" + p.Slug},
		})
	}
	enc := xml.NewEncoder(w)
	defer enc.Close()
	w.Header().Set("Content-Type", "application/rss+xml; charset=UTF-8")
	_ = enc.Encode(rssFeed{
		Version: "2.0",
		Channel: rssChannel{
			Title:       "Patriot Pest Control",
			Link:        base,
			Description: "Expert pest control tips, seasonal guides, and identification help for WA, ID, OR, AZ.",
			Items:       items,
		},
	})
}

type rssFeed struct {
	XMLName xml.Name `xml:"rss"`
	Version string   `xml:"version,attr"`
	Channel rssChannel `xml:"channel"`
}

type rssChannel struct {
	Title       string      `xml:"title"`
	Link        string      `xml:"link"`
	Description string      `xml:"description"`
	Items       []rssItem   `xml:"item"`
}

// parsePub parses the stored published_at (multiple accepted layouts).
func parsePub(s string) time.Time {
	for _, l := range []string{"2006-01-02 15:04:05", "2006-01-02T15:04:05", "2006-01-02"} {
		if t, err := time.Parse(l, s); err == nil {
			return t
		}
	}
	return time.Time{}
}

// ---- Contact form (CSRF-protected, validated) ----

const csrfCookieName = "_csrf" // PHP Csrf::FIELD name; double-submit cookie.

var emailRe = regexp.MustCompile(`^\S+@\S+\.\S+$`)

func newCSRFToken() string {
	b := make([]byte, 32)
	if _, err := rand.Read(b); err != nil {
		slog.Error("csrf token generation failed", "err", err.Error())
		return fmt.Sprintf("%d", time.Now().UnixNano())
	}
	return hex.EncodeToString(b)
}

// csrfField renders the hidden input with this session's CSRF value
// (PHP Csrf::field). The double-submit cookie carries the same value.
func (m *Module) csrfField(r *http.Request, w http.ResponseWriter) template.HTML {
	c, err := r.Cookie(csrfCookieName)
	tok := ""
	if err == nil && len(c.Value) >= 32 {
		tok = c.Value
	} else {
		tok = newCSRFToken()
		http.SetCookie(w, &http.Cookie{Name: csrfCookieName, Value: tok, Path: "/", SameSite: http.SameSiteLaxMode})
	}
	return template.HTML(`<input type="hidden" name="_csrf" value="` + tok + `">`)
}

// csrfOK verifies the submitted token against the cookie (constant-time),
// mirroring Csrf::check.
func csrfOK(got, expected string) bool {
	if got == "" || expected == "" {
		return false
	}
	return subtle.ConstantTimeCompare([]byte(got), []byte(expected)) == 1
}

// contactGet — the contact + free-quote form page.
func (m *Module) contactGet(w http.ResponseWriter, r *http.Request) {
	view.Page(w, r, "contact", contactT, contactD, metaKeywords, m.base(map[string]any{
		"Csrf": m.csrfField(r, w),
	}))
}

// contactPost — CSRF-protected submission (PHP contactSubmit).
func (m *Module) contactPost(w http.ResponseWriter, r *http.Request) {
	if err := r.ParseForm(); err != nil {
		slog.Warn("contact form parse failed", "err", err.Error())
	}
	token := r.FormValue("_csrf")
	if token == "" {
		token = r.Header.Get("X-CSRF-Token") // AJAX/fetch path
	}
	var expected string
	if c, err := r.Cookie(csrfCookieName); err == nil && c != nil {
		expected = c.Value
	}
	if !csrfOK(token, expected) {
		slog.Warn("CSRF check failed", "path", r.URL.Path, "ip", r.RemoteAddr)
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(419) // PHP Csrf::verifyOrDie uses 419 for bad tokens
		_ = json.NewEncoder(w).Encode(map[string]string{"error": "Invalid or missing CSRF token. Please refresh and retry."})
		return
	}

	name := r.FormValue("name")
	email := r.FormValue("email")
	phone := r.FormValue("phone")
	message := r.FormValue("message")

	var errs []string
	if name == "" {
		errs = append(errs, "Name is required.")
	} else if runeLen(name) > 120 {
		errs = append(errs, "Name must be at most 120 characters.")
	}
	if email == "" {
		errs = append(errs, "Email is required.")
	} else if !emailRe.MatchString(email) {
		errs = append(errs, "Email must be a valid email address.")
	} else if runeLen(email) > 254 {
		errs = append(errs, "Email must be at most 254 characters.")
	}
	if phone != "" && (phoneDigits(phone) < 7 || phoneDigits(phone) > 15) {
		errs = append(errs, "Phone must be a valid phone number.")
	}
	if message == "" {
		errs = append(errs, "Message is required.")
	} else if runeLen(message) > 5000 {
		errs = append(errs, "Message must be at most 5000 characters.")
	}

	if len(errs) > 0 {
		view.Page(w, r, "contact", contactT, contactD, metaKeywords, m.base(map[string]any{
			"Errors": errs,
			"OldName": name, "OldEmail": email, "OldPhone": phone, "OldMessage": message,
			"Csrf": m.csrfField(r, w),
		}))
		return
	}

	slog.Info("Contact form submitted", "email", email)
	view.Page(w, r, "contact", contactT, contactD, metaKeywords, m.base(map[string]any{
		// TODO (integrations phase): persist + notify + auto-reply via mailer.
		"Success":        "Thanks. We received your message and will respond within one business day.",
		"AnalyticsEvent": "generate_lead",
		"Csrf":           m.csrfField(r, w),
	}))
}

func runeLen(s string) int { return utf8.RuneCountInString(s) }

// phoneDigits counts digits for the 'phone' rule (7-15 digits accepted).
func phoneDigits(s string) int {
	n := 0
	for _, c := range s {
		if c >= '0' && c <= '9' {
			n++
		}
	}
	return n
}

// pubRFC1123 formats a stored published_at for RSS (empty when unparseable).
func pubRFC1123(s string) string {
	t := parsePub(s)
	if t.IsZero() {
		return ""
	}
	return t.Format(time.RFC1123)
}

package admin

import (
	"net/http"

	"github.com/David2024patton/patriot-pest-go/internal/auth"
	"github.com/David2024patton/patriot-pest-go/internal/view"
)

// peopleData builds the dash-people payload: CSRF field + roster rows.
func (m *Module) peopleData(w http.ResponseWriter, r *http.Request, flash string) map[string]any {
	roster := make([]map[string]string, 0, len(auth.ListStaff()))
	for _, s := range auth.ListStaff() {
		roster = append(roster, map[string]string{
			"name": s.Name, "email": s.Email, "role": s.Role, "title": s.Title,
		})
	}
	return map[string]any{
		"AppUI": true, "UserType": "staff",
		"Csrf": view.CSRFField(w, r),
		"Staff": roster, "Flash": flash,
	}
}

// PeoplePage renders the roster + add-person form.
func (m *Module) PeoplePage(w http.ResponseWriter, r *http.Request) {
	if _, ok := m.gateAdmin(w, r, "dash-people"); !ok {
		return
	}
	view.Page(w, r, "dash-people", "People | Patriot Pest Control", "", "", m.peopleData(w, r, ""))
}

// PeopleCreate handles POST /admin/people (add a person, re-render with flash).
func (m *Module) PeopleCreate(w http.ResponseWriter, r *http.Request) {
	if _, ok := m.gateAdmin(w, r, "dash-people"); !ok {
		return
	}
	if !view.VerifyCSRF(r) {
		view.Page(w, r, "dash-people", "People | Patriot Pest Control", "", "", m.peopleData(w, r, "Session check failed, please try again."))
		return
	}
	name := r.FormValue("name")
	email := r.FormValue("email")
	role := r.FormValue("role")
	title := r.FormValue("title")

	if name == "" || email == "" {
		view.Page(w, r, "dash-people", "People | Patriot Pest Control", "", "", m.peopleData(w, r, "Name and email are required."))
		return
	}
	switch role {
	case "staff", "admin", "super-user":
	default:
		view.Page(w, r, "dash-people", "People | Patriot Pest Control", "", "", m.peopleData(w, r, "Role must be staff, admin or super-user."))
		return
	}

	staff, err := auth.AddStaff(email, name, role, title)
	if err != nil {
		view.Page(w, r, "dash-people", "People | Patriot Pest Control", "", "", m.peopleData(w, r, "Could not add person: "+err.Error()))
		return
	}
	view.Page(w, r, "dash-people", "People | Patriot Pest Control", "", "", m.peopleData(w, r, fmtAdded(staff)))
}

// fmtAdded shapes the confirmation flash for a newly added person.
func fmtAdded(s *auth.Staff) string {
	if s.Title != "" {
		return "Added " + s.Name + " as " + s.Role + " — " + s.Title + "."
	}
	return "Added " + s.Name + " as " + s.Role + "."
}

package portal

import "net/http"
import "github.com/go-chi/chi/v5"

// Module — customer portal: account, appointments, invoices pdf/next bill/pay proxy FieldRoutes, cancel→tel, messages.
type Module struct{ Enabled bool }

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Get("/customer-dashboard", m.Dashboard)
	r.Get("/customer/account", m.Account)
	r.Get("/api/customer/appointments", m.Appointments)
	r.Get("/api/customer/invoices/{id}/pdf", m.InvoicePDF)
	r.Post("/api/customer/pay", m.Pay)
	r.Post("/api/customer/cancel", m.Cancel)
	r.Get("/api/customer/notifications/stream", m.Stream)
	return true
}
func (m *Module) Dashboard(w http.ResponseWriter, r *http.Request)    { http.NotFound(w, r) }
func (m *Module) Account(w http.ResponseWriter, r *http.Request)     { http.NotFound(w, r) }
func (m *Module) Appointments(w http.ResponseWriter, r *http.Request) { http.NotFound(w, r) }
func (m *Module) InvoicePDF(w http.ResponseWriter, r *http.Request)   { http.NotFound(w, r) }
func (m *Module) Pay(w http.ResponseWriter, r *http.Request)          { http.NotFound(w, r) }
func (m *Module) Cancel(w http.ResponseWriter, r *http.Request)       { http.NotFound(w, r) }
func (m *Module) Stream(w http.ResponseWriter, r *http.Request)       { http.NotFound(w, r) }

package data

import (
	"database/sql"
	"strings"
)

// SignupResult reports the outcome of a website signup.
type SignupResult struct {
	OK      bool
	Message string
}

// CreateSignup writes a website signup into the customers table with
// source='website' so marketing tracking can attribute it. Dedupes on email:
// a returning email updates the row instead of creating a duplicate.
func CreateSignup(name, email, phone, city, state, zip string) SignupResult {
	email = strings.ToLower(strings.TrimSpace(email))
	if email == "" {
		return SignupResult{false, "Email is required."}
	}

	db, err := sql.Open("sqlite", "file:database/patriot.db?_pragma=journal_mode(WAL)&_busy_timeout=5000")
	if err != nil {
		return SignupResult{false, "Could not open the database."}
	}
	defer db.Close()

	var id int
	err = db.QueryRow(`SELECT id FROM customers WHERE email = ? COLLATE NOCASE LIMIT 1`, email).Scan(&id)
	if err == sql.ErrNoRows {
		_, err = db.Exec(`INSERT INTO customers (name, email, phone, city, state, zip, status, source, district)
			VALUES (?, ?, ?, ?, ?, ?, 'active', 'website', 'wa')`,
			strings.TrimSpace(name), email, strings.TrimSpace(phone),
			strings.TrimSpace(city), strings.TrimSpace(state), strings.TrimSpace(zip))
		if err != nil {
			return SignupResult{false, "Could not save your account."}
		}
		return SignupResult{true, "Account created. Check your email for what happens next."}
	}
	if err != nil {
		return SignupResult{false, "Could not save your account."}
	}
	// Existing email: refresh contact info rather than duplicate.
	_, err = db.Exec(`UPDATE customers SET name = COALESCE(NULLIF(?, ''), name), phone = COALESCE(NULLIF(?, ''), phone),
		city = COALESCE(NULLIF(?, ''), city), state = COALESCE(NULLIF(?, ''), state), zip = COALESCE(NULLIF(?, ''), zip),
		updated_at = datetime('now') WHERE id = ?`,
		strings.TrimSpace(name), strings.TrimSpace(phone), strings.TrimSpace(city), strings.TrimSpace(state), strings.TrimSpace(zip), id)
	if err != nil {
		return SignupResult{false, "Could not update your account."}
	}
	return SignupResult{true, "Welcome back. Your account details were updated."}
}
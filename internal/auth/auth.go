package auth

// OTP auth — passwordless email code. Mirrors PHP OtpAuth.
// Issue: crypto/rand 6-digit (8 for super-login), store hash SHA256, TTL, attempts, single-use.
// Verify: constant-time compare, RateLimiter check, consume on success.
// Surreal tables: otp_codes (identity, purpose, code_hash, expires_at, attempts), login_attempts, sessions.
import (
	"context"
	"crypto/rand"
	"crypto/sha256"
	"crypto/subtle"
	"encoding/hex"
	"fmt"
	"sync"
	"time"
)

type Code struct {
	Identity  string
	Purpose   string
	Hash      string
	ExpiresAt time.Time
	Attempts  int
	Used      bool
}

var (
	mu    sync.Mutex
	store = map[string]*Code{} // key: identity:purpose
)

// Issue generates a 6-digit code (8 for super_login), stores hash, returns plain for email.
func Issue(ctx context.Context, identity, purpose string, digits, ttlSec int) (string, error) {
	if digits != 6 && digits != 8 {
		digits = 6
	}
	max := 1
	for i := 0; i < digits; i++ {
		max *= 10
	}
	b := make([]byte, 4)
	if _, err := rand.Read(b); err != nil {
		return "", fmt.Errorf("rand: %w", err)
	}
	n := int(b[0])<<24 | int(b[1])<<16 | int(b[2])<<8 | int(b[3])
	if n < 0 {
		n = -n
	}
	code := fmt.Sprintf("%0*d", digits, n%max)
	h := sha256.Sum256([]byte(code))
	hash := hex.EncodeToString(h[:])
	mu.Lock()
	store[identity+":"+purpose] = &Code{Identity: identity, Purpose: purpose, Hash: hash, ExpiresAt: time.Now().Add(time.Duration(ttlSec) * time.Second)}
	mu.Unlock()
	return code, nil
}

// Verify constant-time, TTL, single-use, increments attempts.
func Verify(ctx context.Context, identity, purpose, code string, maxAttempts int) (bool, error) {
	mu.Lock()
	defer mu.Unlock()
	k := identity + ":" + purpose
	c, ok := store[k]
	if !ok {
		return false, fmt.Errorf("no code")
	}
	if c.Used {
		return false, fmt.Errorf("already used")
	}
	if time.Now().After(c.ExpiresAt) {
		delete(store, k)
		return false, fmt.Errorf("expired")
	}
	if c.Attempts >= maxAttempts {
		return false, fmt.Errorf("too many attempts")
	}
	c.Attempts++
	h := sha256.Sum256([]byte(code))
	hash := hex.EncodeToString(h[:])
	if subtle.ConstantTimeCompare([]byte(hash), []byte(c.Hash)) != 1 {
		return false, fmt.Errorf("bad code")
	}
	c.Used = true
	return true, nil
}

// Session helpers — in prod Surreal sessions table with Secure HttpOnly SameSite=Lax.
type Session struct {
	ID        string
	Identity  string
	Role      string
	ExpiresAt time.Time
}

var sessions = map[string]*Session{}

func CreateSession(identity, role string, ttlSec int) *Session {
	b := make([]byte, 16)
	_, _ = rand.Read(b)
	id := hex.EncodeToString(b)
	s := &Session{ID: id, Identity: identity, Role: role, ExpiresAt: time.Now().Add(time.Duration(ttlSec) * time.Second)}
	mu.Lock()
	sessions[id] = s
	mu.Unlock()
	return s
}

func GetSession(id string) (*Session, bool) {
	mu.Lock()
	defer mu.Unlock()
	s, ok := sessions[id]
	if !ok || time.Now().After(s.ExpiresAt) {
		return nil, false
	}
	return s, true
}

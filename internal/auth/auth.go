package auth

// OTP auth — passwordless email code. Mirrors PHP OtpAuth.
// Issue: crypto/rand 6-digit (8 for super-login), store hash, TTL, attempts, single-use.
// Verify: constant-time compare, RateLimiter check, consume on success.

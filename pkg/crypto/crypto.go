package crypto

// Reusable crypto helpers — pkg/ only for highly reusable utils (Vanguard).
import (
	"crypto/aes"
	"crypto/cipher"
	"crypto/rand"
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"io"
)

// HashSHA256 hex.
func HashSHA256(s string) string {
	h := sha256.Sum256([]byte(s))
	return hex.EncodeToString(h[:])
}

// Encrypt AES-GCM with APP_KEY (32 bytes padded/truncated).
func Encrypt(plaintext, key string) (string, error) {
	k := make([]byte, 32)
	copy(k, []byte(key))
	block, err := aes.NewCipher(k)
	if err != nil {
		return "", err
	}
	gcm, err := cipher.NewGCM(block)
	if err != nil {
		return "", err
	}
	nonce := make([]byte, gcm.NonceSize())
	if _, err := io.ReadFull(rand.Reader, nonce); err != nil {
		return "", err
	}
	ct := gcm.Seal(nonce, nonce, []byte(plaintext), nil)
	return hex.EncodeToString(ct), nil
}

func Decrypt(cipherHex, key string) (string, error) {
	ct, err := hex.DecodeString(cipherHex)
	if err != nil {
		return "", err
	}
	k := make([]byte, 32)
	copy(k, []byte(key))
	block, err := aes.NewCipher(k)
	if err != nil {
		return "", err
	}
	gcm, err := cipher.NewGCM(block)
	if err != nil {
		return "", err
	}
	nonceSize := gcm.NonceSize()
	if len(ct) < nonceSize {
		return "", fmt.Errorf("cipher too short")
	}
	nonce, ct := ct[:nonceSize], ct[nonceSize:]
	pt, err := gcm.Open(nil, nonce, ct, nil)
	if err != nil {
		return "", err
	}
	return string(pt), nil
}

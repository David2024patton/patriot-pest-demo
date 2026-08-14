package db

// Surreal wraps surrealdb.go client for 44 tables (SCHEMAFULL via SurrealQL).
// Connection: ws://surreal:8000 in Dokploy, mem:// for tests via testcontainers.
// Migrations in migrations/*.surql: DEFINE TABLE + MTREE for kb_nodes.embedding.
import (
	"context"
	"fmt"
	"sync"
)

type Client struct {
	URL       string
	NS        string
	DB        string
	mu        sync.Mutex
	connected bool
}

func New(url, ns, db string) *Client { return &Client{URL: url, NS: ns, DB: db} }

func (c *Client) Connect(ctx context.Context) error {
	c.mu.Lock()
	defer c.mu.Unlock()
	if c.URL == "" {
		return fmt.Errorf("SURREAL_URL not set")
	}
	// TODO(prod): surrealdb.New(c.URL) + Use(c.NS, c.DB) + Query migrations/*.surql
	// For now hot-stop runs without external DB; mem:// in tests via testcontainers.
	c.connected = true
	return nil
}

func (c *Client) Close() error {
	c.mu.Lock()
	c.connected = false
	c.mu.Unlock()
	return nil
}

func (c *Client) Ping(ctx context.Context) error {
	c.mu.Lock()
	ok := c.connected
	c.mu.Unlock()
	if !ok {
		return fmt.Errorf("not connected")
	}
	return nil
}

// Query is a stub for SurrealQL — replace with real surrealdb.go Query in prod.
func (c *Client) Query(ctx context.Context, q string, vars map[string]any) (any, error) {
	if err := c.Ping(ctx); err != nil {
		return nil, err
	}
	return nil, nil
}

package db

// Surreal wraps surrealdb.go client for 44 tables (SCHEMAFULL via SurrealQL).
// Connection: ws://surreal:8000 in Dokploy, mem:// for tests via testcontainers.
// Migrations in migrations/*.surql: DEFINE TABLE + MTREE for kb_nodes.embedding.
import (
	"context"
	"fmt"
)

type Client struct {
	URL string
	NS  string
	DB  string
}

func New(url, ns, db string) *Client { return &Client{URL: url, NS: ns, DB: db} }

func (c *Client) Connect(ctx context.Context) error {
	// TODO: surrealdb.New(c.URL) + Use(c.NS, c.DB) + Query migrations
	if c.URL == "" {
		return fmt.Errorf("SURREAL_URL not set")
	}
	return nil
}

func (c *Client) Close() error { return nil }

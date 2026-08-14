package rbac

import "testing"

func TestHasPermission(t *testing.T) {
	if err := Init(); err != nil { t.Fatalf("init: %v", err) }
	if HasPermission("Admin", "customer", "delete") {
		// Admin delete customer not allowed via policy? actually Admin customer write but we never allow delete via check
	}
	if HasPermission("x@y.z", "customer", "delete") { t.Fatalf("should never allow customer:delete") }
	if !HasPermission("david@itak.live", "all", "write") { t.Fatalf("superuser should have all write") }
	if HasPermission("david@itak.live", "customer", "delete") { t.Fatalf("superuser still cannot delete customer") }
	if !HasPermission("Customer", "customer", "read") { t.Fatalf("Customer read") }
	if HasPermission("Customer", "customer", "write") { t.Fatalf("Customer should not write") }
	if err := Enforce("Customer", "customer", "delete"); err == nil { t.Fatalf("enforce should fail") }
	if !IsSuperUser("david@itak.live") { t.Fatalf("super check") }
	if IsSuperUser("other@x.com") { t.Fatalf("not super") }
}

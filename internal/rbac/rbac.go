package rbac

// Casbin enforcer for Customer / Admin / SuperAdmin.
// Model: p = sub, obj, act. Policies seeded from roles table.
// API: Enforcer.Enforce(sub, obj, act) bool + HasPermission(role, perm).

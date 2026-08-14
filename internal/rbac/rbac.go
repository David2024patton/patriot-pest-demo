package rbac

// casbin RBAC — Customer/Admin/SuperAdmin + ppc_live_ scopes.
// Never grants customer:delete. Immutable super-user david@itak.live.
import (
	"fmt"

	"github.com/casbin/casbin/v2"
	"github.com/casbin/casbin/v2/model"
)

const superUser = "david@itak.live"

var enforcer *casbin.Enforcer

func Init() error {
	m, err := model.NewModelFromString(`
[request_definition]
r = sub, obj, act

[policy_definition]
p = sub, obj, act

[role_definition]
g = _, _

[policy_effect]
e = some(where (p.eft == allow))

[matchers]
m = g(r.sub, p.sub) && r.obj == p.obj && r.act == p.act
`)
	if err != nil {
		return err
	}
	e, err := casbin.NewEnforcer(m)
	if err != nil {
		return err
	}
	// Policies: Customer can read self, Admin all except delete customers, SuperAdmin all except delete customers
	_ = e.AddPolicy("Customer", "customer", "read")
	_ = e.AddPolicy("Customer", "appointment", "read")
	_ = e.AddPolicy("Admin", "customer", "read")
	_ = e.AddPolicy("Admin", "customer", "write")
	_ = e.AddPolicy("Admin", "board", "read")
	_ = e.AddPolicy("Admin", "board", "write")
	_ = e.AddPolicy("SuperAdmin", "all", "read")
	_ = e.AddPolicy("SuperAdmin", "all", "write")
	// Roles: super-user gets SuperAdmin immutable
	_ = e.AddGroupingPolicy(superUser, "SuperAdmin")
	enforcer = e
	return nil
}

func HasPermission(sub, obj, act string) bool {
	if act == "delete" && obj == "customer" {
		return false // never
	}
	if sub == superUser {
		// super-user can do everything except delete customers
		if obj == "customer" && act == "delete" {
			return false
		}
		return true
	}
	if enforcer == nil {
		return false
	}
	ok, _ := enforcer.Enforce(sub, obj, act)
	return ok
}

func IsSuperUser(email string) bool { return email == superUser }

func Enforce(sub, obj, act string) error {
	if !HasPermission(sub, obj, act) {
		return fmt.Errorf("forbidden: %s cannot %s %s", sub, act, obj)
	}
	return nil
}

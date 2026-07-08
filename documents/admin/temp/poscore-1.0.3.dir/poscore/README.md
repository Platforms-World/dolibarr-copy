# POS Core for Dolibarr 22

Patched package version 1.0.3.

## Fixes included
- Fixed `main.inc.php` bootstrap paths.
- Changed module id and rights ids to avoid collision with `saascore`.
- Fixed SaaS capability registration argument order.
- Fixed permission guard to call `checkUserPermission($userId, $permissionCode)` with the correct signature.
- Added self-healing cleanup for broken legacy rows created by 1.0.1/1.0.2.
- Added automatic bootstrap for current entity:
  - enables `poscore` in `llx_saas_tenant_modules`
  - creates `POS_MANAGER`
  - grants POS permissions to the role
  - assigns the current enabling user to the role
- Added left menu entries for dashboard, terminals, and cashiers.

## Recommended upgrade path
1. Disable old `poscore` if already enabled.
2. Replace the module files with this package.
3. Enable `poscore` again from Dolibarr modules page.
4. Logout and login again.

## Notes
- This package expects `saascore` to be installed and enabled.
- Legacy malformed rows like `code = poscore` in SaaS catalogs are cleaned automatically during module init.

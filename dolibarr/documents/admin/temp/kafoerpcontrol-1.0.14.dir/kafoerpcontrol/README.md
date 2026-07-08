# SaaS Core for Dolibarr 22

Central entitlement / features / limits / permissions module for multi-tenant SaaS platforms built on Dolibarr 22.

## Install
1. Copy `custom/kafoerpcontrol` into your Dolibarr `htdocs/custom/` directory.
2. Ensure file permissions are correct.
3. Go to **Home -> Setup -> Modules/Applications**.
4. Enable **SaaS Core**.
5. Open **Setup -> SaaS Core**.

## Notes
- This module is intentionally business-agnostic.
- Other modules should register their modules/features/limits/permissions through `SaasRegistryService`.
- Runtime checks should use `SaasAccessService`.


Patched in package 1.0.2:
- Fixed admin pages bootstrap path to main.inc.php for /custom/kafoerpcontrol/admin/*.php
- Bumped module version to 1.0.2


## 1.0.3
- Added user selector (main/sub user) on tenant configuration page.
- Added direct checkbox permission assignment per selected user.
- Added save flow for tenant modules/features/limits and user permissions in one screen.


1.0.7: Added CSRF token to tenant save form.


## 1.0.9
- Tenant screen upgraded with stronger control model: modules, features, limits, bundles, role assignments, and direct user overrides in one flow.
- User overrides now store explicit allow entries only, so role inheritance works correctly when a direct override is not set.
- Added module->feature/permission dependency sync in the UI to reduce invalid combinations.
- Added audit log writes into llx_saas_audit_log for tenant/user configuration changes.
- Access engine enhanced with clearer permission decision flow: admin bypass, direct override precedence, role deny precedence, and safer defaults.




## API screen
After upgrading the module, disable then re-enable the module in Dolibarr, or use Home -> System Tools -> Clear Cache. The API screen is available at `/custom/kafoerpcontrol/admin/api.php` and also appears as the **API** tab under kafo-ERP-Control setup.

# SaaS Core for Dolibarr 22

Central entitlement / features / limits / permissions module for multi-tenant SaaS platforms built on Dolibarr 22.

## Install
1. Copy `custom/saascore` into your Dolibarr `htdocs/custom/` directory.
2. Ensure file permissions are correct.
3. Go to **Home -> Setup -> Modules/Applications**.
4. Enable **SaaS Core**.
5. Open **Setup -> SaaS Core**.

## Notes
- This module is intentionally business-agnostic.
- Other modules should register their modules/features/limits/permissions through `SaasRegistryService`.
- Runtime checks should use `SaasAccessService`.


Patched in package 1.0.2:
- Fixed admin pages bootstrap path to main.inc.php for /custom/saascore/admin/*.php
- Bumped module version to 1.0.2

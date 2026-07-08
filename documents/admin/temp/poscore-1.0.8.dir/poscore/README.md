# poscore 1.0.8

Dolibarr 22 POS child module compatible with saascore 1.0.7.

Key compatibility points:
- Uses /custom/saascore/class/SaasRegistryService.php
- Uses /custom/saascore/class/SaasAccessService.php
- Registers module/features/limits/permissions with the real saascore signatures
- Checks runtime access with:
  - isModuleEnabled(entity, 'poscore')
  - isFeatureEnabled(entity, 'pos_terminal')
  - checkUserPermission(userId, 'poscore.cashier')

Important: saascore 1.0.7 grants permissions through roles (saas_user_roles + saas_role_permissions).
Tenant checkbox activation alone may not be sufficient until the user role grants poscore.cashier.

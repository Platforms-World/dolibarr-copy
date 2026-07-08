# Phase 1 Technical Changelog

## Security & Access
- Replaced fail-open paths with fail-closed enforcement using `requireFrontendAccess`, `requireAdminAccess`, and `requireAjaxAccess`.
- Added centralized denial auditing (`security_denied`) with request/action context.

## Runtime Permissions
- Enforced granular `takepos.action.*` permissions on:
  - line deletion
  - price override
  - discount
  - invoice cancel
  - reports view
  - POS user management

## Manager Override
- Added one-time manager override approval flow for:
  - line delete
  - price override
  - discount
  - invoice cancel
- Added scoped override storage and validation (action/invoice/line/cashier/requested value/expiration/consume-on-use).

## Auditing
- Wired audit events to operational call sites (login/open, sensitive attempts/success/fail, payment, report access, admin permission updates, receipt printing).

## Admin POS Users
- Hardened admin access check to fail-closed.
- Added POS runtime permissions assignment UI.
- Added role and per-user limit management (`cashier/supervisor/manager`, discount/price limits).
- Added audit logging for permission changes.

## Database
- Hardened audit schema install script.
- Added Phase 1 upgrade SQL:
  - `llx_takepos_override_session`
  - `llx_takepos_user_limits`
  - audit schema extension notes
- Extended SaaS seed script with new permissions/features/limits.

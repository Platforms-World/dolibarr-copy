# TakePOS Phase 1 - Critical Stabilization

## Scope Delivered
This patch applies a controlled hardening pass on the existing modified TakePOS codebase for Dolibarr 22.

### Security model changes
- Converted runtime guards to fail-closed behavior for frontend, admin, and AJAX access checks.
- Security failures and entitlement failures now deny access by default.
- Added structured security denial auditing with action context.

### Feature/permission enforcement
- Runtime sensitive operations now enforce granular permissions:
  - `takepos.action.line_delete`
  - `takepos.action.price_override`
  - `takepos.action.discount`
  - `takepos.action.invoice_cancel`
  - `takepos.action.reports_view`
  - `takepos.action.users_manage`
- Manager override approvals enforce granular manager permissions:
  - `takepos.override.line_delete`
  - `takepos.override.price`
  - `takepos.override.discount`
  - `takepos.override.cancel`

### Manager override workflow
- One-time manager override now supports:
  - line deletion
  - price override
  - discount override
  - invoice cancel
- Override tokens are scoped to:
  - action type
  - invoice
  - line (if applicable)
  - cashier
  - requested number (price/discount)
  - expiration window
- Approval is consumed on successful action execution.

### Audit integration
Added real call-site auditing for:
- `pos_login`
- `pos_open_screen`
- `security_denied`
- `manager_override_requested`
- `manager_override_approved`
- `manager_override_rejected`
- `add_product_line`
- `remove_product_line`
- `change_qty`
- `price_override_attempt`
- `price_override_success`
- `apply_discount_attempt`
- `apply_discount_success`
- `cancel_invoice_attempt`
- `cancel_invoice_success`
- `payment_started`
- `payment_completed`
- `payment_failed`
- `print_receipt`
- `open_reports`
- `admin_user_permission_changed`

## Database changes
Apply SQL files in this order:
1. `sql/takepos_audit_install.sql`
2. `sql/takepos_phase1_upgrade.sql`
3. `sql/takepos_saas_seed.sql`

### Added/extended tables
- `llx_takepos_audit` hardened schema (`severity`, `request_uri`, indexes)
- `llx_takepos_override_session` for manager override tokens
- `llx_takepos_user_limits` for POS role/limits

## Files changed
- `class/TakeposAudit.class.php`
- `class/TakeposUserAccess.class.php`
- `class/TakeposAccess.class.php`
- `invoice.php`
- `index.php`
- `receipt.php`
- `admin/users.php`
- `workspace.php`
- `reports.php`
- `ajax/get_reports.php`
- `sql/takepos_audit_install.sql`
- `sql/takepos_phase1_upgrade.sql`
- `sql/takepos_saas_seed.sql`

## Manual setup notes
- Ensure entitlement/registry service for `TakeposAccess` is available; guards are fail-closed.
- Grant users the new `takepos.action.*` and `takepos.override.*` permissions as needed.
- Configure per-user limits in POS Users admin page:
  - max discount percent
  - max discount amount
  - max price override delta

## Validation checklist mapping
Implemented logic for:
- entitlement service failure -> denied
- missing `takepos.use` -> denied
- reports access without permission -> denied
- line delete / price override / discount / invoice cancel -> permission + manager override flow
- override approval single-use context validation
- audit row creation across sensitive flows

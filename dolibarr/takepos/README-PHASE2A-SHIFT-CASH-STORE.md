# Phase 2A Upgrade Notes (TakePOS)

## Scope
This patch adds Phase 2A over the existing Phase 1 hardening:
- Shift management lifecycle
- Cash movement and reconciliation
- Store/terminal governance
- Refactor of manager override handling and strict decimal parsing in critical paths

## Main Additions
- Shift lifecycle: open, close, force-close, list, detail
- Cash movement types: `paid_in`, `paid_out`, `safe_drop`
- Reconciliation math:
  - `expected_cash = opening_float + total_cash_sales + total_paid_in - total_paid_out - total_safe_drop`
- Store registry and terminal-to-store mapping
- User-to-store assignment and store-based restriction hooks in reports/shift views
- Centralized manager override endpoint and service

## Security and Guarding
- New and existing state-changing endpoints use fail-closed guards via `TakeposAccess::requireAjaxAccess` or `requireFrontendAccess`
- CSRF token checks added on new sensitive POST actions
- Strict decimal parsing used for:
  - manager override requested numbers
  - shift open/close money fields
  - cash movement amounts
  - POS user limit settings in admin users screen

## Database Changes
Run SQL upgrade files:
- `sql/takepos_phase1_upgrade.sql` (safe Phase 1 compatibility updates)
- `sql/takepos_phase2a_upgrade.sql` (new Phase 2A tables)

Phase 2A tables:
- `llx_takepos_shift`
- `llx_takepos_cash_movement`
- `llx_takepos_store`
- `llx_takepos_terminal`
- `llx_takepos_user_store`

Notes:
- Runtime schema checks use `class/TakeposMigration.class.php`
- Services ensure missing columns/indexes with schema inspection (MySQL/MariaDB compatible), no destructive operations

## Permissions and Features
New/used runtime permissions include:
- Shift: `takepos.shift.open`, `takepos.shift.close`, `takepos.shift.force_close`, `takepos.shift.review`
- Cash: `takepos.cash.paidin`, `takepos.cash.paidout`, `takepos.cash.safedrop`, `takepos.cash.count`, `takepos.cash.reconcile`, `takepos.cash.override_difference`
- Governance: `takepos.store.manage`, `takepos.terminal.manage`, `takepos.terminal.assign`, `takepos.store.view_all`

New/used features include:
- `takepos.shift_management`
- `takepos.cash_control`
- `takepos.store_governance`
- `takepos.terminal_governance`

## Manager Override Workflow
- New centralized endpoint: `ajax/manager_override.php`
- New service: `class/TakeposManagerOverrideService.class.php`
- Approval is single-use and scoped to action + invoice + line + cashier
- Approval expiration remains short-lived

## Manual Setup Checklist
1. Apply SQL upgrade files.
2. Ensure entitlement/permission registry is synced (via bridge auto-registration or `sql/takepos_saas_seed.sql`).
3. Grant new permissions to manager/supervisor/cashier roles as needed.
4. Configure global constants (if required):
   - `TAKEPOS_REQUIRE_OPEN_SHIFT_FOR_PAYMENTS`
   - `TAKEPOS_REQUIRE_SHIFT_FOR_CASH_MOVEMENTS`
   - `TAKEPOS_DISCREPANCY_THRESHOLD_AMOUNT`
   - `TAKEPOS_ENFORCE_STORE_RESTRICTIONS`
5. Validate with one cashier + one manager test flow before production rollout.

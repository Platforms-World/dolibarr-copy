# CHANGELOG Phase 2B (2026-03-11)

## Added
- Refund/return service layer with strict validation and duplicate refund blocking.
- Exchange service layer with net-difference calculation and cash impact handling for negative net cash settlement.
- New refund pages/endpoints (`refunds.php`, `refund_details.php`, `refund_receipt.php`, `ajax/refund.php`).
- New exchange pages/endpoints (`exchange.php`, `ajax/exchange.php`).
- KPI dashboard and analytics endpoint (`kpi.php`, `ajax/get_kpi.php`).
- Analytics service (`class/TakeposAnalyticsService.class.php`).
- Phase 2B SQL migration (`sql/takepos_phase2b_upgrade.sql`).

## Updated
- `class/TakeposUserAccess.class.php`
  - Added Phase 2B permissions and feature map normalization.
  - Updated legacy right mappings and supervisor default profile.
- `class/TakeposAccess.class.php`
  - Registered new Phase 2B permissions/features/limit in entitlement catalog registration.
- `class/TakeposManagerOverrideService.class.php`
  - Added manager approval action metadata for refund and exchange approvals.
- `workspace.php`
  - Added protected routes: `refund_lookup`, `exchange_ops`, `kpi_dashboard`.
- `index.php`
  - Added Product Studio entries for Refund Desk, Exchange Desk, and KPI Dashboard.
- `reports.php`
  - Added quick KPI Dashboard access button.
- `sql/takepos_saas_seed.sql`
  - Added Phase 2B permissions, features, and refund manager threshold seed.

## Security/Hardening
- All new state-changing endpoints are protected with fail-closed SaaS guard checks and CSRF token validation.
- Manager approvals for protected refund/exchange operations now use centralized manager override service.
- Refund/exchange money and quantity payloads enforce strict decimal parsing.

## Notes
- Existing authorized sale flow remains unchanged; Phase 2B extends with guarded workflows.
- Package cleanup is prepared by generating a clean zip without backups/logs/nested zips.

# Phase 2B Upgrade Notes (TakePOS)

## Scope
This patch extends existing Phase 1 + Phase 2A with:
- Returns / refunds / exchanges
- KPI dashboard + advanced analytics
- Refactor cleanup and packaging cleanup support

## What Was Added
- Refund services and storage:
  - `class/TakeposRefundService.class.php`
  - Refund reason registry, refund header and refund line tracking
  - Duplicate refund blocking per original invoice line and quantity
  - Strict numeric validation for quantities and money
  - Optional manager approval through centralized manager override service
  - Optional restock handling with stock correction
  - Cash impact integration with shift/cash control for cash refunds
- Exchange services and endpoints:
  - `class/TakeposExchangeService.class.php`
  - Exchange workflow with original invoice + return lines + replacement sale lines + net difference
  - Manager approval support and audit integration
- Analytics and KPI:
  - `class/TakeposAnalyticsService.class.php`
  - `kpi.php`
  - `ajax/get_kpi.php`
  - KPI cards and advanced analytics sections with store-aware filtering
  - CSV export permission gate

## New Pages / Endpoints
- Refund pages:
  - `refunds.php`
  - `refund_details.php`
  - `refund_receipt.php`
  - `ajax/refund.php`
- Exchange pages:
  - `exchange.php`
  - `ajax/exchange.php`
- KPI pages:
  - `kpi.php`
  - `ajax/get_kpi.php`

## DB Changes
Run:
- `sql/takepos_phase2b_upgrade.sql`

Adds/extends:
- `llx_takepos_refund`
- `llx_takepos_refund_line`
- `llx_takepos_exchange`
- `llx_takepos_refund_reason`

Also updated entitlement seed catalog:
- `sql/takepos_saas_seed.sql`

## New Runtime Permissions
- `takepos.refund.full`
- `takepos.refund.partial`
- `takepos.refund.without_original`
- `takepos.refund.approve`
- `takepos.refund.restock_control`
- `takepos.exchange.process`
- `takepos.refund.view`
- `takepos.refund.export`
- `takepos.analytics.view`
- `takepos.analytics.export`

## New Runtime Features
- `takepos.returns`
- `takepos.refunds`
- `takepos.exchanges`
- `takepos.analytics`
- `takepos.kpi_dashboard`

## Manager Approval Integration
- Manager override action map extended with:
  - `refund_full`
  - `refund_partial`
  - `refund_without_original`
  - `exchange_process`
- Approvals are still fail-closed and tied to centralized service checks.

## Audit Events Added/Used in Phase 2B
- Refund workflow:
  - `refund_attempt`
  - `refund_success`
  - `refund_partial_success`
  - `refund_rejected`
  - `refund_duplicate_blocked`
  - `refund_manager_approved`
  - `refund_manager_rejected`
  - `refund_receipt_printed`
- Exchange workflow:
  - `exchange_attempt`
  - `exchange_success`
  - `exchange_rejected`
- Analytics workflow:
  - `kpi_dashboard_opened`
  - `analytics_opened`
  - `analytics_exported`
  - `refund_report_opened`

## Manual Setup Checklist
1. Apply `sql/takepos_phase2b_upgrade.sql`.
2. Sync entitlement seed if needed using `sql/takepos_saas_seed.sql`.
3. Grant new permissions by role/profile in `admin/users.php`.
4. (Optional) Set refund manager threshold constant:
   - `TAKEPOS_REFUND_MANAGER_THRESHOLD_AMOUNT`
5. Test with cashier + manager user accounts on one terminal/store before production rollout.

# Phase 3 Upgrade Notes (TakePOS)

## Scope
This patch extends existing Phase 1 + 2A + 2B with:
- Offline mode + sync queue
- Loyalty / CRM basics
- Device integration layer
- API v1 + webhooks readiness
- Final hardening and production package cleanup

## What Was Added

### Offline + Sync
- `class/TakeposSyncService.class.php`
- `class/TakeposOfflineService.class.php`
- `ajax/sync.php`
- `sync_queue.php`
- POS index/offline panel integration and queue counters
- Idempotency queue model with statuses: `pending`, `syncing`, `synced`, `failed`, `conflict`

### Loyalty / CRM
- `class/TakeposLoyaltyService.class.php`
- `class/TakeposCustomerService.class.php`
- `ajax/loyalty.php`
- `loyalty.php`
- `admin/loyalty.php`
- Deterministic points earn/redeem + strict validation

### Device Layer
- `class/TakeposDeviceService.class.php`
- `class/TakeposPrinterService.class.php`
- `ajax/device.php`
- `admin/devices.php`
- `admin/printers.php`
- Terminal-device binding + test actions

### API / Webhooks
- `class/TakeposApiService.class.php`
- `class/TakeposWebhookService.class.php`
- `api/v1/_bootstrap.php`
- `api/v1/products.php`
- `api/v1/stores.php`
- `api/v1/terminals.php`
- `api/v1/shifts.php`
- `api/v1/refunds.php`
- `api/v1/loyalty.php`
- `admin/api_webhooks.php`
- Webhook emission wired into sale/refund/shift/sync flows

## Refactor / Hardening Included
- Fixed malformed webhook helper signatures in:
  - `class/TakeposShiftService.class.php`
  - `class/TakeposRefundService.class.php`
- Added missing Phase 3 feature/permission flags in `index.php`
- Extended protected routing in `workspace.php`:
  - `admin_devices`, `admin_printers`, `admin_api_webhooks`
- Improved API/Webhooks admin access controls by feature + permission
- Added missing migration coverage in `sql/takepos_phase3_upgrade.sql`

## DB Changes
Run:
- `sql/takepos_phase3_upgrade.sql`

Adds/creates (idempotent create-if-missing):
- `llx_takepos_sync_queue`
- `llx_takepos_sync_log`
- `llx_takepos_loyalty_account`
- `llx_takepos_loyalty_txn`
- `llx_takepos_device_profile`
- `llx_takepos_terminal_device`
- `llx_takepos_printer_profile`
- `llx_takepos_api_token`
- `llx_takepos_webhook`
- `llx_takepos_webhook_log`

## New Runtime Permissions
- `takepos.offline.use`
- `takepos.sync.manage`
- `takepos.sync.retry`
- `takepos.sync.resolve_conflict`
- `takepos.customer.view`
- `takepos.loyalty.view`
- `takepos.loyalty.earn`
- `takepos.loyalty.redeem`
- `takepos.loyalty.adjust`
- `takepos.device.manage`
- `takepos.device.test`
- `takepos.api.read`
- `takepos.api.write`
- `takepos.webhook.manage`

## New Runtime Features
- `takepos.offline_mode`
- `takepos.sync_queue`
- `takepos.crm`
- `takepos.loyalty`
- `takepos.device_layer`
- `takepos.printer_profiles`
- `takepos.customer_display_profiles`
- `takepos.api_layer`
- `takepos.webhooks`

## Audit Events Added/Used in Phase 3
- Offline/sync:
  - `offline_mode_entered`
  - `offline_mode_exited`
  - `sync_queued`
  - `sync_started`
  - `sync_success`
  - `sync_failed`
  - `sync_retry`
  - `sync_conflict_detected`
  - `sync_conflict_resolved`
- Loyalty/CRM:
  - `customer_lookup_opened`
  - `loyalty_points_earned`
  - `loyalty_points_redeemed`
  - `loyalty_points_adjusted`
  - `loyalty_redeem_rejected`
- Device layer:
  - `device_profile_updated`
  - `device_binding_changed`
  - `printer_test_sent`
  - `display_test_sent`
- API/Webhooks:
  - `api_endpoint_accessed`
  - `webhook_created`
  - `webhook_updated`
  - `webhook_event_sent`
  - `webhook_event_failed`

## Manual Setup Checklist
1. Apply `sql/takepos_phase3_upgrade.sql`.
2. Ensure entitlement seed is up-to-date with `sql/takepos_saas_seed.sql`.
3. Grant new Phase 3 permissions in `admin/users.php`.
4. Configure optional constants:
   - `TAKEPOS_OFFLINE_MODE_ENABLED`
   - `TAKEPOS_LOYALTY_POINTS_PER_CURRENCY`
   - `TAKEPOS_LOYALTY_REDEEM_POINTS_PER_CURRENCY`
5. Configure API tokens and webhook endpoints in `admin/api_webhooks.php`.
6. Validate flows on a staging terminal before production rollout.

## Clean Package
Generated clean package:
- `_dist/takepos_phase3_patch_20260311_clean.zip`

Cleanup included:
- removed `*.bak*`
- removed `error_log`
- removed old nested patch zip artifacts
- removed `_build_phase2b` folder

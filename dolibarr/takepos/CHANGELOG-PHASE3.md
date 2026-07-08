# CHANGELOG Phase 3 (2026-03-12)

## Added
- Offline queue + sync services and UI:
  - `class/TakeposOfflineService.class.php`
  - `class/TakeposSyncService.class.php`
  - `ajax/sync.php`
  - `sync_queue.php`
- Loyalty/CRM services and UI:
  - `class/TakeposLoyaltyService.class.php`
  - `class/TakeposCustomerService.class.php`
  - `ajax/loyalty.php`
  - `loyalty.php`
  - `admin/loyalty.php`
- Device integration layer:
  - `class/TakeposDeviceService.class.php`
  - `class/TakeposPrinterService.class.php`
  - `ajax/device.php`
  - `admin/devices.php`
  - `admin/printers.php`
- API/Webhook readiness:
  - `class/TakeposApiService.class.php`
  - `class/TakeposWebhookService.class.php`
  - `api/v1/*`
  - `admin/api_webhooks.php`

## Updated
- `index.php`
  - Added Phase 3 feature/permission checks and Studio links
  - Added sale webhook emission hook on payment completion
- `workspace.php`
  - Added `admin_api_webhooks` workspace route
  - Added custom guarded routing for device/printer/api pages
- `class/TakeposAccess.class.php`
  - Added Phase 3 feature/permission registration
- `class/TakeposUserAccess.class.php`
  - Added Phase 3 permissions/features normalization and mappings
- `class/TakeposShiftService.class.php`
  - Fixed webhook helper signature
  - Added webhook emissions for `shift_opened` and `shift_closed`
- `class/TakeposRefundService.class.php`
  - Fixed webhook helper signature
  - Added webhook emission for `refund_completed`
- `class/TakeposAnalyticsService.class.php`
  - Added missing migration include safety
- `class/TakeposCustomerService.class.php`
  - Fixed SQL search LIKE parsing bug

## Database
- Expanded `sql/takepos_phase3_upgrade.sql` with full Phase 3 tables:
  - sync queue/log
  - loyalty account/txn
  - device profile/terminal binding
  - printer profile
  - api token
  - webhook/webhook log

## Security / Hardening
- Maintained fail-closed guard posture for new state-changing operations
- Added feature/permission gating consistency for API/Webhook admin UI
- Preserved strict numeric handling in Phase 3 workflows
- Full PHP lint pass completed successfully after changes

## Packaging Cleanup
- Removed backup files, error logs, old nested patch zips, and `_build_phase2b`
- Produced clean installable package:
  - `_dist/takepos_phase3_patch_20260311_clean.zip`

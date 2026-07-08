# TAKEPOS

## Features
Add a Touch Screen POS (Point Of Sale) to your ERP.

<!--
![Screenshot takepos](img/screenshot_takepos.png?raw=true "TakePos"){imgmd}
-->

## Upgrade Notes
- Phase 1: README-PHASE1-CRITICAL-STABILIZATION.md
- Phase 2A: README-PHASE2A-SHIFT-CASH-STORE.md
- Phase 2B: README-PHASE2B-REFUND-EXCHANGE-ANALYTICS.md
- Phase 3: README-PHASE3-OFFLINE-LOYALTY-DEVICE-API.md

## UTF-8 / Arabic Hardening
- New runtime helper: `class/TakeposUtf8.class.php`
- New admin audit/fix page: `admin/utf8.php`
- New SQL migration: `sql/takepos_utf8_catalog_upgrade.sql`
- Product/customer search endpoints now normalize Unicode input and return UTF-8 JSON safely.
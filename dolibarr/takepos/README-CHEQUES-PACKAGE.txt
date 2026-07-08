TakePOS Cheques + Translation Package v2

Included in v2
- Fixed shortcut translation loading and broken labels such as TakeposShortcutPurchases.
- Added advanced cheque tracking screen: /takepos/cheques.php
- Added due-date dashboard cards:
  - overdue
  - due today
  - next 7 days
- Added richer filters:
  - status
  - supplier
  - collection date from / to
  - due window
  - search
- Added professional print report for the currently filtered results.
- Added professional single-cheque print layout.
- Added direct button in purchases.php to create a supplier cheque from the current purchase receipt with prefilled:
  - supplier
  - purchase receipt
  - amount
- Added service class update with purchase lookup and due-state logic.
- Added SQL index for linked purchase.

Files
- takepos/cheques.php
- takepos/class/TakeposChequeService.class.php
- takepos/sql/takepos_cheques_upgrade.sql
- takepos/langs/ar_JO/takeposcustom.lang
- takepos/langs/en_US/takeposcustom.lang
- takepos/purchases.php

Installation
1. Copy all files from this package into your TakePOS module path.
2. Open /takepos/cheques.php once.
3. If runtime schema creation is blocked, execute:
   takepos/sql/takepos_cheques_upgrade.sql
4. Clear Dolibarr cache and refresh the browser.

Notes
- Existing cheque data remains compatible.
- Report printing follows the same filters shown on screen.
- When you open the cheque screen from a purchase, the form is prefilled for faster entry.

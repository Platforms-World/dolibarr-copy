TakePOS package fixes prepared against Excel issue list.

Implemented in code:
- Held invoices isolation per user and terminal.
- Search/barcode flow hardened for scanners and browsers that do not expose window.event.
- Calculator/OpenDrawer/Search2 published on window to avoid undefined handler issues.
- All action buttons shown directly without Next pagination.
- Top navigation right section layout hardened to remain visible.
- Product labels forced visible in product grid.
- Free-text amount input now explicitly accepts decimal values below 1.

Still requires live validation after deployment:
- Product-entry/edit screen issue mentioned in video (not included in uploaded package context).
- Multi-barcode storage requires the SQL table in sql/llx_takepos_product_barcode.sql to exist and any product-entry UI to save aliases.
- Auto-print depends on existing Dolibarr/TakePOS print settings (TAKEPOS_AUTO_PRINT_TICKETS).

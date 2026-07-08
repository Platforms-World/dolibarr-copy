kafo-ERP Import Export Product
==============================

Dolibarr external module for product CSV template export and ZIP product import.

Features
--------
- Download a CSV template with required columns and sample rows.
- Import products from a ZIP package containing:
  - products.csv
  - optional images/ directory
- For each row the importer can:
  - create product
  - assign existing category
  - set initial stock in an existing warehouse
  - attach product image
- Import report with status per line (OK, SKIPPED, ERROR).
- Optional quick action buttons on product list/card pages via hooks.

Required CSV Header
-------------------
ref,label,barcode,price_ht,tva_tx,qty,category_ref,warehouse_ref,image,description

Sample rows
-----------
10001,Coca Cola 330ml,625100001,0.350,16,120,DRINKS,MAIN,10001.jpg,Can
10002,Pepsi 330ml,625100002,0.350,16,100,DRINKS,MAIN,10002.jpg,Can

Installation
------------
1. Copy folder `kafoerpproductimportexport` into Dolibarr `htdocs/custom/`.
2. In Dolibarr: Home > Setup > Modules/Applications.
3. Enable module: `kafo-ERP Import Export Product`.
4. Assign rights to target users/roles:
   - Read
   - Import
   - Export

Usage
-----
1. Go to Products > kafo-ERP Import/Export Product.
2. Click `Download CSV Template` and prepare your file.
3. Build ZIP with `products.csv` and optional `images` folder.
4. Upload ZIP and click `Import ZIP`.
5. Review import report lines and messages.

Notes
-----
- Category must already exist (lookup by ref, then label).
- Warehouse must exist when qty > 0 (lookup by ref, label, then lieu).
- Duplicate product ref rows are skipped.
- Duplicate barcode rows are rejected.
- If image name is empty, importer tries `<ref>.jpg`, `<ref>.jpeg`, `<ref>.png`.

# TakePOS — Fixes Package v3

This package contains 8 patched files that address all issues found so far.

## What's new in v3 (since v2)

**Fix 5 — Warehouse dropdown is empty in admin pages.** Three SQL queries
in the takepos module use the wrong column name (`status`) for the Dolibarr
warehouse table. The real column name is `statut` (French spelling).
That's why your Warehouse dropdown on `/takepos/admin/branches.php`
showed only "— None —" even though 4 warehouses exist in your DB.
Fixed in: `admin/branches.php`, `admin/stores.php`,
`class/TakeposPurchaseService.class.php`.

## All 5 fixes in this package

1. **Translations** — 86 missing keys added to canonical
   `langs/<lang>/takeposcustom.lang` for EN and AR.
2. **Stock badge JS** — added `window.takeposRefreshStockBadges()` so
   badges refresh after a sale.
3. **`TakeposFinalizePaymentUi`** — calls the badge refresh on payment success.
4. **`TakeposBranchService::grantTakePosRight`** — grants `takepos.editlines`
   and `takepos.editorderedlines` so branch cashiers can change quantity,
   price, and discount on cart lines.
5. **NEW — `entrepot.status` → `entrepot.statut`** — 3 SQL queries fixed
   so the Warehouse dropdown actually lists the warehouses.

## Files in this package

| File | Replaces |
| ---- | -------- |
| `langs/en_US/takeposcustom.lang`            | same path in takepos module |
| `langs/ar_JO/takeposcustom.lang`            | same |
| `js/takepos_stock_badges.js`                | same |
| `index.php`                                 | takepos root index.php |
| `class/TakeposBranchService.class.php`      | same |
| `class/TakeposPurchaseService.class.php`    | same |
| `admin/branches.php`                        | same |
| `admin/stores.php`                          | same |

Extract the zip on top of your takepos folder, overwriting these 8 files.
Then hard-refresh the browser (Ctrl-F5).

---

## After installing — order of operations

Do these steps in order. Each step verifies the fix before moving on.

### 1. Confirm warehouse dropdown shows warehouses now

- Go to `/takepos/admin/branches.php`.
- Look at the Warehouse dropdown for any branch row.
- Before v3 it showed only "— None —". After v3 it should also list:
  `150`, `main`, `TEST`, `TEST2` (and any others you have).

### 2. Assign each branch to a warehouse

- For branch **TEST** → pick warehouse `TEST` → click **SAVE**.
- For branch **TEST2** → pick warehouse `TEST2` → click **SAVE**.
- For branch **TEST3** → no `TEST3` warehouse exists yet, so either:
  - Create one first at `/product/stock/card.php?action=create`
    with a unique name (e.g. `TEST3` or `BR-TEST3`), then assign it.
  - Or reuse one of the existing warehouses (`150`, `main`).
- The "⚠ No warehouse" warning under each row should disappear.

### 3. Sync existing branch user permissions

- On the same `/takepos/admin/branches.php` page, click the
  **"Sync permissions"** button at the top. This pushes the new
  `takepos.editlines` / `editorderedlines` rights to your already-created
  branch users (Fix 4).

### 4. Test the branch terminal

- Log out, log back in as a branch user.
- Add a product to the cart, press `+` to bump qty from 1 to 2.
- The qty changes without the red "Not enough permissions" error.

### 5. Open "All Branches Stock"

- Now the page should show a real table with one column per branch,
  not the "No branches configured" message.

---

## Things v3 does NOT fix (deliberate, separate work)

### Branch sales still don't decrement branch stock

This is GAP #1 from the original analysis report. Even with all v3
fixes applied, when a branch user completes a sale, the stock in their
branch warehouse will NOT go down. The code in `ajax/checkstock.php` and
`invoice.php` explicitly skips stock checks and stock movement for
branch users.

Fixing this is a meaningful code change in two files. Let me know when
you want it done — it will be Fix 6 in a v4 package.

### "Stock change disabled" banner on a new terminal

If you ever set up a new terminal and see this banner, it's the same
config issue we walked through earlier:

1. Go to `/takepos/admin/terminal.php` for that terminal.
2. Turn **OFF** "Disable stock decrease when a sale is done from POS".
3. Set "Force and restrict warehouse to use for stock decrease" to a
   real warehouse.
4. Save.

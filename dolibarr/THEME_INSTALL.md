# TakePOS Custom Theme — Installation Guide

This package reskins the **TakePOS** Dolibarr module to match the clean, modern layout
defined in `takepos_redesign_final.html`.

---

## Files Added

```
takepos/
├── css/
│   └── takepos_custom.css   ← Main design system (CSS variables, layout, components)
├── js/
│   └── takepos_custom.js    ← UI interactions (drawer, keypad, clock, toast)
├── lib/
│   └── takepos_theme.php    ← PHP helper to inject CSS/JS into admin pages
└── index.php                ← Rebuilt main POS terminal (drop-in replacement)
```

---

## Installation

### Step 1 — Copy files into Dolibarr
Copy all files maintaining the directory structure into your Dolibarr install:

```
/var/www/html/htdocs/takepos/css/takepos_custom.css
/var/www/html/htdocs/takepos/js/takepos_custom.js
/var/www/html/htdocs/takepos/lib/takepos_theme.php
/var/www/html/htdocs/takepos/index.php   ← replaces existing file
```

> **Backup first:** Before overwriting `index.php`, backup your original at `index.php.bak`.

---

### Step 2 — Apply theme to Admin pages (optional)

To make the admin pages also use the design system, add one line inside each
admin PHP file right after the `llxHeader()` call:

```php
// At the top of the file, next to other require_once lines:
require_once DOL_DOCUMENT_ROOT . '/takepos/lib/takepos_theme.php';

// ... after llxHeader():
takeposInjectTheme();
```

Example (`admin/setup.php`):
```php
require_once DOL_DOCUMENT_ROOT.'/takepos/lib/takepos_theme.php';
// ...
llxHeader('', $langs->trans("CashDeskSetup"), ...);
takeposInjectTheme();
```

---

### Step 3 — Verify

1. Open `https://your-dolibarr/takepos/index.php?terminal=1`
2. You should see the new dark appbar, product grid, and cart panel.
3. All Dolibarr AJAX calls (product search, invoice creation, payment) remain intact
   because the PHP/JS backend logic is untouched.

---

## Design Tokens

All colors, spacing, and typography are controlled via CSS variables in `:root`.
To adjust them, edit `css/takepos_custom.css` at the top of the file:

| Variable         | Default   | Purpose               |
|------------------|-----------|-----------------------|
| `--primary`      | `#1e40af` | Buttons, accents      |
| `--bg-dark`      | `#0f172a` | Appbar, totals box    |
| `--bg-app`       | `#f1f4f9` | Page background       |
| `--font-ar`      | IBM Plex Sans Arabic | Arabic text |
| `--font-num`     | JetBrains Mono | Numbers, prices |

---

## RTL Support

The theme fully supports RTL (Arabic/Hebrew) layouts. Set `<html dir="rtl">` and
the layout mirrors automatically — sidebar, cart indicator bar, drawer slide direction,
and badge positions all flip correctly.

---

## Offline / No Google Fonts

If the POS runs without internet access, replace the Google Fonts `<link>` with
the inline CSS vars helper:

```php
echo takeposGetInlineCssVars(); // outputs :root { ... } with fallback system fonts
```

The layout still works with system Arabic fonts (Segoe UI Arabic, Tahoma).

---

## Browser Support

Chrome 90+, Firefox 88+, Safari 14+, Edge 90+.
Requires CSS Grid and CSS Custom Properties support.

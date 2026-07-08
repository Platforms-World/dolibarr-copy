# poscore 1.0.5

Dolibarr 22 child module for POS Terminal under saascore entitlement control.

## Main entry
- /custom/poscore/pos.php

## Requirements
- saascore must be installed and enabled
- SaasRegistryService must exist
- SaasAccessService must exist

## Files included
- core/modules/modPoscore.class.php
- class/service/PosSaasBridge.php
- class/service/PosCartService.php
- ajax/search_products.php
- ajax/add_to_cart.php
- ajax/remove_from_cart.php
- ajax/clear_cart.php
- ajax/create_invoice.php
- js/pos.js.php
- css/pos.css.php
- sql/llx_poscore_cart.sql

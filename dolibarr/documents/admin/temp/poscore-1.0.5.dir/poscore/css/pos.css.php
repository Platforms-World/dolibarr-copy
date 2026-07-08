<?php
if (!defined('NOREQUIREUSER')) define('NOREQUIREUSER', '1');
if (!defined('NOREQUIREDB')) define('NOREQUIREDB', '1');
if (!defined('NOREQUIRESOC')) define('NOREQUIRESOC', '1');
if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', '1');
require '../../../main.inc.php';
header('Content-Type: text/css');
?>
.poscore-layout { display:flex; gap:16px; align-items:flex-start; width:100%; box-sizing:border-box; }
.poscore-col { background:#fff; border:1px solid #ddd; padding:12px; box-sizing:border-box; border-radius:4px; }
.poscore-left { width:32%; }
.poscore-center { width:38%; }
.poscore-right { width:30%; }
.poscore-panel label { display:block; margin-bottom:4px; font-weight:600; }
.poscore-panel input, .poscore-panel select { margin-bottom:10px; }
.pos-totals { margin-top:15px; border-top:1px solid #ddd; padding-top:10px; }
.pos-totals div { display:flex; justify-content:space-between; padding:4px 0; }
.pos-grand-line { font-size:16px; font-weight:bold; border-top:1px solid #ddd; margin-top:6px; padding-top:8px; }
.pos-payment-summary { text-align:center; border:1px solid #ddd; padding:14px; background:#f8f8f8; border-radius:4px; }
#payment-grand-total { font-size:24px; font-weight:bold; margin-top:8px; }
.button-pay { min-width:90px; margin-bottom:6px; }
#pos-search-results td, #pos-cart-table td, #pos-search-results th, #pos-cart-table th { padding:8px; }

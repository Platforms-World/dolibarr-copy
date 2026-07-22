<?php
/* ==========================================================================
 * takepos/pos.php  —  Kafo POS · واجهة بيع جديدة نظيفة (Path B · Step 1)
 * --------------------------------------------------------------------------
 * صفحة مستقلة تماماً، لا تلمس index.php. تستخدم جلسة Dolibarr للمصادقة،
 * وتعيد استعمال الباك-إند الحالي:
 *   - المنتجات : ajax/ajax.php?action=getProducts|getProductsAll|search
 *   - السلة    : invoice.php (addline / deleteline / updateprice ...)
 * افتحها على:  /dolibarr/takepos/pos.php
 * ========================================================================== */

if (!defined('NOREQUIREMENU'))  define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML'))  define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX', '1');

$res = 0;
if (!$res && file_exists("../main.inc.php"))            $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php"))         $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php"))      $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
if (file_exists(__DIR__ . '/lib/takepos_help.php')) { require_once __DIR__ . '/lib/takepos_help.php'; }

global $conf, $langs, $db, $user;

// --- auth ---
if (empty($user->id)) {
    accessforbidden();
}

$langs->loadLangs(array("main", "bills", "cashdesk", "products", "stocks"));

// --- session context ---
$term         = isset($_SESSION["takeposterminal"]) ? (int) $_SESSION["takeposterminal"] : 0;
$place        = 0; // free-POS mode (no floor plan)
$terminalName = $term ? getDolGlobalString("TAKEPOS_TERMINAL_NAME_" . $term, $langs->trans("TerminalName", $term)) : '';
$baseCurrency = strtoupper(trim(!empty($conf->currency) ? $conf->currency : 'JOD'));
$cashAcct = function_exists('takeposResolveTerminalBankAccountId') ? (int) takeposResolveTerminalBankAccountId('CASH', $term) : 0;
$cardAcct = function_exists('takeposResolveTerminalBankAccountId') ? (int) takeposResolveTerminalBankAccountId('CB', $term) : 0;
$activeCurr   = isset($_SESSION['takeposcustomercurrency']) ? strtoupper((string) $_SESSION['takeposcustomercurrency']) : '';
$currLabel    = ($activeCurr !== '' && $activeCurr !== $baseCurrency) ? $activeCurr : $baseCurrency;

// direction
$dir = (strpos((string) $langs->defaultlang, 'ar') === 0) ? 'rtl' : 'ltr';

// --- main categories (top level under configured root) ---
$rootcat  = getDolGlobalInt('TAKEPOS_ROOT_CATEGORY_ID');
$catobj   = new Categorie($db);
$allcats  = $catobj->get_full_arbo('product', ($rootcat > 0 ? $rootcat : 0), 1);
$mainCats = array();
if (is_array($allcats) && count($allcats)) {
    $minlevel = null;
    foreach ($allcats as $c) {
        $lv = isset($c['level']) ? (int) $c['level'] : 1;
        if ($minlevel === null || $lv < $minlevel) $minlevel = $lv;
    }
    foreach ($allcats as $c) {
        $lv = isset($c['level']) ? (int) $c['level'] : 1;
        if ($lv === $minlevel) {
            $mainCats[] = array(
                'id'    => (int) (isset($c['id']) ? $c['id'] : (isset($c['rowid']) ? $c['rowid'] : 0)),
                'label' => (string) (isset($c['label']) ? $c['label'] : ''),
            );
        }
    }
}

// --- config passed to JS ---
$token       = newToken();
$ajaxUrl     = DOL_URL_ROOT . '/takepos/ajax/ajax.php';
$invoiceUrl  = DOL_URL_ROOT . '/takepos/invoice.php';
$logoutUrl   = DOL_URL_ROOT . '/user/logout.php?token=' . newToken() . '&urlfrom=' . urlencode('/takepos/pos.php');
$faCss       = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css';
$cssUrl      = DOL_URL_ROOT . '/takepos/css/pos_v3.css?v=1';
$jsUrl       = DOL_URL_ROOT . '/takepos/js/pos_v3.js?v=1';
$jsOfflineUrl = DOL_URL_ROOT . '/takepos/js/kf_offline.js?v=1';

$userInitials = strtoupper(mb_substr(trim($user->firstname . $user->lastname) ?: $user->login, 0, 2));

// category accent palette (cycled)
$catColors = array('#3b82f6', '#f59e0b', '#10b981', '#8b5cf6', '#ef4444', '#ec4899', '#06b6d4', '#14b8a6');

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="<?php echo $dir === 'rtl' ? 'ar' : 'en'; ?>" dir="<?php echo $dir; ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Kafo POS</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600;700&display=swap">
    <link rel="stylesheet" href="<?php echo dol_escape_htmltag($faCss); ?>">
    <link rel="stylesheet" href="<?php echo dol_escape_htmltag($cssUrl); ?>">
</head>
<body class="kfpos">
<div class="kf-app">

    <!-- ===== COMMAND BAR ===== -->
    <header class="kf-cmd">
        <div class="kf-term" id="kfTerminalBtn" title="<?php echo dol_escape_htmltag($langs->trans('ChangeTerminal') ?: 'Terminal'); ?>">
            <span class="ic"><i class="fa-solid fa-cash-register"></i></span>
            <span class="meta"><b><?php echo dol_escape_htmltag($terminalName ?: ('Terminal ' . $term)); ?></b><small class="num"><?php echo dol_print_date(dol_now(), 'day'); ?></small></span>
        </div>
        <button class="kf-chip" id="kfCurrencyBtn"><i class="fa-solid fa-coins"></i><span><?php echo dol_escape_htmltag($currLabel); ?></span></button>
        <button class="kf-chip" id="kfCustomerBtn"><i class="fa-solid fa-building"></i><span><?php echo dol_escape_htmltag($langs->trans('Customer')); ?></span></button>

        <div class="kf-search">
            <i class="fa-solid fa-magnifying-glass sx"></i>
            <input type="text" id="kfSearch" autocomplete="off" placeholder="<?php echo dol_escape_htmltag($langs->trans('Search') . '…'); ?>">
            <span class="key num">/</span>
        </div>

        <div class="kf-tools">
            <a class="kf-ic" href="<?php echo DOL_URL_ROOT . '/'; ?>" target="backoffice" rel="opener" title="Back office"><i class="fa-solid fa-house"></i></a>
            <div class="kf-user">
                <span class="av"><?php echo dol_escape_htmltag($userInitials); ?></span>
                <span class="meta"><b><?php echo dol_escape_htmltag($user->getFullName($langs) ?: $user->login); ?></b><small><?php echo dol_escape_htmltag($user->login); ?></small></span>
                <a class="lo" href="<?php echo dol_escape_htmltag($logoutUrl); ?>" title="<?php echo dol_escape_htmltag($langs->trans('Logout')); ?>"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
            </div>
        </div>
    </header>

    <div class="kf-body">

        <!-- ===== CART ===== -->
        <aside class="kf-cart">
            <div class="kf-cart-head">
                <h3><?php echo dol_escape_htmltag($langs->trans('SalesCart') ?: 'Sales Cart'); ?></h3>
                <span class="order num" id="kfInvoiceLabel">#—</span>
                <div class="tools">
                    <button title="Hold"><i class="fa-solid fa-pause"></i></button>
                    <button title="Discount"><i class="fa-solid fa-percent"></i></button>
                    <button title="Note"><i class="fa-regular fa-note-sticky"></i></button>
                </div>
            </div>

            <div class="kf-cust" id="kfCustomer">
                <span class="av">T</span>
                <span class="meta"><small><?php echo dol_escape_htmltag($langs->trans('Customer')); ?></small><b><?php echo dol_escape_htmltag($langs->trans('TakeposGenericCustomer') ?: 'Generic customer'); ?></b></span>
                <button class="chg"><?php echo dol_escape_htmltag($langs->trans('Change') ?: 'Change'); ?></button>
            </div>

            <div class="kf-cart-lines" id="kfCartLines">
                <div class="kf-empty"><i class="fa-solid fa-basket-shopping"></i><span><?php echo dol_escape_htmltag($langs->trans('CartIsEmpty') ?: 'Cart is empty'); ?></span></div>
            </div>

            <div class="kf-cart-foot">
                <div class="row"><span><?php echo dol_escape_htmltag($langs->trans('SubTotal') ?: 'Subtotal'); ?></span><span class="num" id="kfSub">0.00</span></div>
                <div class="row"><span><?php echo dol_escape_htmltag($langs->trans('Tax') ?: 'Tax'); ?></span><span class="num" id="kfTax">0.00</span></div>
                <div class="kf-total">
                    <div class="lbl"><?php echo dol_escape_htmltag($langs->trans('TotalTTC') ?: 'Total Due'); ?><b><?php echo dol_escape_htmltag($langs->trans('IncludingTax') ?: 'incl. tax'); ?></b></div>
                    <div class="amt"><span class="num" id="kfTotal">0.00</span> <em><?php echo dol_escape_htmltag($currLabel); ?></em></div>
                </div>
                <div class="kf-actions">
                    <button class="btn danger" id="kfCancel"><i class="fa-solid fa-trash-can"></i> <?php echo dol_escape_htmltag($langs->trans('Cancel')); ?></button>
                    <button class="btn ghost" id="kfHold"><i class="fa-solid fa-pause"></i> <?php echo dol_escape_htmltag($langs->trans('Hold') ?: 'Hold'); ?></button>
                    <button class="btn pay" id="kfPay"><i class="fa-solid fa-credit-card"></i> <?php echo dol_escape_htmltag($langs->trans('Pay') ?: 'Pay'); ?> <span class="num" id="kfPayAmt">0.00</span> <?php echo dol_escape_htmltag($currLabel); ?></button>
                </div>
            </div>

            <!-- hidden source-of-truth fragment from invoice.php -->
            <div id="poslines" style="display:none"></div>
        </aside>

        <!-- ===== CATALOG ===== -->
        <main class="kf-catalog">
            <div class="kf-cat-head">
                <div class="bc"><i class="fa-solid fa-house"></i> <?php echo dol_escape_htmltag($langs->trans('Catalog') ?: 'Catalog'); ?> › <b id="kfCatName"><?php echo dol_escape_htmltag($langs->trans('AllProducts') ?: 'All products'); ?></b></div>
                <span class="count" id="kfCount"></span>
            </div>

            <div class="kf-chips" id="kfChips">
                <button class="chip on" data-cat="0"><i class="fa-solid fa-box-open"></i> <?php echo dol_escape_htmltag($langs->trans('All') ?: 'All'); ?></button>
                <?php foreach ($mainCats as $i => $c): $col = $catColors[$i % count($catColors)]; ?>
                    <button class="chip" data-cat="<?php echo (int) $c['id']; ?>"><span class="d" style="background:<?php echo $col; ?>"></span> <?php echo dol_escape_htmltag($c['label']); ?></button>
                <?php endforeach; ?>
            </div>

            <div class="kf-grid" id="kfGrid"></div>

            <div class="kf-loading" id="kfLoading"><i class="fa-solid fa-spinner fa-spin"></i> <?php echo dol_escape_htmltag($langs->trans('Loading') . '…'); ?></div>
        </main>

        <!-- ===== ACTION RAIL ===== -->
        <aside class="kf-rail">
            <div class="grp"><?php echo dol_escape_htmltag($langs->trans('Sale') ?: 'Sale'); ?></div>
            <button class="rkey"><i class="fa-solid fa-plus"></i><span><?php echo dol_escape_htmltag($langs->trans('New') ?: 'New'); ?></span></button>
            <button class="rkey pay" id="kfRailPay"><i class="fa-solid fa-money-bill"></i><span><?php echo dol_escape_htmltag($langs->trans('Payment') ?: 'Pay'); ?></span></button>
            <button class="rkey"><i class="fa-solid fa-coins"></i><span><?php echo dol_escape_htmltag($langs->trans('Cash') ?: 'Cash'); ?></span></button>
            <button class="rkey"><i class="fa-solid fa-credit-card"></i><span><?php echo dol_escape_htmltag($langs->trans('Card') ?: 'Card'); ?></span></button>
            <div class="grp"><?php echo dol_escape_htmltag($langs->trans('Manage') ?: 'Manage'); ?></div>
            <button class="rkey"><i class="fa-solid fa-clock-rotate-left"></i><span><?php echo dol_escape_htmltag($langs->trans('History') ?: 'History'); ?></span></button>
            <button class="rkey"><i class="fa-solid fa-list-check"></i><span><?php echo dol_escape_htmltag($langs->trans('Held') ?: 'Held'); ?></span></button>
            <button class="rkey"><i class="fa-solid fa-business-time"></i><span><?php echo dol_escape_htmltag($langs->trans('Shift') ?: 'Shift'); ?></span></button>
            <button class="rkey"><i class="fa-solid fa-layer-group"></i><span><?php echo dol_escape_htmltag($langs->trans('More') ?: 'More'); ?></span></button>
        </aside>

    </div>

    <!-- ===== PAYMENT MODAL ===== -->
    <div class="kf-ov" id="kfPayOv">
        <div class="kf-modal">
            <div class="kf-modal-head"><h3>إتمام الدفع</h3><button class="x" id="kfPayClose">&times;</button></div>
            <div class="kf-modal-body">

                <div class="kf-pay-due">
                    <span class="k">الإجمالي المستحق</span>
                    <span class="amt"><span class="num" id="kfPayDue">0.00</span> <em><?php echo dol_escape_htmltag($currLabel); ?></em></span>
                </div>

                <div class="kf-pay-methods">
                    <button class="kf-pm on" data-method="LIQ"><i class="fa-solid fa-money-bill-wave"></i> نقدي</button>
                    <button class="kf-pm" data-method="CB"><i class="fa-solid fa-credit-card"></i> بطاقة</button>
                </div>

                <div id="kfCashWrap">
                    <div class="kf-pay-field">
                        <label>المبلغ المستلم</label>
                        <input type="text" id="kfReceived" class="num" inputmode="decimal">
                    </div>
                    <div class="kf-quick" id="kfQuick">
                        <button data-q="exact">بالضبط</button>
                        <button data-q="5">+5</button>
                        <button data-q="10">+10</button>
                        <button data-q="20">+20</button>
                        <button data-q="50">+50</button>
                    </div>
                    <div class="kf-pay-change"><span>الباقي للعميل</span><b class="num" id="kfChange">0.00</b></div>
                    <div class="kf-keypad" id="kfKeypad">
                        <button>1</button><button>2</button><button>3</button>
                        <button>4</button><button>5</button><button>6</button>
                        <button>7</button><button>8</button><button>9</button>
                        <button data-k=".">.</button><button>0</button><button data-k="back"><i class="fa-solid fa-delete-left"></i></button>
                    </div>
                </div>

                <button class="kf-pay-confirm" id="kfPayConfirm">
                    <span class="lbl"><i class="fa-solid fa-circle-check"></i> تأكيد الدفع</span>
                    <span class="amt num" id="kfPayConfirmAmt">0.00</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    window.KAFO = {
        token:      <?php echo json_encode($token); ?>,
        ajaxUrl:    <?php echo json_encode($ajaxUrl); ?>,
        invoiceUrl: <?php echo json_encode($invoiceUrl); ?>,
        term:       <?php echo json_encode((string) $term); ?>,
        place:      <?php echo json_encode((string) $place); ?>,
        currency:   <?php echo json_encode($currLabel); ?>,
        rtl:        <?php echo json_encode($dir === 'rtl'); ?>,
        cashAcct:   <?php echo json_encode((string) $cashAcct); ?>,
        cardAcct:   <?php echo json_encode((string) $cardAcct); ?>,
        debug:      true
    };
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register(
            <?php echo json_encode(DOL_URL_ROOT . '/takepos/kf_sw.js'); ?>,
            { scope: <?php echo json_encode(DOL_URL_ROOT . '/takepos/'); ?> }
        ).catch(function (e) { console.warn('[KFOffline] service worker registration failed:', e); });
    }
</script>
<script src="<?php echo dol_escape_htmltag($jsOfflineUrl); ?>"></script>
<script src="<?php echo dol_escape_htmltag($jsUrl); ?>"></script>
</body>
</html>
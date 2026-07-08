<?php
/**
 * topbar_v2.php — Kafo POS v2 shared navigation bar
 * require هذا الملف داخل <?php ?> بعد <body> مباشرة
 *
 * المتغيرات الاختيارية (تُعرَّف قبل require):
 *   $v2PageTitle  string  عنوان الصفحة
 *   $v2PageIcon   string  FA class  e.g. 'fa-clock-rotate-left'
 *   $v2PageSub    string  وصف مختصر
 *   $v2BackUrl    string  رابط زر الرجوع
 */
$_v2PosUrl  = DOL_URL_ROOT . '/takepos/pos_v2.php';
$_v2Logout  = DOL_URL_ROOT . '/user/logout.php?token=' . newToken();
$_v2Term    = isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : 0;
$_v2Date    = dol_print_date(dol_now(), 'day');
$_v2Init    = isset($user) ? strtoupper(mb_substr(trim(($user->firstname ?? '') . ($user->lastname ?? '')) ?: ($user->login ?? 'KF'), 0, 2, 'UTF-8')) : 'KF';
$_v2Name    = isset($user) ? ($user->getFullName($langs) ?: ($user->login ?? '')) : '';
$_v2Login   = isset($user) ? ($user->login ?? '') : '';
$_v2Current = basename($_SERVER['PHP_SELF'] ?? '');

$_v2Nav = array(
    array('fa-cash-register',     'TakeposPOS',            DOL_URL_ROOT . '/takepos/pos_v2.php',      'pos_v2.php'),
    array('fa-clock-rotate-left', 'TakeposHistoryTitle',   DOL_URL_ROOT . '/takepos/history_v2.php',  'history_v2.php'),
    array('fa-business-time',     'TakeposShiftTitle',     DOL_URL_ROOT . '/takepos/shifts_v2.php',   'shifts_v2.php'),
    array('fa-rotate-left',       'TakeposRefundTitle',    DOL_URL_ROOT . '/takepos/refunds_v2.php',  'refunds_v2.php'),
    array('fa-chart-line',        'TakeposReportsTitle',   DOL_URL_ROOT . '/takepos/reports_v2.php',  'reports_v2.php'),
    array('fa-money-bill-wave',   'TakeposExpenseTitle',   DOL_URL_ROOT . '/takepos/expenses_v2.php', 'expenses_v2.php'),
);
?>
<!-- إخفاء الـ topbar عند الفتح داخل modal iframe -->
<style>
.kfv2-in-modal .kfv2-nav,
.kfv2-in-modal .kfv2-page-head { display:none !important; }
.kfv2-in-modal .kfv2-page-body { padding-top:14px !important; }
</style>
<script>
(function(){
    try { if(window.self!==window.top){ document.body.classList.add('kfv2-in-modal'); } }
    catch(e){ document.body.classList.add('kfv2-in-modal'); }
})();
</script>
<nav class="kfv2-nav">
    <a class="kfv2-brand" href="<?php echo dol_escape_htmltag($_v2PosUrl); ?>">
        <span class="logo">K</span>
        <span class="name">Kafo POS</span>
    </a>

    <div class="kfv2-navlinks">
        <?php foreach ($_v2Nav as $item): ?>
            <a class="kfv2-navlink<?php echo (strpos($_v2Current, $item[3]) !== false) ? ' active' : ''; ?>"
               href="<?php echo dol_escape_htmltag($item[2]); ?>">
                <i class="fa-solid <?php echo dol_escape_htmltag($item[0]); ?>"></i>
                <span><?php echo dol_escape_htmltag($langs->transnoentities($item[1]) ?: $item[1]); ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="kfv2-nav-right">
        <?php if ($_v2Term): ?>
        <div class="kfv2-terminal-badge">
            <i class="fa-solid fa-display"></i>
            <span>T<?php echo (int) $_v2Term; ?></span>
            <small><?php echo dol_escape_htmltag($_v2Date); ?></small>
        </div>
        <?php endif; ?>
        <div class="kfv2-user-chip">
            <span class="av"><?php echo dol_escape_htmltag($_v2Init); ?></span>
            <div class="info">
                <b><?php echo dol_escape_htmltag($_v2Name); ?></b>
                <small><?php echo dol_escape_htmltag($_v2Login); ?></small>
            </div>
            <a class="kfv2-logout" href="<?php echo dol_escape_htmltag($_v2Logout); ?>" title="Logout">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </a>
        </div>
    </div>
</nav>

<?php if (!empty($v2PageTitle)): ?>
<div class="kfv2-page-head">
    <?php if (!empty($v2PageIcon)): ?>
        <div class="icon"><i class="fa-solid <?php echo dol_escape_htmltag($v2PageIcon); ?>"></i></div>
    <?php endif; ?>
    <div>
        <h1><?php echo dol_escape_htmltag($v2PageTitle); ?></h1>
        <?php if (!empty($v2PageSub)): ?>
            <p><?php echo dol_escape_htmltag($v2PageSub); ?></p>
        <?php endif; ?>
    </div>
    <?php if (!empty($v2BackUrl)): ?>
        <a class="kfv2-back-btn" href="<?php echo dol_escape_htmltag($v2BackUrl); ?>">
            <i class="fa-solid fa-arrow-right"></i>
        </a>
    <?php endif; ?>
</div>
<?php endif; ?>

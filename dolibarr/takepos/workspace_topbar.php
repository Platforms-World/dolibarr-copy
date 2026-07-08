<?php
/**
 * workspace_topbar.php — Kafo POS shared navigation topbar
 * Include هذا الملف في كل صفحة workspace بعد <body>
 * 
 * المتغيرات المطلوبة: $user, $langs, $conf
 * اختيارية: $wsPageTitle, $wsPageIcon, $wsPageSub, $wsBackUrl
 */
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');

$_wsPosUrl   = DOL_URL_ROOT . '/takepos/pos.php';
$_wsLogout   = DOL_URL_ROOT . '/user/logout.php?token=' . newToken();
$_wsInitials = isset($user) ? strtoupper(mb_substr(trim($user->firstname . $user->lastname) ?: $user->login, 0, 2)) : 'KF';
$_wsFullName = isset($user) ? ($user->getFullName($langs) ?: $user->login) : '';
$_wsLogin    = isset($user) ? $user->login : '';
$_wsTerm     = isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : 0;
$_wsDate     = dol_print_date(dol_now(), 'day');

// Nav items: [icon, label_key, url, active_match]
$_wsNavItems = array(
    array('fa-cash-register',    'TakeposPOS',       DOL_URL_ROOT . '/takepos/pos.php',      'pos.php'),
    array('fa-clock-rotate-left','TakeposHistoryTitle', DOL_URL_ROOT . '/takepos/history.php', 'history.php'),
    array('fa-business-time',    'TakeposShiftManagement', DOL_URL_ROOT . '/takepos/shifts.php', 'shifts.php'),
    array('fa-rotate-left',      'TakeposRefundTitle', DOL_URL_ROOT . '/takepos/refunds.php', 'refunds.php'),
    array('fa-chart-line',       'TakeposReportsTitle', DOL_URL_ROOT . '/takepos/reports.php', 'reports.php'),
    array('fa-money-bill-wave',  'TakeposExpensesTitle', DOL_URL_ROOT . '/takepos/expenses.php','expenses.php'),
);
$_wsCurrent = basename($_SERVER['PHP_SELF'] ?? '');
?>
<nav class="kf-ws-nav">
    <a class="kf-ws-brand" href="<?php echo dol_escape_htmltag($_wsPosUrl); ?>">
        <span class="logo">K</span>
        <span class="name">Kafo POS</span>
    </a>

    <div class="kf-ws-items">
        <?php foreach ($_wsNavItems as $item): ?>
            <?php $isActive = (strpos($_wsCurrent, $item[3]) !== false); ?>
            <a class="kf-ws-item<?php echo $isActive ? ' active' : ''; ?>"
               href="<?php echo dol_escape_htmltag($item[2]); ?>">
                <i class="fa-solid <?php echo dol_escape_htmltag($item[0]); ?>"></i>
                <span><?php echo dol_escape_htmltag($langs->transnoentities($item[1]) ?: $item[1]); ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="kf-ws-right">
        <div class="kf-ws-term">
            <i class="fa-solid fa-display"></i>
            <span><?php echo $_wsTerm ? dol_escape_htmltag('T' . $_wsTerm) : '—'; ?></span>
            <small><?php echo dol_escape_htmltag($_wsDate); ?></small>
        </div>
        <div class="kf-ws-user">
            <span class="av"><?php echo dol_escape_htmltag($_wsInitials); ?></span>
            <div class="meta">
                <b><?php echo dol_escape_htmltag($_wsFullName); ?></b>
                <small><?php echo dol_escape_htmltag($_wsLogin); ?></small>
            </div>
            <a class="lo" href="<?php echo dol_escape_htmltag($_wsLogout); ?>" title="Logout">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </a>
        </div>
    </div>
</nav>
<?php if (!empty($wsPageTitle)): ?>
<div class="kf-ws-page-head">
    <?php if (!empty($wsPageIcon)): ?>
        <div class="kf-ws-page-ic"><i class="fa-solid <?php echo dol_escape_htmltag($wsPageIcon); ?>"></i></div>
    <?php endif; ?>
    <div class="kf-ws-page-titlewrap">
        <h1 class="kf-ws-page-title"><?php echo dol_escape_htmltag($wsPageTitle); ?></h1>
        <?php if (!empty($wsPageSub)): ?>
            <p class="kf-ws-page-sub"><?php echo dol_escape_htmltag($wsPageSub); ?></p>
        <?php endif; ?>
    </div>
    <?php if (!empty($wsBackUrl)): ?>
        <a class="kf-ws-back" href="<?php echo dol_escape_htmltag($wsBackUrl); ?>">
            <i class="fa-solid fa-arrow-right"></i>
        </a>
    <?php endif; ?>
</div>
<?php endif; ?>

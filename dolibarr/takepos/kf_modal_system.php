<?php
/* ==========================================================================
 * KAFO POS — Modal Shell System (inject into pos_v2.php before </body>)
 * كل صفحة تفتح في modal أنيق داخل الـ POS بدون مغادرة الصفحة
 * ========================================================================== */
$posModalPages = array(
    'history'    => array('fa-clock-rotate-left', 'سجل المبيعات',          DOL_URL_ROOT.'/takepos/history_v2.php',             '#1d4ed8'),
    'held'       => array('fa-list-check',        'الطلبات المعلقة',        DOL_URL_ROOT.'/takepos/held_v2.php',                '#9333ea'),
    'shifts'     => array('fa-business-time',     'الورديات',              DOL_URL_ROOT.'/takepos/shifts_v2.php',              '#0891b2'),
    'refunds'    => array('fa-rotate-left',        'الاسترجاع',             DOL_URL_ROOT.'/takepos/refunds_v2.php',             '#dc2626'),
    'reports'    => array('fa-chart-line',         'التقارير',              DOL_URL_ROOT.'/takepos/reports_v2.php',             '#16a34a'),
    'expenses'   => array('fa-money-bill-wave',    'المصروفات',             DOL_URL_ROOT.'/takepos/expenses_v2.php',            '#d97706'),
    'expense_ledger' => array('fa-book',           'دفتر المصروفات',        DOL_URL_ROOT.'/takepos/expense_ledger_v2.php',      '#d97706'),
    'exchange'   => array('fa-right-left',         'الاستبدال',             DOL_URL_ROOT.'/takepos/exchange_v2.php',            '#7c3aed'),
    'loyalty'    => array('fa-id-card',            'برنامج الولاء',         DOL_URL_ROOT.'/takepos/loyalty_v2.php',             '#e11d48'),
    'purchases'  => array('fa-truck-arrow-right',  'المشتريات',             DOL_URL_ROOT.'/takepos/purchases_v2.php',           '#0f766e'),
    'cheques'    => array('fa-money-check',        'الشيكات',               DOL_URL_ROOT.'/takepos/cheques_v2.php',             '#1d4ed8'),
    'sync'       => array('fa-cloud-arrow-up',     'قائمة المزامنة',        DOL_URL_ROOT.'/takepos/sync_queue_v2.php',          '#0891b2'),
    'stock_overview' => array('fa-warehouse',       'نظرة المخزون',         DOL_URL_ROOT.'/takepos/stock_overview_v2.php',      '#92400e'),
    'stock_count'    => array('fa-clipboard-check', 'جرد المخزون',          DOL_URL_ROOT.'/takepos/stock_count_v2.php',         '#065f46'),
    'stock_transfer' => array('fa-right-left',      'نقل المخزون',          DOL_URL_ROOT.'/takepos/stock_transfer_v2.php',      '#1e40af'),
    'stock_recon'    => array('fa-scale-balanced',  'تسوية المخزون',        DOL_URL_ROOT.'/takepos/stock_reconciliation_v2.php','#7c3aed'),
    'stock_branches' => array('fa-layer-group',     'مخزون الفروع',         DOL_URL_ROOT.'/takepos/stock_all_branches_v2.php',  '#0f766e'),
    'tax_rates'  => array('fa-percent',            'معدلات الضريبة',        DOL_URL_ROOT.'/takepos/tax_rates_v2.php',           '#dc2626'),
);
?>

<!-- ═══════════════════════════════════════════════
     KAFO MODAL SHELL — The Beautiful Page Container
     ═══════════════════════════════════════════════ -->

<!-- Backdrop -->
<div id="kfModalBackdrop"></div>

<!-- Modal Shell -->
<div id="kfModalShell" role="dialog" aria-modal="true" aria-labelledby="kfModalTitle">

    <!-- Top accent bar (color changes per page) -->
    <div id="kfModalAccent"></div>

    <!-- Header -->
    <div id="kfModalHead">
        <div id="kfModalIcon"><i class="fa-solid fa-circle" id="kfModalIconI"></i></div>
        <div id="kfModalTitleWrap">
            <h2 id="kfModalTitle">—</h2>
            <div id="kfModalBreadcrumb">Kafo POS</div>
        </div>
        <div id="kfModalActions">
            <button id="kfModalPopout" title="فتح في تاب جديد">
                <i class="fa-solid fa-arrow-up-right-from-square"></i>
            </button>
            <button id="kfModalReload" title="إعادة تحميل">
                <i class="fa-solid fa-rotate"></i>
            </button>
            <button id="kfModalClose" title="إغلاق" aria-label="إغلاق">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </div>

    <!-- Quick Nav Tabs (visible when modal is open) -->
    <div id="kfModalNav">
        <?php foreach ($posModalPages as $key => $page): ?>
        <button class="kfmn-tab" data-key="<?php echo $key; ?>"
                data-url="<?php echo dol_escape_htmltag($page[2]); ?>"
                data-color="<?php echo dol_escape_htmltag($page[3]); ?>"
                data-icon="<?php echo dol_escape_htmltag($page[0]); ?>"
                data-label="<?php echo dol_escape_htmltag($page[1]); ?>"
                title="<?php echo dol_escape_htmltag($page[1]); ?>">
            <i class="fa-solid <?php echo dol_escape_htmltag($page[0]); ?>"></i>
            <span><?php echo dol_escape_htmltag($page[1]); ?></span>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- iframe content area -->
    <div id="kfModalBody">
        <iframe id="kfModalFrame" src="" frameborder="0"
                allow="same-origin" title="Page content"></iframe>
        <div id="kfModalLoader">
            <div class="kfml-spin"></div>
            <span id="kfModalLoaderLabel">جاري التحميل...</span>
        </div>
    </div>

    <!-- Resize handle -->
    <div id="kfModalResizer" title="اسحب لتغيير الحجم"></div>

</div>

<!-- Shortcuts Drawer (More button) -->
<div id="kfDrawer">
    <div id="kfDrawerHead">
        <span>القوائم والاختصارات</span>
        <button onclick="kfDrawerClose()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div id="kfDrawerSearch">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" placeholder="بحث..." oninput="kfDrawerFilter(this.value)" id="kfDrawerInput">
    </div>
    <div id="kfDrawerBody">
        <?php
        $wsUrl = DOL_URL_ROOT . '/takepos/workspace.php?key=';
        $drawerSections = array(
            array('fa-boxes-stacked', '#1d4ed8', 'Catalog & Inventory', array(
                array('fa-box',             'إضافة منتج',          $wsUrl.'add_product'),
                array('fa-concierge-bell',  'إضافة خدمة',          $wsUrl.'add_service'),
                array('fa-th-list',         'إدارة المنتجات',       $wsUrl.'manage_products'),
                array('fa-barcode',         'باركود المنتجات',      $wsUrl.'product_barcodes'),
                array('fa-percent',         'معدلات الضريبة',       $wsUrl.'tax_rates'),
                array('fa-warehouse',       'نظرة المخزون',        $wsUrl.'stock_overview'),
                array('fa-clipboard-check', 'تعديلات المخزون',      $wsUrl.'stock_adjustments'),
                array('fa-layer-group',     'مخزون الفروع',        $wsUrl.'stock_all_branches'),
                array('fa-right-left',      'نقل المخزون',         $wsUrl.'stock_transfer'),
                array('fa-scale-balanced',  'تسوية المخزون',       $wsUrl.'stock_reconciliation'),
                array('fa-clipboard-check', 'جرد المخزون',         $wsUrl.'stock_count'),
                array('fa-folder-plus',     'إضافة تصنيف',         $wsUrl.'add_category'),
                array('fa-sitemap',         'إدارة التصنيفات',     $wsUrl.'manage_categories'),
                array('fa-boxes-stacked',   'قطعة / كرتون',        $wsUrl.'admin_product_variants'),
                array('fa-money-check',     'الشيكات',             $wsUrl.'cheque_ops'),
                array('fa-weight-scale',    'باركود الميزان',      $wsUrl.'scale_barcode'),
            )),
            array('fa-cart-shopping', '#16a34a', 'Sales Operations', array(
                array('fa-business-time',    'إدارة الورديات',      $wsUrl.'shift_ops'),
                array('fa-rotate-left',      'مكتب الاسترجاع',      $wsUrl.'refund_lookup'),
                array('fa-right-left',       'مكتب الاستبدال',      $wsUrl.'exchange_ops'),
                array('fa-id-card',          'برنامج الولاء',       $wsUrl.'loyalty_desk'),
                array('fa-cloud-arrow-up',   'قائمة المزامنة',     $wsUrl.'sync_queue'),
                array('fa-receipt',          'المصروفات',          $wsUrl.'expenses_ops'),
                array('fa-book',             'دفتر المصروفات',     $wsUrl.'expense_ledger'),
                array('fa-truck-arrow-right','المشتريات',          $wsUrl.'purchase_ops'),
            )),
            array('fa-chart-pie', '#9333ea', 'Analytics', array(
                array('fa-chart-line',  'لوحة KPI',              $wsUrl.'kpi_dashboard'),
                array('fa-chart-pie',   'لوحة المدير التنفيذي', $wsUrl.'dashboard_pro'),
            )),
            array('fa-gear', '#d97706', 'POS Configuration', array(
                array('fa-tags',         'تصنيفات المصروفات',    $wsUrl.'expense_categories'),
                array('fa-gears',        'إعدادات TakePOS',      $wsUrl.'takepos_setup'),
                array('fa-cash-register','الطرفيات',             $wsUrl.'terminals'),
                array('fa-receipt',      'إعدادات الإيصال',      $wsUrl.'receipt_settings'),
                array('fa-palette',      'المظهر',               $wsUrl.'appearance'),
                array('fa-sliders',      'إعدادات أخرى',        $wsUrl.'other_settings'),
                array('fa-file-invoice', 'نظام الفوترة',         $wsUrl.'billing_system'),
            )),
            array('fa-shield-halved', '#dc2626', 'Governance & Access', array(
                array('fa-code-branch',    'إدارة الفروع',       $wsUrl.'branch_management'),
                array('fa-store',          'المتاجر',            $wsUrl.'stores'),
                array('fa-network-wired',  'ربط الطرفيات',       $wsUrl.'terminal_mapping'),
                array('fa-users-gear',     'مستخدمو POS',       $wsUrl.'pos_users'),
            )),
        );
        foreach ($drawerSections as $sec):
        ?>
        <div class="kfds">
            <button class="kfds-head" onclick="this.parentNode.classList.toggle('open')">
                <span style="width:26px;height:26px;border-radius:8px;background:<?php echo $sec[1]; ?>18;display:grid;place-items:center;color:<?php echo $sec[1]; ?>;font-size:12px;flex:0 0 26px">
                    <i class="fa-solid <?php echo $sec[0]; ?>"></i>
                </span>
                <b><?php echo htmlspecialchars($sec[2]); ?></b>
                <i class="fa-solid fa-chevron-down" style="margin-inline-start:auto;font-size:9px;color:#8294b0;transition:.2s"></i>
            </button>
            <div class="kfds-body">
                <?php foreach ($sec[3] as $item): ?>
                <a class="kfds-link" href="#"
                   onclick="kfModalOpen('<?php echo dol_escape_htmltag($item[2]); ?>','<?php echo dol_escape_htmltag($item[1]); ?>','<?php echo dol_escape_htmltag($item[0]); ?>','<?php echo dol_escape_htmltag($sec[1]); ?>');kfDrawerClose();return false;">
                    <span style="width:24px;height:24px;border-radius:7px;background:<?php echo $sec[1]; ?>12;display:grid;place-items:center;font-size:11px;color:<?php echo $sec[1]; ?>;flex:0 0 24px">
                        <i class="fa-solid <?php echo $item[0]; ?>"></i>
                    </span>
                    <span class="kfds-txt"><?php echo htmlspecialchars($item[1]); ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<div id="kfDrawerBackdrop" onclick="kfDrawerClose()"></div>

<style>
/* ══════════════════════════════════════════════
   KAFO MODAL SHELL — CSS
   ══════════════════════════════════════════════ */
#kfModalBackdrop {
    display:none;position:fixed;inset:0;z-index:800;
    background:rgba(9,16,30,.6);
    backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);
    animation:kfBdIn .22s ease;
}
#kfModalBackdrop.on{display:block}
@keyframes kfBdIn{from{opacity:0}to{opacity:1}}

#kfModalShell {
    display:none;
    position:fixed;
    inset-inline:0;
    bottom:0;
    z-index:801;
    background:#fff;
    border-radius:20px 20px 0 0;
    box-shadow:0 -12px 60px rgba(9,16,30,.22),0 -2px 8px rgba(9,16,30,.08);
    flex-direction:column;
    height:92vh;
    max-height:92vh;
    overflow:hidden;
    animation:kfShellUp .28s cubic-bezier(.22,.88,.36,1);
    will-change:transform;
}
#kfModalShell.on{display:flex}
@keyframes kfShellUp{
    from{transform:translateY(100%);opacity:.4}
    to{transform:translateY(0);opacity:1}
}

/* accent bar */
#kfModalAccent{
    height:3px;
    background:linear-gradient(90deg,#1d4ed8,#22c55e);
    flex:0 0 3px;
    transition:background .25s ease;
}

/* header */
#kfModalHead{
    display:flex;align-items:center;gap:12px;
    padding:14px 20px;
    border-bottom:1px solid #e8edf5;
    flex:0 0 auto;
    background:#fff;
}
#kfModalIcon{
    width:38px;height:38px;border-radius:11px;
    display:grid;place-items:center;
    font-size:16px;flex:0 0 38px;
    background:#eaf1ff;color:#1d4ed8;
    transition:background .25s,color .25s;
}
#kfModalTitleWrap{flex:1;min-width:0}
#kfModalTitle{
    margin:0;font-size:17px;font-weight:800;
    color:#0f1d33;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
#kfModalBreadcrumb{font-size:11.5px;color:#8294b0;margin-top:1px}

#kfModalActions{display:flex;gap:4px;align-items:center}
#kfModalActions button{
    width:34px;height:34px;border-radius:9px;
    border:1px solid #e3e9f2;background:#f6f8fc;
    color:#465775;font-size:13px;cursor:pointer;
    display:grid;place-items:center;transition:.12s;
}
#kfModalActions button:hover{background:#eaf1ff;color:#1d4ed8;border-color:#c5d9f8}
#kfModalClose:hover{background:#fdecec!important;color:#dc2626!important;border-color:#f6d6d6!important}

/* Quick nav tabs */
#kfModalNav{
    display:flex;align-items:center;
    gap:4px;
    padding:8px 16px;
    border-bottom:1px solid #e8edf5;
    overflow-x:auto;
    flex:0 0 auto;
    scrollbar-width:none;background:#f8faff;
}
#kfModalNav::-webkit-scrollbar{display:none}
.kfmn-tab{
    display:flex;align-items:center;gap:6px;
    padding:7px 12px;border-radius:10px;
    font-size:12.5px;font-weight:600;
    color:#465775;border:1px solid transparent;
    background:none;cursor:pointer;white-space:nowrap;
    transition:.12s;flex:0 0 auto;
}
.kfmn-tab i{font-size:12px;color:#8294b0}
.kfmn-tab:hover{background:#fff;border-color:#e3e9f2;color:#0f1d33}
.kfmn-tab.active{background:#fff;color:#1d4ed8;border-color:#c5d9f8}
.kfmn-tab.active i{color:#1d4ed8}

/* body + iframe */
#kfModalBody{
    flex:1;position:relative;min-height:0;overflow:hidden;
}
#kfModalFrame{
    width:100%;height:100%;border:none;
    display:block;background:#eef2f8;
}

/* loader */
#kfModalLoader{
    position:absolute;inset:0;
    background:#f0f4fb;
    display:flex;flex-direction:column;
    align-items:center;justify-content:center;gap:14px;
    z-index:2;
    transition:opacity .2s;
}
#kfModalLoader.done{opacity:0;pointer-events:none}
.kfml-spin{
    width:36px;height:36px;border-radius:50%;
    border:3px solid #e3e9f2;
    border-top-color:#1d4ed8;
    animation:kfSpin .7s linear infinite;
}
@keyframes kfSpin{to{transform:rotate(360deg)}}
#kfModalLoaderLabel{font-size:13.5px;font-weight:600;color:#8294b0}

/* resize handle */
#kfModalResizer{
    position:absolute;top:0;inset-inline:0;height:6px;
    cursor:ns-resize;z-index:10;
}
#kfModalResizer::before{
    content:"";position:absolute;top:10px;
    left:50%;transform:translateX(-50%);
    width:40px;height:3px;border-radius:3px;
    background:#d1d9e8;
}

/* ── Drawer ── */
#kfDrawerBackdrop{
    display:none;position:fixed;inset:0;z-index:899;
    background:rgba(9,16,30,.4);backdrop-filter:blur(3px)
}
#kfDrawerBackdrop.on{display:block}

#kfDrawer{
    position:fixed;inset-block:0;inset-inline-end:-340px;
    width:320px;z-index:900;
    background:#fff;
    border-inline-start:1px solid #e3e9f2;
    box-shadow:-6px 0 40px rgba(9,16,30,.12);
    display:flex;flex-direction:column;
    transition:inset-inline-end .24s cubic-bezier(.4,0,.2,1);
    will-change:inset-inline-end;
}
#kfDrawer.on{inset-inline-end:0}
#kfDrawerHead{
    display:flex;align-items:center;padding:16px 18px;
    border-bottom:1px solid #e3e9f2;flex:0 0 auto;
    font-weight:800;font-size:15px;color:#0f1d33;
}
#kfDrawerHead button{
    margin-inline-start:auto;width:30px;height:30px;
    border-radius:8px;border:1px solid #e3e9f2;
    background:#f6f8fc;color:#465775;
    font-size:16px;cursor:pointer;display:grid;place-items:center
}
#kfDrawerHead button:hover{background:#fdecec;color:#dc2626}
#kfDrawerSearch{
    padding:10px 14px;border-bottom:1px solid #e3e9f2;
    flex:0 0 auto;position:relative;display:flex;align-items:center;
}
#kfDrawerSearch i{position:absolute;inset-inline-start:26px;color:#8294b0;font-size:13px}
#kfDrawerSearch input{
    width:100%;height:36px;
    padding:0 12px 0 36px;
    border:1px solid #cdd7e6;border-radius:9px;
    font-size:13px;font-family:inherit;
    background:#f6f8fc;color:#0f1d33;
}
#kfDrawerBody{flex:1;overflow-y:auto;padding:8px 0}

/* drawer section */
.kfds{border-bottom:1px solid #f0f4fb}
.kfds-head{
    width:100%;display:flex;align-items:center;gap:10px;
    padding:10px 16px;background:none;border:none;
    cursor:pointer;font-family:inherit;text-align:start;
}
.kfds-head b{font-size:13px;font-weight:700;color:#0f1d33}
.kfds-body{display:none;padding:2px 10px 8px}
.kfds.open .kfds-body{display:block}
.kfds.open .kfds-head{background:#f8faff}
.kfds.open .kfds-head i.fa-chevron-down{transform:rotate(180deg);color:#1d4ed8}
.kfds-link{
    display:flex;align-items:center;gap:9px;
    padding:8px 10px;border-radius:9px;
    color:#0f1d33;text-decoration:none;
    font-size:13px;font-weight:600;transition:.1s;
    margin-bottom:1px;
}
.kfds-link:hover{background:#f0f4ff}

/* responsive — full screen on mobile */
@media(max-width:768px){
    #kfModalShell{height:100vh;max-height:100vh;border-radius:0}
    #kfModalNav{display:none}
    #kfDrawer{width:90vw}
}
</style>

<script>
/* ══════════════════════════════════════════════
   KAFO MODAL SHELL — JS
   ══════════════════════════════════════════════ */
(function () {
    var shell    = document.getElementById('kfModalShell');
    var backdrop = document.getElementById('kfModalBackdrop');
    var frame    = document.getElementById('kfModalFrame');
    var loader   = document.getElementById('kfModalLoader');
    var accent   = document.getElementById('kfModalAccent');
    var titleEl  = document.getElementById('kfModalTitle');
    var iconEl   = document.getElementById('kfModalIconI');
    var iconWrap = document.getElementById('kfModalIcon');
    var breadEl  = document.getElementById('kfModalBreadcrumb');
    var loaderLbl= document.getElementById('kfModalLoaderLabel');
    var popoutBtn= document.getElementById('kfModalPopout');

    var currentUrl = '';

    /* ── open ── */
    window.kfModalOpen = function (url, label, icon, color) {
        currentUrl = url;
        color = color || '#1d4ed8';

        /* accent bar */
        accent.style.background = 'linear-gradient(90deg,' + color + ',#22c55e)';

        /* icon */
        iconWrap.style.background = color + '18';
        iconWrap.style.color = color;
        iconEl.className = 'fa-solid ' + (icon || 'fa-circle');

        /* title */
        titleEl.textContent = label || '';
        breadEl.textContent = 'Kafo POS › ' + (label || '');
        loaderLbl.textContent = 'جاري تحميل ' + (label || '') + '...';

        /* highlight nav tab */
        document.querySelectorAll('.kfmn-tab').forEach(function (t) {
            t.classList.toggle('active', t.getAttribute('data-url') === url);
        });

        /* load iframe */
        loader.classList.remove('done');
        frame.src = '';
        frame.onload = function () { loader.classList.add('done'); };
        setTimeout(function () { frame.src = url; }, 40);

        /* show */
        shell.classList.add('on');
        backdrop.classList.add('on');
        document.body.style.overflow = 'hidden';
    };

    /* ── close ── */
    window.kfModalClose = function () {
        shell.classList.remove('on');
        backdrop.classList.remove('on');
        document.body.style.overflow = '';
        document.dispatchEvent(new Event('kfModalClosed'));
        setTimeout(function () {
            frame.src = '';
            document.querySelectorAll('.kfmn-tab').forEach(function(t){t.classList.remove('active')});
        }, 300);
    };

    /* ── popout ── */
    popoutBtn.addEventListener('click', function () {
        if (currentUrl) window.open(currentUrl, '_blank');
    });

    /* ── reload ── */
    document.getElementById('kfModalReload').addEventListener('click', function () {
        loader.classList.remove('done');
        var cur = frame.src;
        frame.src = '';
        setTimeout(function () { frame.src = cur; }, 40);
    });

    /* ── close button ── */
    document.getElementById('kfModalClose').addEventListener('click', kfModalClose);

    /* ── backdrop click ── */
    backdrop.addEventListener('click', kfModalClose);

    /* ── nav tabs ── */
    document.querySelectorAll('.kfmn-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            kfModalOpen(
                btn.getAttribute('data-url'),
                btn.getAttribute('data-label'),
                btn.getAttribute('data-icon'),
                btn.getAttribute('data-color')
            );
        });
    });

    /* ── ESC key ── */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (shell.classList.contains('on')) { kfModalClose(); return; }
            if (document.getElementById('kfDrawer').classList.contains('on')) { kfDrawerClose(); }
        }
    });

    /* ── Resize (drag top handle) ── */
    var isResizing = false, startY = 0, startH = 0;
    document.getElementById('kfModalResizer').addEventListener('mousedown', function (e) {
        isResizing = true;
        startY = e.clientY;
        startH = shell.offsetHeight;
        document.body.style.userSelect = 'none';
    });
    document.addEventListener('mousemove', function (e) {
        if (!isResizing) return;
        var delta = startY - e.clientY;
        var newH = Math.min(Math.max(startH + delta, 300), window.innerHeight * 0.97);
        shell.style.height = newH + 'px';
    });
    document.addEventListener('mouseup', function () {
        isResizing = false;
        document.body.style.userSelect = '';
    });

    /* ── Drawer ── */
    window.kfDrawerOpen = function () {
        document.getElementById('kfDrawer').classList.add('on');
        document.getElementById('kfDrawerBackdrop').classList.add('on');
        setTimeout(function () { document.getElementById('kfDrawerInput').focus(); }, 200);
    };
    window.kfDrawerClose = function () {
        document.getElementById('kfDrawer').classList.remove('on');
        document.getElementById('kfDrawerBackdrop').classList.remove('on');
    };
    window.kfDrawerFilter = function (q) {
        q = (q || '').toLowerCase();
        document.querySelectorAll('.kfds-link').forEach(function (a) {
            var txt = a.querySelector('.kfds-txt').textContent.toLowerCase();
            a.style.display = (!q || txt.indexOf(q) >= 0) ? '' : 'none';
        });
        document.querySelectorAll('.kfds').forEach(function (s) {
            var vis = Array.from(s.querySelectorAll('.kfds-link')).some(function (a) { return a.style.display !== 'none'; });
            s.style.display = vis ? '' : 'none';
            if (q) s.classList.add('open'); else s.classList.remove('open');
        });
    };

    /* open first drawer section by default */
    var first = document.querySelector('.kfds');
    

})();
</script>

<?php
/**
 * refunds_v2.php — Kafo POS v2 · الاسترجاع
 * نسخة جديدة بتصميم v2 — الـ JS مطابق للأصل تماماً
 */
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML'))  define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX', '1');

require '../main.inc.php';
require_once __DIR__ . '/lib/takepos_help.php';
require_once __DIR__ . '/class/TakeposAccess.class.php';
require_once __DIR__ . '/class/TakeposAudit.class.php';
require_once __DIR__ . '/class/TakeposUserAccess.class.php';

$langs->loadLangs(array('cashdesk', 'main', 'bills', 'takeposcustom@takepos'));
$terminal = isset($_SESSION['takeposterminal']) ? (string) $_SESSION['takeposterminal'] : '1';

$canAccess = (!empty($user->admin) || TakeposUserAccess::userHasAnyPermission($db, $user, array('takepos.refund.view','takepos.refund.partial','takepos.refund.full')));
if (!$canAccess) accessforbidden($langs->trans('TakeposRefundAccessDenied'));
TakeposAccess::requireFeature($db, 'takepos.returns', $user, false, array('page'=>'refunds_v2.php','feature'=>'takepos.returns'));
TakeposAccess::requireFrontendAccess($db,$user,'takepos.refunds','takepos.use',(int)$terminal,$langs->trans('TakeposRefundAccessDenied'),array('page'=>'refunds_v2.php'));
TakeposAudit::logEvent($db,$user,'refund_report_opened',TakeposAudit::SEVERITY_INFO,array('view'=>'v2'),'Refund v2 opened');

$FA    = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css';
$title = $langs->trans('TakeposRefundTitle');
$head  = '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="'.$FA.'">';
$arrayofcss = array('/takepos/css/workspace_v2.css');
top_htmlhead($head, $title, 0, 0, array(), $arrayofcss);
?>
<body class="kfv2-body">
<?php
$v2PageTitle = $langs->trans('TakeposRefundPageTitle');
$v2PageIcon  = 'fa-rotate-left';
$v2PageSub   = $langs->trans('TakeposRefundTitle');
$v2BackUrl   = DOL_URL_ROOT . '/takepos/pos_v2.php';
require __DIR__ . '/topbar_v2.php';
?>

<div class="kfv2-page-body">

    <!-- hidden config (same data-attrs as original) -->
    <div id="takepos-refund-config"
         data-token="<?php echo dol_escape_htmltag(newToken()); ?>"
         data-endpoint="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/ajax/refund.php'); ?>"
         data-details-url="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/refund_details.php?id='); ?>"
         data-receipt-url="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/refund_receipt.php?id='); ?>"></div>

    <!-- Invoice Lookup -->
    <div class="kfv2-card">
        <div class="kfv2-card-head"><i class="fa-solid fa-magnifying-glass"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposRefundInvoiceLookup')); ?></div>
        <div class="kfv2-card-body">
            <div class="kfv2-form-grid">
                <div class="kfv2-field">
                    <label><?php echo dol_escape_htmltag($langs->trans('TakeposRefundInvoiceId')); ?></label>
                    <input type="text" id="lookup_invoice_id" maxlength="64" placeholder="e.g. 159">
                </div>
                <div class="kfv2-field">
                    <label><?php echo dol_escape_htmltag($langs->trans('TakeposRefundInvoiceRef')); ?></label>
                    <input type="text" id="lookup_invoice_ref" maxlength="64" placeholder="e.g. TC1-2605-0091">
                </div>
                <div class="kfv2-field">
                    <label><?php echo dol_escape_htmltag($langs->trans('TakeposRefundDateFrom')); ?></label>
                    <input type="date" id="lookup_date_from">
                </div>
                <div class="kfv2-field">
                    <label><?php echo dol_escape_htmltag($langs->trans('TakeposRefundDateTo')); ?></label>
                    <input type="date" id="lookup_date_to">
                </div>
            </div>
            <div class="kfv2-actions">
                <button class="kfv2-btn kfv2-btn-primary" id="btn_lookup">
                    <i class="fa-solid fa-magnifying-glass"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposRefundSearchInvoices')); ?>
                </button>
            </div>
            <div class="kfv2-table-wrap" style="margin-top:14px">
                <table class="kfv2-table" id="table_lookup"></table>
            </div>
        </div>
    </div>

    <!-- Refund Wizard -->
    <div class="kfv2-card">
        <div class="kfv2-card-head"><i class="fa-solid fa-rotate-left"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposRefundWizard')); ?></div>
        <div class="kfv2-card-body">
            <div class="kfv2-msg info" id="refund_invoice_meta"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundSelectInvoice')); ?></div>
            <div class="kfv2-table-wrap" style="margin-bottom:14px">
                <table class="kfv2-table" id="table_lines"></table>
            </div>
            <div class="kfv2-form-grid">
                <div class="kfv2-field">
                    <label><?php echo dol_escape_htmltag($langs->trans('TakeposRefundType')); ?></label>
                    <select id="refund_type">
                        <option value="partial"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundPartial')); ?></option>
                        <option value="full"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundFull')); ?></option>
                    </select>
                </div>
                <div class="kfv2-field">
                    <label><?php echo dol_escape_htmltag($langs->trans('TakeposRefundPaymentMethod')); ?></label>
                    <select id="refund_payment_method">
                        <option value="CASH"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundCash')); ?></option>
                        <option value="CB"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundCard')); ?></option>
                        <option value="OTHER"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundOther')); ?></option>
                    </select>
                </div>
                <div class="kfv2-field">
                    <label><?php echo dol_escape_htmltag($langs->trans('TakeposRefundReason')); ?></label>
                    <select id="refund_reason"></select>
                </div>
                <div class="kfv2-field">
                    <label><?php echo dol_escape_htmltag($langs->trans('TakeposRefundNote')); ?></label>
                    <input type="text" id="refund_note" maxlength="255">
                </div>
                <div class="kfv2-field">
                    <label><?php echo dol_escape_htmltag($langs->trans('TakeposRefundManagerLogin')); ?></label>
                    <input type="text" id="manager_login" maxlength="128">
                </div>
                <div class="kfv2-field">
                    <label><?php echo dol_escape_htmltag($langs->trans('TakeposRefundManagerPassword')); ?></label>
                    <input type="password" id="manager_password" maxlength="128">
                </div>
                <div class="kfv2-field">
                    <label><?php echo dol_escape_htmltag($langs->trans('TakeposRefundManagerBarcode')); ?></label>
                    <input type="text" id="manager_barcode" maxlength="128">
                </div>
                <div class="kfv2-field">
                    <label><?php echo dol_escape_htmltag($langs->trans('TakeposRefundDefaultRestock')); ?></label>
                    <select id="refund_restock_default">
                        <option value="0"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonNo')); ?></option>
                        <option value="1"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonYes')); ?></option>
                    </select>
                </div>
            </div>
            <div class="kfv2-actions">
                <button class="kfv2-btn kfv2-btn-success" id="btn_process_refund">
                    <i class="fa-solid fa-check"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposRefundProcess')); ?>
                </button>
            </div>
            <div class="kfv2-msg" id="refund_msg"></div>
        </div>
    </div>

    <!-- Recent Refunds -->
    <div class="kfv2-card">
        <div class="kfv2-card-head">
            <i class="fa-solid fa-list"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposRefundRecentRefunds')); ?>
            <div style="margin-inline-start:auto;display:flex;gap:8px">
                <button class="kfv2-btn kfv2-btn-sm kfv2-btn-outline" id="btn_refresh_refunds">
                    <i class="fa-solid fa-rotate"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposRefundRefresh')); ?>
                </button>
                <button class="kfv2-btn kfv2-btn-sm kfv2-btn-outline" id="btn_export_refunds">
                    <i class="fa-solid fa-file-csv"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposRefundExportCsv')); ?>
                </button>
            </div>
        </div>
        <div class="kfv2-table-wrap">
            <table class="kfv2-table" id="table_refunds"></table>
        </div>
    </div>

</div><!-- /kfv2-page-body -->

<script>
/* ── JS مطابق للأصل refunds.php بدون تعديل في المنطق ── */
(function () {
    'use strict';
    var cfg = document.getElementById('takepos-refund-config');
    if (!cfg) return;
    var token      = cfg.getAttribute('data-token')       || '';
    var endpoint   = cfg.getAttribute('data-endpoint')    || '';
    var detailsUrl = cfg.getAttribute('data-details-url') || '';
    var receiptUrl = cfg.getAttribute('data-receipt-url') || '';
    var selectedInvoiceId = 0;
    var selectedLines     = [];

    var i18n = <?php echo json_encode(array(
        'id'=>$langs->trans('TakeposLoyaltyId'),
        'ref'=>$langs->trans('TakeposRefundInvoiceRef'),
        'date'=>$langs->trans('TakeposLoyaltyDate'),
        'customer'=>$langs->trans('TakeposRefundCustomer'),
        'total'=>$langs->trans('TakeposRefundAmount'),
        'store'=>$langs->trans('TakeposCommonStore'),
        'terminal'=>$langs->trans('TakeposCommonTerminal'),
        'action'=>$langs->trans('TakeposRefundAction'),
        'select'=>$langs->trans('TakeposCommonSelect'),
        'loadLinesFailed'=>$langs->trans('TakeposRefundLoadLinesFailed'),
        'invoiceMeta'=>$langs->trans('TakeposRefundInvoiceMeta'),
        'lineId'=>$langs->trans('TakeposRefundLineId'),
        'label'=>$langs->trans('TakeposCommonLabel'),
        'sold'=>$langs->trans('TakeposRefundSold'),
        'alreadyRefunded'=>$langs->trans('TakeposRefundAlreadyRefunded'),
        'refundable'=>$langs->trans('TakeposRefundRefundable'),
        'refundQty'=>$langs->trans('TakeposRefundRefundQty'),
        'unitTtc'=>$langs->trans('TakeposRefundUnitTtc'),
        'restock'=>$langs->trans('TakeposRefundRestock'),
        'loadRefundsFailed'=>$langs->trans('TakeposRefundLoadRefundsFailed'),
        'type'=>$langs->trans('TakeposCommonType'),
        'invoice'=>$langs->trans('TakeposRefundInvoice'),
        'amount'=>$langs->trans('TakeposRefundAmount'),
        'payment'=>$langs->trans('TakeposRefundPaymentMethod'),
        'reason'=>$langs->trans('TakeposRefundReason'),
        'status'=>$langs->trans('TakeposCommonStatus'),
        'details'=>$langs->trans('TakeposRefundDetails'),
        'print'=>$langs->trans('TakeposRefundPrint'),
        'noData'=>$langs->trans('TakeposReportsNoDataAvailable'),
        'selectInvoiceFirst'=>$langs->trans('TakeposRefundSelectInvoice'),
        'confirmProcess'=>$langs->trans('TakeposRefundConfirmProcess'),
        'failed'=>$langs->trans('TakeposRefundFailed'),
    ), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;

    function byId(id) { return document.getElementById(id); }
    function qs(p) { var u = new URLSearchParams(); Object.keys(p||{}).forEach(function(k){if(p[k]!==''&&p[k]!==null&&p[k]!==undefined)u.append(k,p[k]);}); return u.toString(); }
    function parseJsonPayload(txt) {
        var c=(txt||'').replace(/^\uFEFF/,'').trim();
        try{return JSON.parse(c);}catch(e){var f=c.indexOf('{'),l=c.lastIndexOf('}');if(f>=0&&l>f)return JSON.parse(c.slice(f,l+1));throw e;}
    }
    function callJson(p) { return fetch(endpoint+'?'+qs(p),{credentials:'same-origin',cache:'no-store'}).then(function(r){return r.text().then(function(t){return parseJsonPayload(t);});}); }
    function callJsonPost(p) {
        var b=new URLSearchParams(); Object.keys(p||{}).forEach(function(k){if(p[k]!==''&&p[k]!==null&&p[k]!==undefined)b.append(k,p[k]);});
        return fetch(endpoint+'?action='+encodeURIComponent(p.action||''),{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:b.toString()}).then(function(r){return r.text().then(function(t){return parseJsonPayload(t);});});
    }
    function safe(v) { return (v===null||v===undefined)?'':String(v); }
    function fmt(v)  { var n=parseFloat(v||0); if(!isFinite(n))n=0; return n.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); }
    function setMsg(txt,ok) {
        var n=byId('refund_msg');
        n.className='kfv2-msg '+(ok?'success':'error');
        n.textContent=txt||'';
    }

    function loadReasons() {
        callJson({action:'reasons'}).then(function(res){
            var s=byId('refund_reason'); s.innerHTML='';
            (res.rows||[]).forEach(function(r){var o=document.createElement('option');o.value=r.code;o.textContent=r.label+' ('+r.code+')';s.appendChild(o);});
        });
    }

    function renderLookup(rows) {
        var t=byId('table_lookup');
        var h='<thead><tr><th>'+i18n.id+'</th><th>'+i18n.ref+'</th><th>'+i18n.date+'</th><th>'+i18n.customer+'</th><th>'+i18n.total+'</th><th>'+i18n.store+'</th><th>'+i18n.terminal+'</th><th>'+i18n.action+'</th></tr></thead><tbody>';
        if(!(rows||[]).length){
            h+='<tr class="empty-row"><td colspan="8">'+safe(i18n.noData)+'</td></tr>';
        } else {
            (rows||[]).forEach(function(r){
                h+='<tr><td>'+safe(r.invoice_id)+'</td><td>'+safe(r.invoice_ref)+'</td><td>'+safe(r.invoice_date)+'</td><td>'+safe(r.customer_name)+'</td><td class="num">'+fmt(r.total_ttc)+'</td><td>'+safe(r.store_id)+'</td><td>'+safe(r.terminal_code)+'</td>'
                  +'<td><button type="button" class="kfv2-btn kfv2-btn-sm kfv2-btn-primary" data-invoice="'+safe(r.invoice_id)+'">'+i18n.select+'</button></td></tr>';
            });
        }
        h+='</tbody>'; t.innerHTML=h;
        t.querySelectorAll('button[data-invoice]').forEach(function(b){
            b.addEventListener('click',function(){selectedInvoiceId=parseInt(b.getAttribute('data-invoice')||'0',10);loadRefundableLines();});
        });
    }

    function loadRefundableLines() {
        if(!selectedInvoiceId) return;
        callJson({action:'refundable_lines',invoice_id:selectedInvoiceId}).then(function(res){
            if(!res||!res.success){setMsg(res&&res.message?res.message:i18n.loadLinesFailed,false);return;}
            var d=res.data||{}; selectedLines=d.lines||[];
            var mi=byId('refund_invoice_meta');
            mi.className='kfv2-msg success';
            mi.textContent=i18n.invoiceMeta.replace('%s',safe(d.invoice_ref)).replace('%s',safe(d.invoice_date)).replace('%s',safe(d.terminal_code));
            renderLines(selectedLines);
        });
    }

    function renderLines(lines) {
        var t=byId('table_lines');
        var h='<thead><tr><th>'+i18n.lineId+'</th><th>'+i18n.label+'</th><th>'+i18n.sold+'</th><th>'+i18n.alreadyRefunded+'</th><th>'+i18n.refundable+'</th><th>'+i18n.refundQty+'</th><th>'+i18n.unitTtc+'</th><th>'+i18n.restock+'</th></tr></thead><tbody>';
        if(!(lines||[]).length){
            h+='<tr class="empty-row"><td colspan="8">'+safe(i18n.noData)+'</td></tr>';
        } else {
            lines.forEach(function(r){
                h+='<tr><td>'+safe(r.line_id)+'</td><td>'+safe(r.label)+'</td><td class="num">'+fmt(r.qty_sold)+'</td><td class="num">'+fmt(r.qty_refunded)+'</td><td class="num">'+fmt(r.qty_refundable)+'</td>'
                  +'<td><input type="number" class="refund-qty" data-line="'+safe(r.line_id)+'" min="0" step="0.001" max="'+safe(r.qty_refundable)+'" value="0" style="width:80px;height:36px;border:1px solid var(--border-2);border-radius:8px;padding:0 8px;font-family:var(--ff-num)"></td>'
                  +'<td class="num">'+fmt(r.unit_price_ttc)+'</td>'
                  +'<td style="text-align:center"><input type="checkbox" class="refund-restock" data-line="'+safe(r.line_id)+'" style="width:18px;height:18px"></td></tr>';
            });
        }
        h+='</tbody>'; t.innerHTML=h;
    }

    function collectLines() {
        var lines=[];
        byId('table_lines').querySelectorAll('.refund-qty').forEach(function(input){
            var qty=parseFloat(input.value||'0');
            if(isFinite(qty)&&qty>0){
                var lineId=parseInt(input.getAttribute('data-line')||'0',10);
                var rn=byId('table_lines').querySelector('.refund-restock[data-line="'+lineId+'"]');
                lines.push({line_id:lineId,qty:qty,restock_flag:rn&&rn.checked?1:0});
            }
        });
        return lines;
    }

    function loadRecentRefunds() {
        callJson({action:'list_refunds'}).then(function(res){
            var t=byId('table_refunds');
            if(!res||!res.success){t.innerHTML='<tbody><tr><td>'+i18n.loadRefundsFailed+'</td></tr></tbody>';return;}
            var h='<thead><tr><th>'+i18n.id+'</th><th>'+i18n.ref+'</th><th>'+i18n.type+'</th><th>'+i18n.invoice+'</th><th>'+i18n.amount+'</th><th>'+i18n.payment+'</th><th>'+i18n.reason+'</th><th>'+i18n.status+'</th><th>'+i18n.date+'</th><th></th></tr></thead><tbody>';
            (res.rows||[]).forEach(function(r){
                h+='<tr><td>'+safe(r.rowid)+'</td><td>'+safe(r.refund_ref)+'</td><td>'+safe(r.refund_type)+'</td><td>'+safe(r.original_invoice_ref)+'</td><td class="num">'+fmt(r.total_amount)+'</td><td>'+safe(r.payment_method)+'</td><td>'+safe(r.reason_code)+'</td><td>'+safe(r.status)+'</td><td>'+safe(r.date_creation)+'</td>'
                  +'<td style="display:flex;gap:6px"><a class="kfv2-lnk" href="'+detailsUrl+encodeURIComponent(r.rowid)+'">'+i18n.details+'</a> <a class="kfv2-lnk" href="'+receiptUrl+encodeURIComponent(r.rowid)+'" target="_blank">'+i18n.print+'</a></td></tr>';
            });
            h+='</tbody>'; t.innerHTML=h;
        });
    }

    byId('btn_lookup').addEventListener('click',function(){
        var idVal=(byId('lookup_invoice_id').value||'').trim();
        var refVal=(byId('lookup_invoice_ref').value||'').trim();
        var idP='', refP=refVal;
        if(idVal!==''){if(/^\d+$/.test(idVal)){idP=idVal;}else if(refP===''){refP=idVal;}}
        callJson({action:'search_invoices',invoice_id:idP,invoice_ref:refP,date_from:byId('lookup_date_from').value,date_to:byId('lookup_date_to').value})
            .then(function(res){renderLookup((res&&res.rows)?res.rows:[]);});
    });
    ['lookup_invoice_id','lookup_invoice_ref','lookup_date_from','lookup_date_to'].forEach(function(id){
        byId(id).addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();byId('btn_lookup').click();}});
    });

    byId('btn_process_refund').addEventListener('click',function(){
        if(!selectedInvoiceId){setMsg(i18n.selectInvoiceFirst,false);return;}
        var lines=collectLines();
        if(!confirm(i18n.confirmProcess)) return;
        callJsonPost({action:'create_refund',token:token,refund_type:byId('refund_type').value,invoice_id:selectedInvoiceId,
            reason_code:byId('refund_reason').value,note:byId('refund_note').value,
            payment_method:byId('refund_payment_method').value,restock_default:byId('refund_restock_default').value,
            manager_login:byId('manager_login').value,manager_password:byId('manager_password').value,
            manager_barcode:byId('manager_barcode').value,lines_json:JSON.stringify(lines)
        }).then(function(res){
            if(res&&res.success){setMsg(res.message+' ('+safe(res.result&&res.result.refund_ref)+')',true);loadRefundableLines();loadRecentRefunds();}
            else{setMsg(res&&res.message?res.message:i18n.failed,false);}
        });
    });
    byId('btn_refresh_refunds').addEventListener('click',function(){loadRecentRefunds();});
    byId('btn_export_refunds').addEventListener('click',function(){window.location.href=endpoint+'?'+qs({action:'export_csv'});});

    loadReasons();
    loadRecentRefunds();
})();
</script>
<?php echo takeposHelpRender($langs, __FILE__); ?>
</body>
<?php llxFooter(); $db->close();

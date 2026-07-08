<?php
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
if (!defined('NOBROWSERNOTIF')) define('NOBROWSERNOTIF', '1');
require '../main.inc.php';
require_once __DIR__ . '/lib/takepos_help.php';

require_once DOL_DOCUMENT_ROOT . '/takepos/lib/takepos_access_guard.php';
takeposAccessGuardCurrent($db, isset($user) ? $user : null, __FILE__);
$langs->loadLangs(array('cashdesk', 'main', 'takeposcustom@takepos'));
$embed = GETPOSTINT('embed');
if (empty($user->id) || empty($user->rights->takepos->run)) accessforbidden();
?><!DOCTYPE html>
<html lang="<?php echo dol_escape_htmltag((string) $langs->defaultlang); ?>" dir="<?php echo preg_match('/^ar/i', (string) $langs->defaultlang) ? 'rtl' : 'ltr'; ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo dol_escape_htmltag($langs->trans('TakeposCustomerDisplayTitle')); ?></title>
<style>
:root{--bg:#0f172a;--panel:#111827;--muted:#94a3b8;--text:#f8fafc;--accent:#22c55e;}
*{box-sizing:border-box} body{margin:0;font-family:Arial,Helvetica,sans-serif;background:linear-gradient(135deg,#0f172a,#111827 45%,#1e293b);color:var(--text);min-height:100vh;overflow:hidden} body[dir=rtl]{direction:rtl} body[dir=rtl] .table th, body[dir=rtl] .table td{text-align:right} body[dir=rtl] .num{text-align:left}
body.embed{transform:scale(.88);transform-origin:top left;width:113.5%;height:113.5%}
.wrap{display:grid;grid-template-rows:auto 1fr auto;min-height:100vh}.header{display:flex;justify-content:space-between;align-items:center;padding:18px 24px;border-bottom:1px solid rgba(255,255,255,.08)}
.brand h1{margin:0;font-size:28px}.sub{color:var(--muted);margin-top:6px}.badge{background:rgba(255,255,255,.08);padding:10px 14px;border-radius:999px;font-size:13px}
.main{display:grid;grid-template-columns:1.65fr .95fr;gap:20px;padding:20px}.panel{background:rgba(17,24,39,.82);border:1px solid rgba(255,255,255,.08);border-radius:22px;overflow:hidden}
.lines{padding:18px}.title{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}.title h2{margin:0;font-size:22px}.small{font-size:13px;color:var(--muted)}
.table{width:100%;border-collapse:collapse}.table th,.table td{padding:14px 10px;border-bottom:1px solid rgba(255,255,255,.07)}.table th{color:var(--muted);text-align:left;font-size:14px}.table td{font-size:18px}.num{text-align:right}
.side{padding:18px;display:grid;gap:14px;align-content:start}.sum{padding:18px;border-radius:18px;background:linear-gradient(180deg,rgba(37,99,235,.18),rgba(34,197,94,.12))}.sum .lbl{color:var(--muted);font-size:13px}.sum .val{font-size:38px;font-weight:800;margin-top:6px}
.kv{display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px dashed rgba(255,255,255,.08)}.kv .k{color:var(--muted)}.kv .v{font-size:22px;font-weight:700}
.empty{display:grid;place-items:center;min-height:48vh;text-align:center}.empty .icon{font-size:72px;margin-bottom:10px}.empty .title{font-size:30px;font-weight:800}.empty .desc{color:var(--muted);margin-top:8px;font-size:16px}
.footer{padding:12px 22px 18px;color:var(--muted);display:flex;justify-content:space-between}.hero{padding:16px 18px;border-radius:16px;background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.16);font-size:20px;font-weight:700}
@media (max-width:980px){.main{grid-template-columns:1fr}.sum .val{font-size:28px}.table td{font-size:16px}}
</style>
</head>
<body class="<?php echo $embed ? 'embed' : ''; ?>">
<div class="wrap">
  <div class="header"><div class="brand"><h1><?php echo dol_escape_htmltag($langs->trans('TakeposCustomerDisplayHeader')); ?></h1><div class="sub"><?php echo dol_escape_htmltag($langs->trans('TakeposCustomerDisplaySubtitle')); ?></div></div><div class="badge" id="status"><?php echo dol_escape_htmltag($langs->trans('TakeposCustomerDisplayWaiting')); ?></div></div>
  <div class="main">
    <div class="panel lines"><div class="title"><h2><?php echo dol_escape_htmltag($langs->trans('TakeposCustomerDisplayCurrentItems')); ?></h2><div class="small" id="invoice-ref"><?php echo dol_escape_htmltag($langs->trans('TakeposCustomerDisplayNoInvoice')); ?></div></div><div id="content"></div></div>
    <div class="panel side"><div class="sum"><div class="lbl"><?php echo dol_escape_htmltag($langs->trans('TakeposCustomerDisplayGrandTotal')); ?></div><div class="val" id="grand-total">0.00</div></div><div class="hero" id="hero"><?php echo dol_escape_htmltag($langs->trans('TakeposCustomerDisplayWelcome')); ?></div><div><div class="kv"><div class="k"><?php echo dol_escape_htmltag($langs->trans('TakeposCustomerDisplaySubtotal')); ?></div><div class="v" id="subtotal">0.00</div></div><div class="kv"><div class="k"><?php echo dol_escape_htmltag($langs->trans('TakeposCustomerDisplayTax')); ?></div><div class="v" id="tax">0.00</div></div><div class="kv"><div class="k"><?php echo dol_escape_htmltag($langs->trans('TakeposCustomerDisplayDiscount')); ?></div><div class="v" id="discount">0.00</div></div><div class="kv" style="border-bottom:none"><div class="k"><?php echo dol_escape_htmltag($langs->trans('TakeposCustomerDisplayItems')); ?></div><div class="v" id="item-count">0</div></div></div></div>
  </div>
  <div class="footer"><div id="foot"><?php echo dol_escape_htmltag($langs->trans('TakeposCustomerDisplayFooter')); ?></div><div id="clock"></div></div>
</div>
<script>
(function(){
  const i18n = <?php echo json_encode(array(
    'Waiting' => $langs->trans('TakeposCustomerDisplayWaiting'),
    'NoInvoice' => $langs->trans('TakeposCustomerDisplayNoInvoice'),
    'InvoicePrefix' => $langs->trans('TakeposCustomerDisplayInvoicePrefix'),
    'LiveCart' => $langs->trans('TakeposCustomerDisplayLiveCart'),
    'Welcome' => $langs->trans('TakeposCustomerDisplayWelcome'),
    'ReviewOrder' => $langs->trans('TakeposCustomerDisplayReviewOrder'),
    'Ready' => $langs->trans('TakeposCustomerDisplayReady'),
    'BasketAppears' => $langs->trans('TakeposCustomerDisplayBasketAppears'),
    'Item' => $langs->trans('TakeposCustomerDisplayItem'),
    'Qty' => $langs->trans('Qty'),
    'Total' => $langs->trans('TakeposCustomerDisplayTotal')
  ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const isArabic = /^ar/i.test(<?php echo json_encode((string) $langs->defaultlang); ?> || '');
  const key='takepos_customer_display_state';
  let channel=null; try{channel=new BroadcastChannel('takepos_customer_display');}catch(e){}
  function esc(s){return String(s||'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
  function parseAmount(v){
    const s=String(v||'').replace(/,/g,'');
    const m=s.match(/-?\d+(?:\.\d+)?/g);
    if(!m||!m.length) return 0;
    return parseFloat(m[m.length-1])||0;
  }
  function preserveCurrency(raw, numeric){
    let s=String(raw||'').trim();
    s=s.replace(/&amp;/g,'&');
    if(s) return s;
    return numeric.toFixed(2);
  }
  function looksLikeCurrencyText(v){return /[%$\u20AC\u00A3\u00A5]|(?:\b(?:sar|jod|usd|eur|aed)\b)|(?:\u0631\u064a\u0627\u0644|\u062f\u064a\u0646\u0627\u0631|\u062f\u0648\u0644\u0627\u0631|\u064a\u0648\u0631\u0648|\u062f\u0631\u0647\u0645)/i.test(String(v||'').trim());}
  function normalizeQty(v){
    const s=String(v||'').trim();
    if(!s || looksLikeCurrencyText(s)) return '1';
    const m=s.match(/\d+(?:\.\d+)?/);
    return m ? m[0] : '1';
  }
  function render(state){
    state=state||{}; const items=Array.isArray(state.items)?state.items:[];
    let derivedTotal=0;
    items.forEach(function(it){ derivedTotal += parseAmount(it.total||it.price||'0'); it.qty = normalizeQty(it.qty); });
    const rawTotal = String(state.totalTtc||state.total||'').trim();
    const rawSubtotal = String(state.totalHt||state.subtotal||'').trim();
    const rawTax = String(state.tax||'').trim();
    const rawDiscount = String(state.discount||'').trim();
    const numericTotal = parseAmount(rawTotal) > 0 ? parseAmount(rawTotal) : derivedTotal;
    const numericSubtotal = parseAmount(rawSubtotal) > 0 ? parseAmount(rawSubtotal) : numericTotal;
    const numericTax = parseAmount(rawTax);
    const numericDiscount = parseAmount(rawDiscount);
    document.getElementById('invoice-ref').textContent = state.invoiceRef ? (i18n.InvoicePrefix + ' ' + state.invoiceRef) : i18n.NoInvoice;
    document.getElementById('grand-total').textContent = preserveCurrency(rawTotal, numericTotal);
    document.getElementById('subtotal').textContent = preserveCurrency(rawSubtotal, numericSubtotal);
    document.getElementById('tax').textContent = preserveCurrency(rawTax, numericTax);
    document.getElementById('discount').textContent = preserveCurrency(rawDiscount, numericDiscount);
    document.getElementById('item-count').textContent = String(items.length||0);
    document.getElementById('status').textContent = items.length ? i18n.LiveCart : i18n.Waiting;
    document.getElementById('hero').textContent = state.message || (items.length ? i18n.ReviewOrder : i18n.Welcome);
    if (!items.length) { document.getElementById('content').innerHTML = '<div class="empty"><div><div class="icon">&#128722;</div><div class="title">' + esc(i18n.Ready) + '</div><div class="desc">' + esc(i18n.BasketAppears) + '</div></div></div>'; return; }
    let html='<table class="table"><thead><tr><th>' + esc(i18n.Item) + '</th><th class="num">' + esc(i18n.Qty) + '</th><th class="num">' + esc(i18n.Total) + '</th></tr></thead><tbody>';
    items.forEach(item=>{html += '<tr><td>'+esc(item.label||item.name||'')+'</td><td class="num">'+esc(normalizeQty(item.qty||'1'))+'</td><td class="num">'+esc(item.total||item.price||'')+'</td></tr>';});
    html += '</tbody></table>'; document.getElementById('content').innerHTML=html;
  }
  function load(){try{const raw=localStorage.getItem(key); if(raw) render(JSON.parse(raw));}catch(e){}}
  load();
  window.addEventListener('storage', e=>{ if(e.key===key && e.newValue){ try{render(JSON.parse(e.newValue));}catch(err){} } });
  if(channel){ channel.onmessage = ev=>{ if(ev && ev.data) render(ev.data); }; }
  window.addEventListener('message', function(ev){ if(ev.data && ev.data.type==='takepos_customer_display'){ render(ev.data.state||{}); } });
  setInterval(load, 1500); setInterval(()=>{document.getElementById('clock').textContent = new Date().toLocaleString();}, 1000);
})();
</script>
<?php echo takeposHelpRender($langs, __FILE__); ?>
</body>
</html>
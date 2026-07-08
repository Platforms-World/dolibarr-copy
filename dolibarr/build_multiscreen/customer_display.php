<?php
/* Professional customer-facing secondary screen for TakePOS */
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
if (!defined('NOBROWSERNOTIF')) define('NOBROWSERNOTIF', '1');
require '../main.inc.php';
require_once __DIR__.'/class/TakeposSaasBridge.class.php';

if (empty($user->id) || !$user->hasRight('takepos', 'run')) {
    accessforbidden();
}
TakeposSaasBridge::enforceFrontend($db, isset($user) ? $user : null, 'takepos.frontend', isset($_SESSION['takeposterminal']) ? $_SESSION['takeposterminal'] : null);
$terminal = GETPOST('terminal', 'alpha');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TakePOS Customer Display</title>
<style>
:root{--bg:#0f172a;--panel:#111827;--muted:#94a3b8;--text:#f8fafc;--accent:#22c55e;--accent2:#38bdf8;--warn:#f59e0b;}
*{box-sizing:border-box} body{margin:0;font-family:Arial,Helvetica,sans-serif;background:linear-gradient(135deg,#0f172a,#111827 50%,#1e293b);color:var(--text);min-height:100vh}
.wrap{display:grid;grid-template-rows:auto 1fr auto;min-height:100vh}
.header{display:flex;justify-content:space-between;align-items:center;padding:18px 28px;border-bottom:1px solid rgba(255,255,255,.08);background:rgba(15,23,42,.55);backdrop-filter:blur(12px)}
.brand h1{margin:0;font-size:32px;letter-spacing:.4px}.brand .sub{color:var(--muted);margin-top:4px;font-size:15px}
.badges{display:flex;gap:12px;flex-wrap:wrap}.badge{background:rgba(255,255,255,.06);padding:10px 14px;border-radius:999px;font-size:14px;color:#e2e8f0}
.main{display:grid;grid-template-columns:1.65fr .95fr;gap:24px;padding:24px}.panel{background:rgba(17,24,39,.82);border:1px solid rgba(255,255,255,.08);border-radius:22px;box-shadow:0 20px 50px rgba(0,0,0,.25)}
.lines{padding:20px 22px;display:flex;flex-direction:column}.panel-title{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}.panel-title h2{margin:0;font-size:24px}.panel-title .small{color:var(--muted);font-size:14px}
.table{width:100%;border-collapse:collapse}.table th,.table td{padding:14px 10px;border-bottom:1px solid rgba(255,255,255,.07)}.table th{color:var(--muted);font-weight:600;text-align:left;font-size:14px}.table td{font-size:20px}.table td.num,.table th.num{text-align:right}.product{font-weight:700}.note{color:var(--muted);font-size:13px;margin-top:6px}
.side{padding:20px 22px;display:grid;gap:18px;align-content:start}.summary-card{padding:18px 20px;border-radius:18px;background:linear-gradient(180deg,rgba(56,189,248,.15),rgba(34,197,94,.08));border:1px solid rgba(255,255,255,.08)}.summary-card .label{font-size:14px;color:var(--muted)}.summary-card .value{font-size:40px;font-weight:800;margin-top:8px}
.kv{display:flex;justify-content:space-between;gap:12px;padding:14px 0;border-bottom:1px dashed rgba(255,255,255,.08)}.kv .k{color:var(--muted);font-size:16px}.kv .v{font-size:22px;font-weight:700}
.empty{display:grid;place-items:center;min-height:48vh;text-align:center;padding:30px}.empty .icon{font-size:76px;margin-bottom:14px}.empty .title{font-size:34px;font-weight:800}.empty .desc{color:var(--muted);margin-top:8px;font-size:18px}
.footer{padding:12px 24px 22px;color:var(--muted);display:flex;justify-content:space-between;align-items:center}.ticker{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:70vw}
.hero-msg{padding:16px 20px;border-radius:18px;background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.22);font-size:22px;font-weight:700}
@media (max-width: 980px){.main{grid-template-columns:1fr}.brand h1{font-size:24px}.table td{font-size:16px}.summary-card .value{font-size:30px}}
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <div class="brand">
      <h1>Customer Display</h1>
      <div class="sub">TakePOS secondary screen<?php echo $terminal ? ' • Terminal '.dol_escape_htmltag($terminal) : ''; ?></div>
    </div>
    <div class="badges">
      <div class="badge" id="badge-status">Waiting for cart</div>
      <div class="badge" id="badge-updated">No data yet</div>
    </div>
  </div>
  <div class="main">
    <div class="panel lines">
      <div class="panel-title"><h2>Current Items</h2><div class="small" id="invoice-ref">No active invoice</div></div>
      <div id="content"></div>
    </div>
    <div class="panel side">
      <div class="summary-card"><div class="label">Grand Total</div><div class="value" id="grand-total">0.00</div></div>
      <div class="hero-msg" id="hero-msg">Welcome. Your order will appear here.</div>
      <div>
        <div class="kv"><div class="k">Subtotal</div><div class="v" id="subtotal">0.00</div></div>
        <div class="kv"><div class="k">Discount</div><div class="v" id="discount">0.00</div></div>
        <div class="kv"><div class="k">Tax</div><div class="v" id="tax">0.00</div></div>
        <div class="kv" style="border-bottom:none"><div class="k">Items</div><div class="v" id="item-count">0</div></div>
      </div>
    </div>
  </div>
  <div class="footer">
    <div class="ticker" id="footer-msg">Tip: move this page to the second monitor and keep it fullscreen.</div>
    <div id="clock"></div>
  </div>
</div>
<script>
(function(){
  const key = 'takepos_customer_display_state';
  const channelName = 'takepos_customer_display';
  let channel = null;
  try { channel = new BroadcastChannel(channelName); } catch(e) { channel = null; }

  function fmt(v){ return (v===undefined||v===null||v==='') ? '0.00' : String(v); }
  function esc(s){ return String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }
  function render(state){
    state = state || {};
    const items = Array.isArray(state.items) ? state.items : [];
    document.getElementById('invoice-ref').textContent = state.invoiceRef ? ('Invoice: ' + state.invoiceRef) : 'No active invoice';
    document.getElementById('grand-total').textContent = fmt(state.totalTtc || state.total || '0.00');
    document.getElementById('subtotal').textContent = fmt(state.totalHt || state.subtotal || state.totalTtc || '0.00');
    document.getElementById('discount').textContent = fmt(state.discount || '0.00');
    document.getElementById('tax').textContent = fmt(state.tax || '0.00');
    document.getElementById('item-count').textContent = String(items.length || 0);
    document.getElementById('badge-status').textContent = items.length ? 'Live cart' : 'Waiting for cart';
    document.getElementById('badge-updated').textContent = state.updatedAt ? ('Updated: ' + state.updatedAt) : 'No data yet';
    document.getElementById('hero-msg').textContent = state.message || (items.length ? 'Please review your order before payment.' : 'Welcome. Your order will appear here.');
    document.getElementById('footer-msg').textContent = state.footer || 'Tip: move this page to the second monitor and keep it fullscreen.';
    const content = document.getElementById('content');
    if (!items.length) {
      content.innerHTML = '<div class="empty"><div><div class="icon">🛒</div><div class="title">Ready for the next customer</div><div class="desc">The cashier screen will send the basket here automatically.</div></div></div>';
      return;
    }
    let html = '<table class="table"><thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Total</th></tr></thead><tbody>';
    items.forEach(item => {
      html += '<tr><td><div class="product">'+esc(item.label || item.name || '')+'</div>' + (item.note ? '<div class="note">'+esc(item.note)+'</div>' : '') + '</td><td class="num">'+esc(item.qty || '1')+'</td><td class="num">'+esc(item.total || item.price || '')+'</td></tr>';
    });
    html += '</tbody></table>';
    content.innerHTML = html;
  }

  function load(){
    try {
      const raw = localStorage.getItem(key);
      if (raw) render(JSON.parse(raw));
    } catch(e) {}
  }
  load();
  window.addEventListener('storage', function(ev){ if (ev.key === key && ev.newValue) { try { render(JSON.parse(ev.newValue)); } catch(e){} } });
  if (channel) { channel.onmessage = function(ev){ if (ev && ev.data) render(ev.data); }; }
  setInterval(load, 1500);
  setInterval(function(){ document.getElementById('clock').textContent = new Date().toLocaleString(); }, 1000);
})();
</script>
</body>
</html>

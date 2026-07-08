(function () {
    function q(id) { return document.getElementById(id); }
    function n(v) { var x = Number(v || 0); return Number.isFinite(x) ? x : 0; }
    function html(id, value) { var el = q(id); if (el) el.innerHTML = value; }
    function text(id, value) { var el = q(id); if (el) el.textContent = value; }
    function fmt(v) {
        var x = n(v);
        return x.toLocaleString(undefined, { minimumFractionDigits: x % 1 ? 2 : 0, maximumFractionDigits: 2 });
    }
    function pct(v) { return (Math.round(n(v) * 10) / 10).toFixed(1) + '%'; }
    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    function sum(rows, key) {
        return (rows || []).reduce(function (acc, row) { return acc + n(row[key]); }, 0);
    }
    function showFeedback(msg) {
        var box = q('tp_dashboard_feedback');
        if (!box) return;
        box.textContent = msg || '';
        box.classList.toggle('hidden', !msg);
    }
    function delta(current, prev) {
        current = n(current); prev = n(prev);
        if (!prev) return current ? 100 : 0;
        return ((current - prev) / prev) * 100;
    }
    function lastPeriod(rows) {
        var len = (rows || []).length;
        if (!len) return { current: 0, previous: 0 };
        var split = Math.max(1, Math.floor(len / 2));
        return {
            current: sum(rows.slice(len - split), 'value'),
            previous: sum(rows.slice(0, Math.max(0, len - split)), 'value')
        };
    }
    function buildLineChart(hostId, rows) {
        var host = q(hostId);
        if (!host) return;
        if (!rows || !rows.length) { host.innerHTML = '<div class="tpdb-empty">' + escapeHtml(window.takeposDashboardLabels.NoData) + '</div>'; return; }
        var width = Math.max(host.clientWidth || 300, 300), height = 170, pad = 16;
        var values = rows.map(function (r) { return n(r.value); });
        var max = Math.max.apply(Math, values.concat([1])), min = Math.min.apply(Math, values.concat([0]));
        var step = rows.length > 1 ? (width - pad * 2) / (rows.length - 1) : width - pad * 2;
        var pts = rows.map(function (row, idx) {
            var ratio = max === min ? 0.5 : (n(row.value) - min) / (max - min);
            return { x: pad + idx * step, y: height - pad - ratio * (height - pad * 2), label: row.label };
        });
        var line = pts.map(function (p) { return p.x.toFixed(1) + ',' + p.y.toFixed(1); }).join(' ');
        var area = 'M ' + pts[0].x.toFixed(1) + ' ' + (height - pad) + ' L ' + line.replace(/,/g, ' ') + ' L ' + pts[pts.length - 1].x.toFixed(1) + ' ' + (height - pad) + ' Z';
        var labels = pts.filter(function (_, idx) { return idx === 0 || idx === pts.length - 1 || idx === Math.floor(pts.length / 2); })
            .map(function (p) { return '<text x="' + p.x.toFixed(1) + '" y="' + (height - 2) + '" text-anchor="middle">' + escapeHtml(p.label.slice(5)) + '</text>'; }).join('');
        host.innerHTML = '<svg viewBox="0 0 ' + width + ' ' + height + '" preserveAspectRatio="none">'
            + '<defs><linearGradient id="tpdbLineFill" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#22c55e" stop-opacity="0.28"/><stop offset="100%" stop-color="#22c55e" stop-opacity="0.02"/></linearGradient></defs>'
            + '<path d="' + area + '" fill="url(#tpdbLineFill)"></path>'
            + '<polyline points="' + line + '" fill="none" stroke="#22c55e" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></polyline>'
            + labels + '</svg>';
    }
    function buildBars(hostId, rows) {
        var host = q(hostId);
        if (!host) return;
        if (!rows || !rows.length) { host.innerHTML = '<div class="tpdb-empty">' + escapeHtml(window.takeposDashboardLabels.NoData) + '</div>'; return; }
        var width = Math.max(host.clientWidth || 360, 360), height = 180, pad = 16;
        var max = Math.max.apply(Math, rows.map(function (r) { return n(r.value); }).concat([1]));
        var bw = Math.max(12, Math.min(28, ((width - pad * 2) / rows.length) - 10));
        var gap = ((width - pad * 2) - (bw * rows.length)) / Math.max(1, rows.length - 1);
        var x = pad;
        var bars = rows.map(function (row, idx) {
            var h = Math.max(6, (n(row.value) / max) * (height - 38));
            var y = height - h - 20;
            var fill = idx === rows.length - 2 ? '#7c6cf2' : '#d9d2fb';
            var out = '<rect x="' + x.toFixed(1) + '" y="' + y.toFixed(1) + '" width="' + bw + '" height="' + h.toFixed(1) + '" rx="6" fill="' + fill + '"></rect>'
                + '<text x="' + (x + bw / 2).toFixed(1) + '" y="' + (height - 2) + '" text-anchor="middle">' + escapeHtml((row.label || '').slice(5, 10)) + '</text>';
            x += bw + gap;
            return out;
        }).join('');
        host.innerHTML = '<svg viewBox="0 0 ' + width + ' ' + height + '" preserveAspectRatio="none">' + bars + '</svg>';
    }
    function buildCompare(hostId, rows) {
        var host = q(hostId);
        if (!host) return;
        if (!rows || !rows.length) { host.innerHTML = '<div class="tpdb-empty">' + escapeHtml(window.takeposDashboardLabels.NoData) + '</div>'; return; }
        var width = Math.max(host.clientWidth || 340, 340), height = 150, pad = 16;
        var max = Math.max.apply(Math, rows.map(function (r) { return n(r.value); }).concat([1]));
        var pairCount = Math.min(rows.length, 8);
        var start = rows.length - pairCount;
        var bw = 12, gap = ((width - pad * 2) / pairCount);
        var svg = '';
        for (var i = 0; i < pairCount; i++) {
            var row = rows[start + i];
            var val = n(row.value);
            var prev = i > 0 ? n(rows[start + i - 1].value) : val * 0.72;
            var h1 = Math.max(10, (val / max) * (height - 36));
            var h2 = Math.max(10, (prev / max) * (height - 36));
            var base = pad + i * gap;
            svg += '<rect x="' + base.toFixed(1) + '" y="' + (height - h1 - 10).toFixed(1) + '" width="' + bw + '" height="' + h1.toFixed(1) + '" rx="4" fill="#6c5ce7"></rect>';
            svg += '<rect x="' + (base + bw + 6).toFixed(1) + '" y="' + (height - h2 - 10).toFixed(1) + '" width="' + bw + '" height="' + h2.toFixed(1) + '" rx="4" fill="#9095a1"></rect>';
        }
        host.innerHTML = '<svg viewBox="0 0 ' + width + ' ' + height + '" preserveAspectRatio="none">' + svg + '</svg>';
    }
    function buildGauge(hostId, pending, total) {
        var host = q(hostId);
        if (!host) return;
        var ratio = total > 0 ? Math.min(100, Math.round((pending / total) * 100)) : 0;
        var bars = [];
        var totalBars = 24;
        for (var i = 0; i < totalBars; i++) {
            var active = i < Math.round((ratio / 100) * totalBars);
            var angle = -110 + ((220 / (totalBars - 1)) * i);
            var rad = angle * Math.PI / 180;
            var x = 110 + Math.cos(rad) * 78;
            var y = 96 + Math.sin(rad) * 78;
            var rot = angle + 90;
            bars.push('<rect x="' + (x - 4).toFixed(1) + '" y="' + (y - 14).toFixed(1) + '" width="8" height="28" rx="4" transform="rotate(' + rot.toFixed(1) + ' ' + x.toFixed(1) + ' ' + y.toFixed(1) + ')" fill="' + (active ? '#7c6cf2' : '#ddd8fe') + '"></rect>');
        }
        host.innerHTML = '<svg viewBox="0 0 220 140">' + bars.join('') + '</svg>';
        text('tp_pending_ratio', ratio + '%');
    }
    function renderSuppliers(rows) {
        var host = q('tp_supplier_list');
        if (!host) return;
        if (!rows || !rows.length) { host.innerHTML = '<div class="tpdb-empty">' + escapeHtml(window.takeposDashboardLabels.NoData) + '</div>'; return; }
        var total = sum(rows, 'amount') || 1;
        host.innerHTML = rows.slice(0, 6).map(function (row) {
            var d = delta(n(row.amount), total / Math.max(1, rows.length));
            return '<div class="tpdb-list-row">'
                + '<div><strong>' + escapeHtml(row.supplier || '—') + '</strong><span>' + fmt(row.amount) + '</span></div>'
                + '<div class="tpdb-row-delta ' + (d >= 0 ? 'up' : 'down') + '">' + pct(Math.abs(d)) + '</div>'
                + '</div>';
        }).join('');
    }
    function renderInsights(items) {
        var host = q('tp_insights_list');
        if (!host) return;
        if (!items || !items.length) { host.innerHTML = '<div class="tpdb-empty">' + escapeHtml(window.takeposDashboardLabels.NoData) + '</div>'; return; }
        host.innerHTML = items.slice(0, 6).map(function (item) {
            return '<div class="tpdb-status-row severity-' + escapeHtml(item.severity || 'low') + '">'
                + '<span>' + escapeHtml(item.title || '') + '</span>'
                + '<strong>' + escapeHtml(item.text || '') + '</strong>'
                + '</div>';
        }).join('');
    }
    function renderHeroStats(data) {
        var host = q('tp_hero_stats');
        if (!host) return;
        var trend = lastPeriod(data.sales_trend || []);
        var salesDelta = delta(trend.current, trend.previous);
        host.innerHTML = [
            { label: window.takeposDashboardLabels.SalesLabel, value: pct(Math.abs(salesDelta)) },
            { label: window.takeposDashboardLabels.InvoiceLabel, value: fmt((data.kpis || {}).invoice_count) },
            { label: window.takeposDashboardLabels.PendingChequeLabel, value: fmt((data.kpis || {}).cheques_pending) },
            { label: window.takeposDashboardLabels.LowStockLabel, value: fmt((data.kpis || {}).inventory_low) }
        ].map(function (item) {
            return '<div class="tpdb-hero-stat"><strong>' + escapeHtml(item.value) + '</strong><span>' + escapeHtml(item.label) + '</span></div>';
        }).join('');
    }
    function render(data) {
        if (!data) return;
        window.takeposDashboardInitial = data;
        var rows = data.sales_trend || [];
        var trend = lastPeriod(rows);
        var salesTotal = n((data.kpis || {}).sales_total);
        var invoices = n((data.kpis || {}).invoice_count);
        var avgInvoice = n((data.kpis || {}).avg_invoice);
        var customers = n((data.kpis || {}).distinct_customers);
        var pending = n((data.kpis || {}).cheques_pending);
        var overdue = n((data.kpis || {}).cheques_overdue);
        var lowStock = n((data.kpis || {}).inventory_low);
        var pendingAmount = n((data.cheque_summary || {}).pending_amount);
        var chequeDueToday = n((data.cheque_summary || {}).due_today);
        var chequeDue7 = n((data.cheque_summary || {}).due_7_days);
        var bounced = n((data.cheque_summary || {}).bounced);
        var salesDelta = delta(trend.current, trend.previous);
        var invoicePerCustomer = customers ? invoices / customers : invoices;
        var score = Math.max(0, Math.min(100, 100 - (overdue * 8 + lowStock * 3) + Math.min(20, salesDelta / 5)));

        renderHeroStats(data);
        text('tp_avg_sales_value', fmt(avgInvoice));
        text('tp_avg_sales_hint', window.takeposDashboardLabels.AvgInvoiceLabel + ' / ' + window.takeposDashboardLabels.InvoiceLabel);
        text('tp_sales_overview_value', fmt(salesTotal));
        text('tp_sales_overview_delta', (salesDelta >= 0 ? '+' : '') + pct(salesDelta));
        q('tp_sales_overview_delta') && q('tp_sales_overview_delta').classList.toggle('neg', salesDelta < 0);
        text('tp_invoice_count', fmt(invoices));
        text('tp_customer_count', fmt(customers));
        if (q('tp_progress_a')) q('tp_progress_a').style.width = Math.max(18, Math.min(82, customers ? (invoices / Math.max(customers, 1)) * 18 : 18)) + '%';
        if (q('tp_progress_b')) q('tp_progress_b').style.width = Math.max(18, Math.min(82, pending ? 100 - Math.min(70, overdue * 10) : 35)) + '%';

        text('tp_bar_total', fmt(salesTotal));
        text('tp_bar_delta', (salesDelta >= 0 ? '+' : '') + pct(salesDelta));
        text('tp_bar_caption', window.takeposDashboardLabels.InvoiceLabel + ': ' + fmt(invoices) + ' • ' + window.takeposDashboardLabels.CustomerLabel + ': ' + fmt(customers));
        text('tp_metric_sales', fmt(salesTotal));
        text('tp_metric_avg', fmt(avgInvoice));
        text('tp_metric_pending', fmt(pendingAmount));

        text('tp_due_today', fmt(chequeDueToday));
        text('tp_due_7', fmt(chequeDue7));
        text('tp_due_overdue', fmt(overdue));
        text('tp_due_bounced', fmt(bounced));

        text('tp_score_value', Math.round(score) + '%');
        text('tp_score_delta', (salesDelta >= 0 ? '+' : '') + pct(salesDelta));
        text('tp_sum_sales', fmt(salesTotal));
        text('tp_sum_sales_delta', (salesDelta >= 0 ? '+' : '') + pct(salesDelta));
        text('tp_sum_invoices', fmt(invoices));
        text('tp_sum_invoices_delta', fmt(invoicePerCustomer));

        buildLineChart('tp_sales_line_chart', rows);
        buildBars('tp_sales_bars_chart', rows.slice(-7));
        buildCompare('tp_compare_chart', rows);
        buildGauge('tp_gauge_chart', pending, pending + overdue + bounced + chequeDue7 + chequeDueToday);
        renderSuppliers(data.supplier_summary || []);
        renderInsights(data.decision_insights || []);
    }
    function refresh() {
        var cfg = q('takepos-dashboard-config');
        if (!cfg) return;
        var endpoint = cfg.getAttribute('data-endpoint');
        if (!endpoint) return;
        var from = q('tp_date_from') ? q('tp_date_from').value : '';
        var to = q('tp_date_to') ? q('tp_date_to').value : '';
        showFeedback('');
        fetch(endpoint + '?date_from=' + encodeURIComponent(from) + '&date_to=' + encodeURIComponent(to), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json || !json.success || !json.data) throw new Error(window.takeposDashboardLabels.LoadFailed);
                render(json.data);
            })
            .catch(function (err) { showFeedback(err && err.message ? err.message : window.takeposDashboardLabels.LoadFailed); });
    }
    function exportPdf() {
        if (!window.takeposDashboardCanExport) return showFeedback(window.takeposDashboardLabels.ExportDenied);
        var cfg = q('takepos-dashboard-config');
        if (!cfg) return;
        var endpoint = cfg.getAttribute('data-export-pdf');
        var from = q('tp_date_from') ? q('tp_date_from').value : '';
        var to = q('tp_date_to') ? q('tp_date_to').value : '';
        window.location.href = endpoint + '?date_from=' + encodeURIComponent(from) + '&date_to=' + encodeURIComponent(to);
    }
    document.addEventListener('DOMContentLoaded', function () {
        render(window.takeposDashboardInitial || {});
        var btnRefresh = q('tp_refresh_dashboard');
        if (btnRefresh) btnRefresh.addEventListener('click', refresh);
        var btnExport = q('tp_export_dashboard');
        if (btnExport) btnExport.addEventListener('click', exportPdf);
        window.addEventListener('resize', function () {
            render(window.takeposDashboardInitial || {});
        });
    });
})();

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var cfg = document.getElementById('takepos-kpi-config');
    if (!cfg) {
        return;
    }

    var endpoint = cfg.getAttribute('data-endpoint') || '';
    var feedbackNode = document.getElementById('takepos-kpi-feedback');
    var loadingNode = document.getElementById('takepos-kpi-loading');
    var i18n = window.takeposKpiI18n || {};

    function t(key, fallback) {
        return Object.prototype.hasOwnProperty.call(i18n, key) ? i18n[key] : fallback;
    }

    function byId(id) {
        return document.getElementById(id);
    }

    function setLoading(isLoading, label) {
        if (!loadingNode) {
            return;
        }
        loadingNode.textContent = label || t('Loading', 'Loading...');
        loadingNode.classList.toggle('hidden', !isLoading);
    }

    function showFeedback(level, message) {
        if (!feedbackNode) {
            return;
        }

        if (!message) {
            feedbackNode.className = 'takepos-workspace-feedback hidden';
            feedbackNode.textContent = '';
            return;
        }

        var cls = 'info';
        if (level === 'error') {
            cls = 'error';
        } else if (level === 'warning') {
            cls = 'warning';
        } else if (level === 'success') {
            cls = 'success';
        }

        feedbackNode.className = 'takepos-workspace-feedback ' + cls;
        feedbackNode.textContent = message;
    }

    function qs(params) {
        var usp = new URLSearchParams();
        Object.keys(params || {}).forEach(function (k) {
            if (params[k] !== '' && params[k] !== null && params[k] !== undefined) {
                usp.append(k, params[k]);
            }
        });
        return usp.toString();
    }

    function parseJsonPayload(txt) {
        var cleaned = (txt || '').replace(/^\uFEFF/, '').trim();
        try {
            return JSON.parse(cleaned);
        } catch (e) {
            var first = cleaned.indexOf('{');
            var last = cleaned.lastIndexOf('}');
            if (first >= 0 && last > first) {
                return JSON.parse(cleaned.slice(first, last + 1));
            }
            throw e;
        }
    }
    function requestJson(params) {
        params = params || {};
        params._ts = Date.now();
        return fetch(endpoint + '?' + qs(params), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Accept': 'application/json'
            }
        }).then(function (res) {
            return res.text().then(function (txt) {
                var payload;
                try {
                    payload = parseJsonPayload(txt);
                } catch (e) {
                    throw new Error(t('InvalidJson', 'Invalid JSON response from KPI endpoint.'));
                }
                if (!res.ok) {
                    throw new Error((payload && payload.message) ? payload.message : ('HTTP ' + res.status));
                }
                return payload;
            });
        });
    }

    function unwrap(payload) {
        if (!payload || typeof payload !== 'object') {
            throw new Error(t('UnexpectedEmpty', 'Unexpected empty response.'));
        }
        if (payload.success === false) {
            throw new Error(payload.message || t('Rejected', 'Operation rejected.'));
        }
        if (payload.data && typeof payload.data === 'object') {
            return payload.data;
        }
        return payload;
    }

    function safe(v) {
        return (v === null || v === undefined) ? '' : String(v);
    }

    function fmt(v) {
        var n = parseFloat(v || 0);
        if (!isFinite(n)) {
            n = 0;
        }
        return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function currentFilters() {
        return {
            date_from: byId('date_from').value,
            date_to: byId('date_to').value,
            cashier_id: byId('cashier_id').value,
            terminal_code: byId('terminal_code').value,
            store_id: byId('store_id').value,
            payment_method: byId('payment_method').value
        };
    }

    function fillSelect(id, rows, valueFn, labelFn) {
        var node = byId(id);
        if (!node) {
            return;
        }

        node.innerHTML = '<option value="">' + t('All', 'All') + '</option>';
        (rows || []).forEach(function (row) {
            var value = valueFn(row);
            if (value === '' || value === null || value === undefined) {
                return;
            }
            var option = document.createElement('option');
            option.value = value;
            option.textContent = labelFn(row);
            node.appendChild(option);
        });
    }

    function renderCards(cards) {
        var node = byId('kpi_cards');
        if (!node) {
            return;
        }

        var cardMeta = [
            ['gross_sales', t('GrossSales', 'Gross Sales')],
            ['net_sales', t('NetSales', 'Net Sales')],
            ['refund_amount', t('RefundAmount', 'Refund Amount')],
            ['refund_count', t('RefundCount', 'Refund Count')],
            ['avg_basket', t('AvgBasket', 'Avg Basket')],
            ['tickets_count', t('Tickets', 'Tickets')],
            ['top_cashier', t('TopCashier', 'Top Cashier')],
            ['top_store', t('TopStore', 'Top Store')],
            ['discrepancy_count', t('DiscrepancyCount', 'Discrepancy Count')],
            ['void_count', t('VoidCount', 'Void Count')]
        ];

        var html = '';
        cardMeta.forEach(function (meta) {
            var val = cards && Object.prototype.hasOwnProperty.call(cards, meta[0]) ? cards[meta[0]] : '';
            if (meta[0].indexOf('sales') >= 0 || meta[0].indexOf('amount') >= 0 || meta[0] === 'avg_basket') {
                val = fmt(val);
            }
            html += '<div class="takepos-workspace-card"><div class="takepos-workspace-card-label">' + meta[1] + '</div><div class="takepos-workspace-card-value">' + safe(val) + '</div></div>';
        });

        node.innerHTML = html;
    }

    function renderTable(id, headers, keyMap, rows) {
        var t = byId(id);
        if (!t) {
            return;
        }

        rows = rows || [];
        var html = '<thead><tr>';
        headers.forEach(function (h) {
            html += '<th>' + h + '</th>';
        });
        html += '</tr></thead><tbody>';

        if (rows.length === 0) {
            html += '<tr><td class="takepos-workspace-empty" colspan="' + String(headers.length) + '">' + t('NoData', 'No data available') + '</td></tr>';
        } else {
            rows.forEach(function (r) {
                html += '<tr>';
                keyMap.forEach(function (key) {
                    var val = r[key];
                    if (typeof val === 'number') {
                        val = fmt(val);
                    }
                    html += '<td>' + safe(val) + '</td>';
                });
                html += '</tr>';
            });
        }

        html += '</tbody>';
        t.innerHTML = html;
    }

    function loadFilters() {
        setLoading(true, t('LoadingFilters', 'Loading KPI filters...'));
        showFeedback(null, '');

        requestJson({ action: 'filters' })
            .then(function (res) {
                var payload = unwrap(res);
                var filters = payload.filters || payload;

                fillSelect('cashier_id', filters.cashiers || [], function (r) {
                    return r.rowid;
                }, function (r) {
                    var fullName = ((r.firstname || '') + ' ' + (r.lastname || '')).trim();
                    return fullName ? (fullName + ' (' + (r.login || '') + ')') : (r.login || '');
                });

                fillSelect('store_id', filters.stores || [], function (r) {
                    return r.rowid;
                }, function (r) {
                    return (r.code ? (r.code + ' - ') : '') + (r.label || '');
                });

                fillSelect('terminal_code', filters.terminals || [], function (r) {
                    return r.terminal_code || r.pos_source || '';
                }, function (r) {
                    var code = r.terminal_code || r.pos_source || '';
                    return code + (r.label ? (' - ' + r.label) : '');
                });

                fillSelect('payment_method', filters.payment_methods || [], function (r) {
                    return r.code;
                }, function (r) {
                    return r.libelle ? (r.libelle + ' (' + r.code + ')') : (r.code || '');
                });
            })
            .catch(function (err) {
                showFeedback('error', err && err.message ? err.message : t('LoadFiltersFailed', 'Failed to load KPI filters.'));
            })
            .finally(function () {
                setLoading(false);
            });
    }

    function runKpi() {
        setLoading(true, t('Running', 'Running KPI query...'));
        showFeedback(null, '');

        requestJson(currentFilters())
            .then(function (res) {
                var payload = unwrap(res);
                renderCards(payload.cards || {});
                renderTable('table_sales_hour', [t('Hour', 'Hour'), t('Tickets', 'Tickets'), t('Amount', 'Amount')], ['hour_slot', 'tickets', 'amount'], payload.sales_by_hour || []);
                renderTable('table_cashier', [t('Cashier', 'Cashier'), t('Tickets', 'Tickets'), t('Amount', 'Amount')], ['cashier_name', 'tickets', 'amount'], payload.tickets_per_cashier || []);
                renderTable('table_paymix', [t('Code', 'Code'), t('Label', 'Label'), t('Amount', 'Amount')], ['payment_code', 'payment_label', 'amount'], payload.payment_mix || []);
                renderTable('table_top_products', [t('Ref', 'Ref'), t('Label', 'Label'), t('Qty', 'Qty'), t('Amount', 'Amount')], ['product_ref', 'product_label', 'qty', 'amount'], payload.top_products || []);
                renderTable('table_terminal', [t('Terminal', 'Terminal'), t('Tickets', 'Tickets'), t('Amount', 'Amount')], ['terminal_code', 'tickets', 'amount'], payload.terminal_performance || []);
                renderTable('table_shift', ['ID', t('Ref', 'Ref'), t('Status', 'Status'), t('Open', 'Open'), t('Close', 'Close'), t('Expected', 'Expected'), t('Counted', 'Counted'), t('Difference', 'Difference')], ['rowid', 'shift_ref', 'status', 'date_open', 'date_close', 'expected_cash', 'counted_cash', 'cash_difference'], payload.shift_reconciliation_summary || []);

                var ticketCount = payload.cards && payload.cards.tickets_count ? payload.cards.tickets_count : 0;
                showFeedback('success', t('Loaded', 'KPI loaded successfully (%s tickets).').replace('%s', ticketCount));
            })
            .catch(function (err) {
                showFeedback('error', err && err.message ? err.message : t('RunFailed', 'Failed to run KPI query.'));
                renderCards({});
                renderTable('table_sales_hour', [t('Hour', 'Hour'), t('Tickets', 'Tickets'), t('Amount', 'Amount')], ['hour_slot', 'tickets', 'amount'], []);
                renderTable('table_cashier', [t('Cashier', 'Cashier'), t('Tickets', 'Tickets'), t('Amount', 'Amount')], ['cashier_name', 'tickets', 'amount'], []);
                renderTable('table_paymix', [t('Code', 'Code'), t('Label', 'Label'), t('Amount', 'Amount')], ['payment_code', 'payment_label', 'amount'], []);
                renderTable('table_top_products', [t('Ref', 'Ref'), t('Label', 'Label'), t('Qty', 'Qty'), t('Amount', 'Amount')], ['product_ref', 'product_label', 'qty', 'amount'], []);
                renderTable('table_terminal', [t('Terminal', 'Terminal'), t('Tickets', 'Tickets'), t('Amount', 'Amount')], ['terminal_code', 'tickets', 'amount'], []);
                renderTable('table_shift', ['ID', t('Ref', 'Ref'), t('Status', 'Status'), t('Open', 'Open'), t('Close', 'Close'), t('Expected', 'Expected'), t('Counted', 'Counted'), t('Difference', 'Difference')], ['rowid', 'shift_ref', 'status', 'date_open', 'date_close', 'expected_cash', 'counted_cash', 'cash_difference'], []);
            })
            .finally(function () {
                setLoading(false);
            });
    }

    function resetFilters() {
        document.querySelectorAll('.takepos-workspace-filter-grid input, .takepos-workspace-filter-grid select').forEach(function (el) {
            el.value = '';
        });
        showFeedback('info', t('ResetFilters', 'Reset filters'));
        runKpi();
    }

    byId('btn_run').addEventListener('click', runKpi);
    byId('btn_reset').addEventListener('click', resetFilters);
    byId('btn_export').addEventListener('click', function () {
        var p = currentFilters();
        p.action = 'export_csv';
        window.location.href = endpoint + '?' + qs(p);
    });

    document.querySelectorAll('.takepos-workspace-filter-grid input').forEach(function (input) {
        input.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') {
                return;
            }
            event.preventDefault();
            runKpi();
        });
    });

    loadFilters();
    runKpi();
});



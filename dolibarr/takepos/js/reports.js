document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var configNode = document.getElementById('takepos-workspace-reports-config');
    if (!configNode) {
        return;
    }

    var endpoint = configNode.getAttribute('data-endpoint') || '';
    var feedbackNode = document.getElementById('takepos-workspace-report-feedback');
    var loadingNode = document.getElementById('takepos-workspace-report-loading');
    var activeReportType = 'summary';
    var productMap = {};
    var customerMap = {};
    var currentData = null;
    var i18n = window.takeposReportsI18n || {};

    function t(key, fallback) {
        return Object.prototype.hasOwnProperty.call(i18n, key) ? i18n[key] : fallback;
    }

    function byId(id) {
        return document.getElementById(id);
    }

    function normalizeLookupKey(value) {
        var text = (value === null || value === undefined) ? '' : String(value);
        if (typeof text.normalize === 'function') {
            text = text.normalize('NFC');
        }
        text = text.replace(/\u00A0/g, ' ');
        text = text.replace(/\s+/g, ' ').trim();
        return text;
    }

    function setLoading(isLoading, text) {
        if (!loadingNode) {
            return;
        }
        loadingNode.textContent = text || t('Loading', 'Loading...');
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

    function fmtNumber(value) {
        var num = parseFloat(value || 0);
        if (!isFinite(num)) {
            num = 0;
        }
        return num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function getFilters() {
        return {
            action: 'generate',
            date_from: byId('date_from').value,
            date_to: byId('date_to').value,
            cashier_id: byId('cashier_id').value,
            terminal_id: byId('terminal_id').value,
            store_id: byId('store_id').value,
            product_id: byId('product_id').value,
            customer_id: byId('customer_id').value,
            invoice_status: byId('invoice_status').value,
            payment_method: byId('payment_method').value
        };
    }

    function buildQuery(params) {
        var usp = new URLSearchParams();
        Object.keys(params || {}).forEach(function (k) {
            if (params[k] !== null && params[k] !== undefined && params[k] !== '') {
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
        var url = endpoint + '?' + buildQuery(params);
        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Accept': 'application/json'
            }
        }).then(function (res) {
            return res.text().then(function (txt) {
                var parsed = null;
                try {
                    parsed = parseJsonPayload(txt);
                } catch (e) {
                    throw new Error(t('InvalidJson', 'Invalid JSON response from reports endpoint.'));
                }
                if (!res.ok) {
                    var msg = (parsed && parsed.message) ? parsed.message : ('HTTP ' + res.status);
                    throw new Error(msg);
                }
                return parsed;
            });
        });
    }

    function extractData(response) {
        if (!response || typeof response !== 'object') {
            throw new Error(t('UnexpectedEmpty', 'Unexpected empty response.'));
        }

        if (response.success === false) {
            throw new Error(response.message || t('Rejected', 'Operation was rejected.'));
        }

        if (response.data && typeof response.data === 'object') {
            return response.data;
        }

        return response;
    }

    function fillSelect(select, rows, valueFn, labelFn) {
        if (!select) {
            return;
        }

        select.innerHTML = '<option value="">' + t('All', 'All') + '</option>';
        (rows || []).forEach(function (row) {
            var value = valueFn(row);
            if (value === null || value === undefined || value === '') {
                return;
            }
            var option = document.createElement('option');
            option.value = value;
            option.textContent = labelFn(row);
            select.appendChild(option);
        });
    }

    function fillDatalist(datalist, rows, mapObj, keyFn, labelFn) {
        if (!datalist) {
            return;
        }

        datalist.innerHTML = '';
        Object.keys(mapObj).forEach(function (k) {
            delete mapObj[k];
        });

        (rows || []).forEach(function (row) {
            var label = normalizeLookupKey(labelFn(row));
            var key = keyFn(row);
            if (!label || key === null || key === undefined || key === '') {
                return;
            }
            mapObj[label] = key;
            var opt = document.createElement('option');
            opt.value = label;
            datalist.appendChild(opt);
        });
    }

    function setupSearchBinding(inputId, hiddenId, mapObj) {
        var input = byId(inputId);
        var hidden = byId(hiddenId);
        if (!input || !hidden) {
            return;
        }

        input.addEventListener('change', function () {
            hidden.value = mapObj[normalizeLookupKey(this.value)] || '';
        });

        input.addEventListener('input', function () {
            if (!normalizeLookupKey(this.value)) {
                hidden.value = '';
            }
        });
    }

    function setSummaryCards(summary) {
        summary = summary || {};
        byId('card_total_invoices').textContent = summary.total_invoices || 0;
        byId('card_total_qty').textContent = fmtNumber(summary.total_qty);
        byId('card_subtotal_ht').textContent = fmtNumber(summary.subtotal_ht);
        byId('card_total_tax').textContent = fmtNumber(summary.total_tax);
        byId('card_total_discount').textContent = fmtNumber(summary.total_discount);
        byId('card_total_ttc').textContent = fmtNumber(summary.total_ttc);
    }

    function emptyTable(tableId, columnCount, text) {
        var table = byId(tableId);
        if (!table) {
            return;
        }

        table.innerHTML = '<tbody><tr><td class="takepos-workspace-empty" colspan="' + String(columnCount || 1) + '">' + (text || t('NoDataAvailable', 'No data available')) + '</td></tr></tbody>';
    }

    function renderTable(tableId, columns, rows, numericColumns) {
        var table = byId(tableId);
        if (!table) {
            return;
        }

        rows = rows || [];
        numericColumns = numericColumns || [];

        if (rows.length === 0) {
            emptyTable(tableId, columns.length, t('NoMatchingRows', 'No matching rows for current filters.'));
            return;
        }

        var html = '<thead><tr>';
        columns.forEach(function (col) {
            var thClass = numericColumns.indexOf(col.key) !== -1 ? ' class="num"' : '';
            html += '<th' + thClass + ' data-key="' + col.key + '">' + col.label + '</th>';
        });
        html += '</tr></thead><tbody>';

        rows.forEach(function (row) {
            html += '<tr>';
            columns.forEach(function (col) {
                var v = row[col.key];
                if (numericColumns.indexOf(col.key) !== -1) {
                    html += '<td class="num">' + fmtNumber(v) + '</td>';
                } else {
                    html += '<td>' + (v === null || v === undefined ? '' : v) + '</td>';
                }
            });
            html += '</tr>';
        });
        html += '</tbody>';

        table.innerHTML = html;
    }

    function renderAllTables(data) {
        if (!data) {
            return;
        }

        renderTable('table_summary', [
            { key: 'total_invoices', label: t('TotalInvoices', 'Total invoices') },
            { key: 'total_qty', label: t('TotalQuantity', 'Total quantity') },
            { key: 'subtotal_ht', label: t('Subtotal', 'Subtotal') },
            { key: 'total_tax', label: t('Tax', 'Tax') },
            { key: 'total_discount', label: t('Discount', 'Discount') },
            { key: 'total_ttc', label: t('TotalSales', 'Total sales') }
        ], [data.summary || {}], ['total_invoices', 'total_qty', 'subtotal_ht', 'total_tax', 'total_discount', 'total_ttc']);

        renderTable('table_cashier', [
            { key: 'cashier_name', label: t('CashierName', 'Cashier name') },
            { key: 'invoice_count', label: t('NumberOfInvoices', 'Number of invoices') },
            { key: 'total_qty', label: t('QuantitySold', 'Quantity sold') },
            { key: 'total_ttc', label: t('TotalSales', 'Total sales') },
            { key: 'avg_invoice', label: t('AverageInvoiceValue', 'Average invoice value') }
        ], data.by_cashier || [], ['invoice_count', 'total_qty', 'total_ttc', 'avg_invoice']);

        renderTable('table_terminal', [
            { key: 'terminal_name', label: t('TerminalName', 'Terminal name') },
            { key: 'invoice_count', label: t('Invoices', 'Invoices') },
            { key: 'total_qty', label: t('Quantity', 'Quantity') },
            { key: 'total_ttc', label: t('TotalSales', 'Total sales') }
        ], data.by_terminal || [], ['invoice_count', 'total_qty', 'total_ttc']);

        renderTable('table_product', [
            { key: 'product_ref', label: t('ProductRef', 'Product ref') },
            { key: 'product_label', label: t('ProductLabel', 'Product label') },
            { key: 'total_qty', label: t('QuantitySold', 'Quantity sold') },
            { key: 'total_ttc', label: t('TotalSales', 'Total sales') },
            { key: 'avg_price', label: t('AveragePrice', 'Average price') }
        ], data.by_product || [], ['total_qty', 'total_ttc', 'avg_price']);

        renderTable('table_detailed', [
            { key: 'ref', label: t('InvoiceRef', 'Invoice ref') },
            { key: 'invoice_date', label: t('Date', 'Date') },
            { key: 'cashier_name', label: t('Cashier', 'Cashier') },
            { key: 'terminal_name', label: t('Terminal', 'Terminal') },
            { key: 'store_name', label: t('Store', 'Store') },
            { key: 'customer_name', label: t('Customer', 'Customer') },
            { key: 'total_ht', label: t('Subtotal', 'Subtotal') },
            { key: 'total_tax', label: t('Tax', 'Tax') },
            { key: 'total_ttc', label: t('Total', 'Total') },
            { key: 'payment_methods', label: t('PaymentMethod', 'Payment method') },
            { key: 'status_label', label: t('Status', 'Status') }
        ], data.detailed || [], ['total_ht', 'total_tax', 'total_ttc']);

        renderTable('table_cheques', [
            { key: 'ref', label: t('InvoiceRef', 'Ref') },
            { key: 'cheque_number', label: t('ChequeNumber', 'Cheque No.') },
            { key: 'supplier_name', label: t('Supplier', 'Supplier') },
            { key: 'bank_name', label: t('Bank', 'Bank') },
            { key: 'amount', label: t('Amount', 'Amount') },
            { key: 'cheque_date', label: t('ChequeDate', 'Cheque date') },
            { key: 'collection_date', label: t('CollectionDate', 'Collection date') },
            { key: 'status', label: t('Status', 'Status') },
            { key: 'reminder_status', label: t('Reminder', 'Reminder') }
        ], data.cheques || [], ['amount']);

        renderTable('table_receivables', [
            { key: 'ref', label: t('InvoiceRef', 'Invoice ref') },
            { key: 'customer_name', label: t('Customer', 'Customer') },
            { key: 'invoice_date', label: t('Date', 'Date') },
            { key: 'due_date', label: t('DueDate', 'Due date') },
            { key: 'total_ttc', label: t('Total', 'Total') },
            { key: 'paid_amount', label: t('Paid', 'Paid') },
            { key: 'remaining_amount', label: t('Remaining', 'Remaining') },
            { key: 'reminder_status', label: t('Reminder', 'Reminder') }
        ], data.receivables || [], ['total_ttc', 'paid_amount', 'remaining_amount']);

        renderTable('table_payables', [
            { key: 'ref', label: t('SupplierInvoiceRef', 'Supplier invoice') },
            { key: 'supplier_name', label: t('Supplier', 'Supplier') },
            { key: 'invoice_date', label: t('Date', 'Date') },
            { key: 'due_date', label: t('DueDate', 'Due date') },
            { key: 'total_ttc', label: t('Total', 'Total') },
            { key: 'paid_amount', label: t('Paid', 'Paid') },
            { key: 'remaining_amount', label: t('Remaining', 'Remaining') },
            { key: 'reminder_status', label: t('Reminder', 'Reminder') }
        ], data.payables || [], ['total_ttc', 'paid_amount', 'remaining_amount']);

        renderTable('table_product_velocity', [
            { key: 'product_ref', label: t('ProductRef', 'Product ref') },
            { key: 'product_label', label: t('ProductLabel', 'Product label') },
            { key: 'total_qty', label: t('QuantitySold', 'Quantity sold') },
            { key: 'total_ttc', label: t('TotalSales', 'Total sales') },
            { key: 'avg_price', label: t('AveragePrice', 'Average price') },
            { key: 'qty_per_day', label: t('QtyPerDay', 'Qty/day') },
            { key: 'movement_class', label: t('MovementClass', 'Movement class') }
        ], data.product_velocity || [], ['total_qty', 'total_ttc', 'avg_price', 'qty_per_day']);

        renderTable('table_stock_moves', [
            { key: 'movement_date', label: t('MovementDate', 'Date') },
            { key: 'product_ref', label: t('ProductRef', 'Product ref') },
            { key: 'product_label', label: t('ProductLabel', 'Product label') },
            { key: 'warehouse_name', label: t('Warehouse', 'Warehouse') },
            { key: 'qty_movement', label: t('QtyMovement', 'Qty movement') },
            { key: 'movement_type', label: t('MovementType', 'Type') },
            { key: 'movement_label', label: t('MovementLabel', 'Label') },
            { key: 'inventorycode', label: t('InventoryCode', 'Inventory code') },
            { key: 'user_login', label: t('User', 'User') }
        ], data.stock_moves || [], ['qty_movement']);

        renderTable('table_near_expiry', [
            { key: 'product_ref', label: t('ProductRef', 'Product ref') },
            { key: 'product_label', label: t('ProductLabel', 'Product label') },
            { key: 'batch', label: t('Batch', 'Batch') },
            { key: 'warehouse_name', label: t('Warehouse', 'Warehouse') },
            { key: 'qty', label: t('Quantity', 'Quantity') },
            { key: 'expiry_date', label: t('ExpiryDate', 'Expiry date') },
            { key: 'expiry_status', label: t('ExpiryStatus', 'Status') }
        ], data.near_expiry || [], ['qty']);
    }

    function activateTab(reportType) {
        activeReportType = reportType;

        document.querySelectorAll('.takepos-workspace-tab').forEach(function (tab) {
            tab.classList.toggle('active', tab.getAttribute('data-report') === reportType);
        });

        document.querySelectorAll('.takepos-workspace-table-wrap').forEach(function (wrap) {
            wrap.classList.add('hidden');
        });

        var table = byId('table_' + reportType);
        if (table && table.parentElement) {
            table.parentElement.classList.remove('hidden');
        }
    }

    function loadFilters() {
        setLoading(true, t('LoadingFilters', 'Loading filters...'));
        showFeedback(null, '');

        requestJson({ action: 'filters' })
            .then(function (res) {
                var payload = extractData(res);
                var filters = payload.filters || payload;

                fillSelect(byId('cashier_id'), filters.cashiers || [], function (r) {
                    return r.rowid;
                }, function (r) {
                    var fullName = ((r.firstname || '') + ' ' + (r.lastname || '')).trim();
                    return fullName ? (fullName + ' (' + (r.login || '') + ')') : (r.login || '');
                });

                fillSelect(byId('terminal_id'), filters.terminals || [], function (r) {
                    return r.pos_source || r.terminal_code || '';
                }, function (r) {
                    var code = r.pos_source || r.terminal_code || '';
                    var label = r.label || '';
                    return label ? (code + ' - ' + label) : (t('Terminal', 'Terminal') + ' ' + code);
                });

                fillSelect(byId('store_id'), filters.stores || [], function (r) {
                    return r.rowid;
                }, function (r) {
                    return (r.code ? (r.code + ' - ') : '') + (r.label || '');
                });

                fillSelect(byId('invoice_status'), filters.invoice_statuses || [], function (r) {
                    return r.id;
                }, function (r) {
                    return r.label || '';
                });

                fillSelect(byId('payment_method'), filters.payment_methods || [], function (r) {
                    return r.code;
                }, function (r) {
                    var label = r.libelle || r.code || '';
                    return r.code ? (label + ' (' + r.code + ')') : label;
                });

                fillDatalist(byId('product_list'), filters.products || [], productMap, function (r) {
                    return r.rowid;
                }, function (r) {
                    return (r.ref ? (r.ref + ' - ') : '') + (r.label || '');
                });

                fillDatalist(byId('customer_list'), filters.customers || [], customerMap, function (r) {
                    return r.rowid;
                }, function (r) {
                    return r.name || '';
                });
            })
            .catch(function (err) {
                showFeedback('error', err && err.message ? err.message : t('LoadFiltersFailed', 'Unable to load filters.'));
            })
            .finally(function () {
                setLoading(false);
            });
    }

    function generateReport() {
        setLoading(true, t('GeneratingReport', 'Generate Report'));
        showFeedback(null, '');

        requestJson(getFilters())
            .then(function (res) {
                if (res && res.success === false) {
                    throw new Error(res.message || 'Failed to generate report.');
                }

                var payload = extractData(res);
                currentData = payload;
                setSummaryCards(currentData.summary || {});
                renderAllTables(currentData);

                var detailedCount = (currentData.detailed || []).length;
                if (detailedCount === 0) {
                    showFeedback('warning', t('NoMatchingRows', 'No matching rows for current filters.'));
                } else {
                    showFeedback('success', t('GeneratedSuccess', 'Report generated successfully.') + ' (' + detailedCount + ')');
                }
            })
            .catch(function (err) {
                showFeedback('error', err && err.message ? err.message : t('GenerateFailed', 'Unable to generate report data.'));
                currentData = null;
                setSummaryCards({});
                renderAllTables({
                    summary: {},
                    by_cashier: [],
                    by_terminal: [],
                    by_product: [],
                    detailed: [],
                    cheques: [],
                    receivables: [],
                    payables: [],
                    product_velocity: [],
                    stock_moves: [],
                    near_expiry: []
                });
            })
            .finally(function () {
                setLoading(false);
            });
    }

    function resetFilters() {
        document.querySelectorAll('.takepos-workspace-filter-grid input, .takepos-workspace-filter-grid select').forEach(function (el) {
            el.value = '';
        });
        byId('product_id').value = '';
        byId('customer_id').value = '';
        showFeedback('info', t('FiltersReset', 'Reset filters'));
        generateReport();
    }

    function exportCsv() {
        var payload = getFilters();
        payload.action = 'csv';
        payload.report_type = activeReportType;
        window.location.href = endpoint + '?' + buildQuery(payload);
    }

    function initEvents() {
        setupSearchBinding('product_search', 'product_id', productMap);
        setupSearchBinding('customer_search', 'customer_id', customerMap);

        byId('btn_generate').addEventListener('click', generateReport);
        byId('btn_reset').addEventListener('click', resetFilters);
        byId('btn_export').addEventListener('click', exportCsv);
        byId('btn_print').addEventListener('click', function () {
            window.print();
        });

        document.querySelectorAll('.takepos-workspace-filter-grid input').forEach(function (input) {
            input.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter') {
                    return;
                }
                event.preventDefault();
                generateReport();
            });
        });

        document.querySelectorAll('.takepos-workspace-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                activateTab(tab.getAttribute('data-report'));
            });
        });
    }

    /**
     * Apply default date range from data-attributes on the config node.
     * This sets today's date as default if the inputs are empty.
     */
    function applyDefaultDates() {
        var fromInput = byId('date_from');
        var toInput   = byId('date_to');
        if (!fromInput || !toInput || !configNode) {
            return;
        }
        var defaultFrom = configNode.getAttribute('data-default-date-from') || '';
        var defaultTo   = configNode.getAttribute('data-default-date-to')   || '';
        if (!fromInput.value && defaultFrom) {
            fromInput.value = defaultFrom;
        }
        if (!toInput.value && defaultTo) {
            toInput.value = defaultTo;
        }
    }

    initEvents();
    applyDefaultDates();
    loadFilters();
    generateReport();
    activateTab('summary');
});

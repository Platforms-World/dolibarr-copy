<?php
if (!defined('NOREQUIREUSER')) define('NOREQUIREUSER', '1');
if (!defined('NOREQUIREDB')) define('NOREQUIREDB', '1');
if (!defined('NOREQUIRESOC')) define('NOREQUIRESOC', '1');
if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', '1');
require '../../../main.inc.php';
header('Content-Type: application/javascript');
?>
(function () {
    "use strict";

    function qs(sel) { return document.querySelector(sel); }
    function qsa(sel) { return document.querySelectorAll(sel); }

    function money(v) {
        return parseFloat(v || 0).toFixed(2);
    }

    function escapeHtml(str) {
        return String(str || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function post(url, data) {
        var body = new URLSearchParams();
        Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
        return fetch(url, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: body.toString()
        }).then(function (r) { return r.json(); });
    }

    function renderSearch(rows) {
        var tbody = qs("#pos-search-results tbody");
        tbody.innerHTML = "";
        if (!rows || !rows.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="opacitymedium">No results</td></tr>';
            return;
        }
        rows.forEach(function (row) {
            var tr = document.createElement("tr");
            tr.innerHTML =
                '<td>' + escapeHtml(row.product) + '</td>' +
                '<td class="right">' + money(row.price) + '</td>' +
                '<td class="right">' + money(row.stock) + '</td>' +
                '<td class="center"><button type="button" class="button btn-add-product" data-id="' + row.id + '">Add</button></td>';
            tbody.appendChild(tr);
        });
        qsa('.btn-add-product').forEach(function (btn) {
            btn.addEventListener('click', function () { addToCart(this.getAttribute('data-id')); });
        });
    }

    function renderCart(cart) {
        var tbody = qs('#pos-cart-table tbody');
        tbody.innerHTML = '';
        if (!cart || !cart.items || !cart.items.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="opacitymedium">Cart is empty</td></tr>';
            qs('#cart-subtotal').textContent = '0.00';
            qs('#cart-tax').textContent = '0.00';
            qs('#cart-total').textContent = '0.00';
            qs('#payment-grand-total').textContent = '0.00';
            return;
        }
        cart.items.forEach(function (item) {
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' + escapeHtml(item.product) + '</td>' +
                '<td class="right">' + money(item.qty) + '</td>' +
                '<td class="right">' + money(item.price) + '</td>' +
                '<td class="right">' + money(item.discount) + '</td>' +
                '<td class="right">' + money(item.total) + '</td>' +
                '<td class="center"><button type="button" class="button button-delete btn-remove-product" data-id="' + item.product_id + '">X</button></td>';
            tbody.appendChild(tr);
        });
        qsa('.btn-remove-product').forEach(function (btn) {
            btn.addEventListener('click', function () { removeFromCart(this.getAttribute('data-id')); });
        });
        qs('#cart-subtotal').textContent = money(cart.subtotal);
        qs('#cart-tax').textContent = money(cart.tax);
        qs('#cart-total').textContent = money(cart.total);
        qs('#payment-grand-total').textContent = money(cart.total);
    }

    function searchProducts() {
        post(POSCORE_CONF.urls.searchProducts, {
            token: POSCORE_CONF.token,
            q: qs('#pos-search').value || '',
            barcode: qs('#pos-barcode').value || ''
        }).then(function (json) {
            if (!json.success) {
                alert(json.error || 'Search failed');
                return;
            }
            renderSearch(json.rows);
        }).catch(function () {
            alert('Search request failed');
        });
    }

    function addToCart(productId) {
        post(POSCORE_CONF.urls.addToCart, {
            token: POSCORE_CONF.token,
            product_id: productId,
            qty: 1
        }).then(function (json) {
            if (!json.success) {
                alert(json.error || 'Add to cart failed');
                return;
            }
            renderCart(json.cart);
        }).catch(function () {
            alert('Add to cart request failed');
        });
    }

    function removeFromCart(productId) {
        post(POSCORE_CONF.urls.removeFromCart, {
            token: POSCORE_CONF.token,
            product_id: productId
        }).then(function (json) {
            if (!json.success) {
                alert(json.error || 'Remove request failed');
                return;
            }
            renderCart(json.cart);
        }).catch(function () {
            alert('Remove request failed');
        });
    }

    function clearCart() {
        post(POSCORE_CONF.urls.clearCart, {
            token: POSCORE_CONF.token
        }).then(function (json) {
            if (!json.success) {
                alert(json.error || 'Clear cart failed');
                return;
            }
            renderCart(json.cart);
        }).catch(function () {
            alert('Clear cart request failed');
        });
    }

    function createInvoice(method) {
        var customerId = qs('#pos-customer').value || '0';
        if (customerId === '0') {
            alert('Please select customer');
            return;
        }
        post(POSCORE_CONF.urls.createInvoice, {
            token: POSCORE_CONF.token,
            customer_id: customerId,
            payment_method: method
        }).then(function (json) {
            if (!json.success) {
                alert(json.error || 'Create invoice failed');
                return;
            }
            alert('Invoice created: ' + (json.invoice_ref || json.invoice_id));
            renderCart({items: [], subtotal: 0, tax: 0, total: 0});
        }).catch(function () {
            alert('Create invoice request failed');
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        renderCart(POSCORE_CONF.initialCart || {items: [], subtotal: 0, tax: 0, total: 0});
        var searchBtn = qs('#btn-search-products');
        if (searchBtn) searchBtn.addEventListener('click', searchProducts);
        var searchInput = qs('#pos-search');
        if (searchInput) searchInput.addEventListener('keypress', function (e) { if (e.key === 'Enter') searchProducts(); });
        var barcodeInput = qs('#pos-barcode');
        if (barcodeInput) barcodeInput.addEventListener('keypress', function (e) { if (e.key === 'Enter') searchProducts(); });
        qsa('.button-pay').forEach(function (btn) {
            btn.addEventListener('click', function () { createInvoice(this.getAttribute('data-payment')); });
        });
        var clearBtn = qs('#btn-clear-cart');
        if (clearBtn) clearBtn.addEventListener('click', clearCart);
        var holdBtn = qs('#btn-hold-order');
        if (holdBtn) holdBtn.addEventListener('click', function () { alert('Hold order feature is registered in saascore. Implement hold order endpoint next.'); });
    });
})();

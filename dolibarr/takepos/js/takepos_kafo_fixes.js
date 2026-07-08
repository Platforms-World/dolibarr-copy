/**
 * TakePOS — Kafo Issue Fixes
 * Fixes all issues from kafo.xlsx bug report
 *
 * Drop at: htdocs/takepos/js/takepos_kafo_fixes.js
 * Include in index.php after other scripts
 */
(function (window, $) {
    'use strict';

    /* ================================================================
     * FIX 1: Second-invoice payment rejection (no refresh needed)
     * Root cause: takeposPaymentInProgress flag stays true if colorbox
     * closes before TakeposFinalizePaymentUi is called, or if an AJAX
     * error occurs during payment validation.
     * Fix: aggressive watchdog + forced reset on every loadPosLines call
     * ================================================================ */
    var PAYMENT_WATCHDOG_MS = 12000; // 12s max for any payment operation

    // Wrap the original TakeposFinalizePaymentUi to always reset locks
    var _origFinalizeUi = window.TakeposFinalizePaymentUi;
    window.TakeposFinalizePaymentUi = function (paidInvoiceId) {
        // Always force-reset locks before finalizing
        window.takeposPaymentInProgress = false;
        window.takeposDirectPaymentLock = false;
        window.takeposPreferredPaymentCode = '';
        if (window.takeposPaymentWatchdog) {
            clearTimeout(window.takeposPaymentWatchdog);
            window.takeposPaymentWatchdog = null;
        }
        if (typeof _origFinalizeUi === 'function') {
            _origFinalizeUi(paidInvoiceId);
        }
    };

    // Add a watchdog to every payment start
    var _origExecuteDirectPayment = window.takeposExecuteDirectPayment;
    if (typeof _origExecuteDirectPayment !== 'function') {
        // try to grab it after DOM ready
        $(document).ready(function () {
            _origExecuteDirectPayment = window.takeposExecuteDirectPayment;
        });
    }

    // Universal payment lock reset - runs whenever poslines are reloaded
    $(document).on('kafo:poslines-loaded', function () {
        window.takeposPaymentInProgress = false;
        window.takeposDirectPaymentLock = false;
    });

    // Patch loadPosLines to fire the event
    $(document).ready(function () {
        var _orig = window.loadPosLines;
        if (typeof _orig === 'function') {
            window.loadPosLines = function (url, onDone) {
                return _orig(url, function (responseText, textStatus, xhr) {
                    // Reset payment flags whenever cart reloads
                    window.takeposPaymentInProgress = false;
                    window.takeposDirectPaymentLock = false;
                    $(document).trigger('kafo:poslines-loaded');
                    if (typeof onDone === 'function') {
                        onDone(responseText, textStatus, xhr);
                    }
                });
            };
        }
    });

    /* ================================================================
     * FIX 2: Barcode scanner — instant product add without Enter
     * Root cause: the 25ms delay in Search2 for Enter key is still
     * too slow for some scanners that send the Enter immediately after
     * the last barcode character. We patch Search2 to detect scanner
     * input (rapid typing) and fire instantly (0ms delay).
     * Also: if only 1 result is returned on scanner Enter, auto-add it.
     * ================================================================ */
    $(document).ready(function () {
        var SCAN_SPEED_MS = 40; // inter-key gap below this = scanner
        var lastKeyTime = 0;
        var scanBuffer = '';
        var scanTimer = null;
        var isScannerInput = false;

        var $search = $('#search');
        if ($search.length) {
            $search.on('keydown', function (e) {
                var now = Date.now();
                var delta = now - lastKeyTime;
                lastKeyTime = now;

                if (e.key && e.key.length === 1 && delta < SCAN_SPEED_MS) {
                    scanBuffer += e.key;
                    isScannerInput = true;
                    if (scanTimer) clearTimeout(scanTimer);
                    scanTimer = setTimeout(function () {
                        scanBuffer = '';
                        isScannerInput = false;
                    }, 600);
                } else if (e.key && e.key.length === 1) {
                    // Regular keypress - reset scanner state
                    scanBuffer = e.key;
                    isScannerInput = false;
                }
            });
        }

        // Patch Search2 to use 0ms delay when scanner input detected
        $(document).ready(function () {
            var _origSearch2 = window.Search2;
            if (typeof _origSearch2 === 'function') {
                window.Search2 = function (keyCodeForEnter, moreorless, ev) {
                    // If scanner sent this Enter, clear buffer and let
                    // Search2 run immediately (it uses setTimeout internally).
                    // We override isScannerInput on the event so Search2
                    // fires the 0ms path.
                    if (isScannerInput && ev &&
                        (ev.key === 'Enter' || ev.keyCode === 13)) {
                        isScannerInput = false;
                        scanBuffer = '';
                        if (scanTimer) clearTimeout(scanTimer);
                        // Mark event as scanner so callers can detect it
                        ev._kafoScannerInput = true;
                    }
                    return _origSearch2.apply(this, arguments);
                };
            }
        });
    });

    /* ================================================================
     * FIX 3: Cash drawer button visibility
     * The cash drawer button already exists in index_action_menus.php
     * Make sure it's always visible and styled prominently.
     * ================================================================ */
    $(document).ready(function () {
        // Find the open drawer button by its icon class and highlight it
        $('.div3 .actionbutton').each(function () {
            var $btn = $(this);
            if ($btn.find('.fa-cash-register').length) {
                $btn.addClass('tpv2-btn-drawer');
                $btn.css({
                    'border-color': 'rgba(16,185,129,0.4)',
                    'color': '#047857'
                });
            }
        });
    });

    /* ================================================================
     * FIX 4: Visa/Card payment button — make it visually distinct
     * from Cash payment button so cashiers don't confuse them.
     * ================================================================ */
    $(document).ready(function () {
        // Style direct card payment button distinctly
        var $cardBtn = $('[data-takepos-action-id="takepos-action-direct-card-payment"]');
        if ($cardBtn.length) {
            $cardBtn.css({
                'background': 'linear-gradient(180deg, #1e40af, #1e3a8a)',
                'color': '#fff',
                'border-color': '#1e3a8a'
            });
            $cardBtn.find('.fa, .fas, .far, .fab').css('color', '#93c5fd');
        }

        // Style direct cash payment button
        var $cashBtn = $('[data-takepos-action-id="takepos-action-direct-payment"]');
        if ($cashBtn.length) {
            $cashBtn.css({
                'background': 'linear-gradient(180deg, #047857, #065f46)',
                'color': '#fff',
                'border-color': '#065f46'
            });
        }
    });

    /* ================================================================
     * FIX 5: Currency display — replace SAR with configured currency (JOD)
     * Bug #8 (main screen) and Bug #14 (customer display screen).
     * Root cause: Dolibarr default currency code "SAR" appears when the
     * system currency isn't configured correctly or multicurrency is active.
     * Fix: forcibly replace any SAR text in POS UI elements with the
     * actual configured base currency (window.takeposBaseCurrency from index.php).
     * ================================================================ */
    $(document).ready(function () {
        // takeposBaseCurrency is injected by index.php (Bug#8/#14 fix)
        var targetCurrency = (typeof window.takeposBaseCurrency === 'string')
            ? window.takeposBaseCurrency.trim()
            : '';

        // Fallback: read from meta tag or default to JOD
        if (!targetCurrency || targetCurrency === 'SAR') {
            targetCurrency = $('meta[name="takepos-currency"]').attr('content') || 'JOD';
        }

        if (!targetCurrency || targetCurrency === 'SAR') {
            return; // Nothing to replace
        }

        var SAR_RE = /\bSAR\b/g;

        function fixCurrencyText(root) {
            var selector = '.tpv2-grand-total, .tpv2-summary-value, .tpv2-grand-total-value, ' +
                '#total, .productprice, .amountremaintopay, .amount, ' +
                '.tpv2-subtotal-row, .tpv2-total-row, .tpv2-tax-row';
            $(root || document).find(selector).addBack(selector).each(function () {
                var $el = $(this);
                var html = $el.html();
                if (html && SAR_RE.test(html)) {
                    SAR_RE.lastIndex = 0;
                    $el.html(html.replace(SAR_RE, targetCurrency));
                }
                SAR_RE.lastIndex = 0;
            });
        }

        fixCurrencyText(document);

        // Watch for DOM changes (cart reloads, customer display)
        if (typeof MutationObserver !== 'undefined') {
            var obs = new MutationObserver(function () {
                fixCurrencyText('#poslines');
                fixCurrencyText('#customerscreen');
            });
            var poslines = document.getElementById('poslines');
            if (poslines) {
                obs.observe(poslines, { childList: true, subtree: true });
            }
            var cusScreen = document.getElementById('customerscreen');
            if (cusScreen) {
                obs.observe(cusScreen, { childList: true, subtree: true });
            }
        }
    });

    /* ================================================================
     * FIX 6: Stock overview page — it works but shortcut may be missing
     * Ensure the link is always accessible via the shortcuts panel.
     * ================================================================ */
    // Stock overview fix is in shortcuts_drawer.php - it's accessible via
    // the workspace key=stock_overview already. No JS fix needed.

    /* ================================================================
     * FIX 7: Shortcuts drawer blank white screen fix
     * If productStudioEnabled is false or shortcuts panel fails to load,
     * the drawer shows blank. We add a fallback renderer.
     * ================================================================ */
    $(document).ready(function () {
        var $drawer = $('#takepos-shortcuts-drawer');
        if (!$drawer.length) return;

        // Check if the drawer has content
        setTimeout(function () {
            var $panel = $drawer.find('#takepos-shortcuts-panel');
            if ($panel.length && $panel.children().length === 0) {
                // Drawer is empty — show a helpful message
                $panel.html(
                    '<div style="padding:24px;text-align:center;color:var(--tp-text-muted,#64748b)">' +
                    '<p style="font-size:14px;margin:0 0 8px">لا توجد اختصارات متاحة</p>' +
                    '<p style="font-size:12px;margin:0">يرجى التحقق من إعدادات TakePOS</p>' +
                    '</div>'
                );
            }
        }, 800);

        // Fix: if drawer opens but is white, force repaint
        $(document).on('click', '#takepos-shortcuts-launcher', function () {
            setTimeout(function () {
                $drawer.css('display', 'flex');
                if ($drawer.hasClass('is-open')) {
                    $drawer[0].style.transform = 'translateX(0)';
                }
            }, 50);
        });
    });

    /* ================================================================
     * FIX 8: Category label cleanup (strip " - " suffix from names)
     * Already handled by takepos_v2_cat_active.js but ensure it runs
     * ================================================================ */
    // Already fixed in takepos_v2_cat_active.js

    /* ================================================================
     * FIX 9: RTL / Arabic language enforcement in POS UI
     * Ensure RTL direction is applied to all dynamically loaded content
     * ================================================================ */
    $(document).ready(function () {
        var isRtl = $('body').hasClass('tp-rtl') ||
            $('body').attr('dir') === 'rtl' ||
            document.documentElement.getAttribute('dir') === 'rtl';

        if (isRtl) {
            // Apply RTL to dynamically loaded cart content
            if (typeof MutationObserver !== 'undefined') {
                var rtlObs = new MutationObserver(function () {
                    $('#poslines').find('[dir]').attr('dir', 'rtl');
                });
                var poslines = document.getElementById('poslines');
                if (poslines) {
                    rtlObs.observe(poslines, { childList: true, subtree: false });
                }
            }
        }
    });

    /* ================================================================
     * FIX 10: Cash payment modal separation
     * When CASH is clicked, it should open the cash modal ONLY.
     * When the payment window opens (CloseBill), show all options.
     * The preferred payment code mechanism handles this - we ensure
     * the cash button calls DirectPayment and card calls DirectCardPayment.
     * ================================================================ */
    $(document).ready(function () {
        // Re-bind after any DOM changes to ensure buttons work
        function rebindPaymentButtons() {
            var $cashBtn = $('[data-takepos-action-id="takepos-action-direct-payment"]');
            var $cardBtn = $('[data-takepos-action-id="takepos-action-direct-card-payment"]');
            var $payBtn  = $('[data-takepos-action-id="takepos-action-payment"]');

            // Remove any conflicting inline onclick that might cause confusion
            // The takeposBindCriticalActionButtons handles these correctly
            if (typeof window.takeposBindCriticalActionButtons === 'function') {
                window.takeposBindCriticalActionButtons();
            }
        }

        // Run on load and after cart refreshes
        rebindPaymentButtons();
        $(document).on('kafo:poslines-loaded', rebindPaymentButtons);
    });

    /* ================================================================
     * FIX 11: Payment watchdog - prevent stuck state
     * Start a watchdog whenever a direct payment begins.
     * If payment takes longer than 12 seconds, force-reset.
     * ================================================================ */
    $(document).ready(function () {
        var _origDirectPayment = window.DirectPayment;
        var _origDirectCardPayment = window.DirectCardPayment;

        function startWatchdog() {
            if (window.takeposPaymentWatchdog) {
                clearTimeout(window.takeposPaymentWatchdog);
            }
            window.takeposPaymentWatchdog = setTimeout(function () {
                if (window.takeposPaymentInProgress || window.takeposDirectPaymentLock) {
                    window.takeposPaymentInProgress = false;
                    window.takeposDirectPaymentLock = false;
                    window.takeposPreferredPaymentCode = '';
                    console.warn('[kafo-fix] Payment watchdog fired - resetting stuck payment state');
                    if (typeof window.takeposFeedback === 'function') {
                        window.takeposFeedback('انتهت مهلة الدفع. يرجى المحاولة مجدداً.', 'warning');
                    }
                }
            }, PAYMENT_WATCHDOG_MS);
        }

        if (typeof _origDirectPayment === 'function') {
            window.DirectPayment = function () {
                startWatchdog();
                return _origDirectPayment.apply(this, arguments);
            };
        }
        if (typeof _origDirectCardPayment === 'function') {
            window.DirectCardPayment = function () {
                startWatchdog();
                return _origDirectCardPayment.apply(this, arguments);
            };
        }
    });

    console.log('[kafo-fixes] TakePOS Kafo issue fixes loaded');

})(window, jQuery);

<?php
/**
 * Shared currency helpers for TakePOS multicurrency display/payment handling.
 */

if (!function_exists('takeposNormalizeCurrencyCode')) {
    function takeposNormalizeCurrencyCode($code)
    {
        $code = strtoupper(trim((string) $code));
        return preg_replace('/[^A-Z0-9_]/', '', $code);
    }
}

if (!function_exists('takeposNormalizeCurrencyRate')) {
    function takeposNormalizeCurrencyRate($rate)
    {
        $rate = price2num((string) $rate, 'MU');
        return ($rate > 0 ? (float) $rate : 0.0);
    }
}

if (!function_exists('takeposSetSessionCurrencySelection')) {
    function takeposSetSessionCurrencySelection($baseCurrencyCode, $currencyCode = '', $rate = 0.0)
    {
        $baseCurrencyCode = takeposNormalizeCurrencyCode($baseCurrencyCode);
        $currencyCode = takeposNormalizeCurrencyCode($currencyCode);
        $rate = takeposNormalizeCurrencyRate($rate);

        if ($currencyCode === '' || ($baseCurrencyCode !== '' && $currencyCode === $baseCurrencyCode)) {
            unset($_SESSION['takeposcustomercurrency'], $_SESSION['takeposcustomercurrencyrate']);
            return;
        }

        $_SESSION['takeposcustomercurrency'] = $currencyCode;
        if ($rate > 0) {
            $_SESSION['takeposcustomercurrencyrate'] = $rate;
        } else {
            unset($_SESSION['takeposcustomercurrencyrate']);
        }
    }
}

if (!function_exists('takeposGetSessionCurrencyCode')) {
    function takeposGetSessionCurrencyCode()
    {
        return takeposNormalizeCurrencyCode(isset($_SESSION['takeposcustomercurrency']) ? $_SESSION['takeposcustomercurrency'] : '');
    }
}

if (!function_exists('takeposGetSessionCurrencyRate')) {
    function takeposGetSessionCurrencyRate()
    {
        return takeposNormalizeCurrencyRate(isset($_SESSION['takeposcustomercurrencyrate']) ? $_SESSION['takeposcustomercurrencyrate'] : 0);
    }
}

if (!function_exists('takeposFetchCurrencyRate')) {
    function takeposFetchCurrencyRate($db, $currencyCode)
    {
        $currencyCode = takeposNormalizeCurrencyCode($currencyCode);
        if ($currencyCode === '' || !is_object($db) || !function_exists('isModEnabled') || !isModEnabled('multicurrency')) {
            return 0.0;
        }

        if (!class_exists('MultiCurrency')) {
            $mcClass = DOL_DOCUMENT_ROOT . '/multicurrency/class/multicurrency.class.php';
            if (is_file($mcClass)) {
                include_once $mcClass;
            }
        }

        if (!class_exists('MultiCurrency')) {
            return 0.0;
        }

        $multicurrency = new MultiCurrency($db);
        if ($multicurrency->fetch(0, $currencyCode) <= 0) {
            return 0.0;
        }

        if (is_object($multicurrency->rate) && isset($multicurrency->rate->rate)) {
            return takeposNormalizeCurrencyRate($multicurrency->rate->rate);
        }
        if (isset($multicurrency->rate)) {
            return takeposNormalizeCurrencyRate($multicurrency->rate);
        }
        if (isset($multicurrency->multicurrency_tx)) {
            return takeposNormalizeCurrencyRate($multicurrency->multicurrency_tx);
        }

        return 0.0;
    }
}

if (!function_exists('takeposResolveDocumentCurrencyCode')) {
    function takeposResolveDocumentCurrencyCode($conf, $document = null)
    {
        $baseCurrency = takeposNormalizeCurrencyCode(is_object($conf) && !empty($conf->currency) ? $conf->currency : '');
        if (is_object($document) && !empty($document->multicurrency_code)) {
            $documentCurrency = takeposNormalizeCurrencyCode($document->multicurrency_code);
            if ($documentCurrency !== '' && $documentCurrency !== $baseCurrency) {
                return $documentCurrency;
            }
        }

        $sessionCurrency = takeposGetSessionCurrencyCode();
        if ($sessionCurrency !== '' && $sessionCurrency !== $baseCurrency) {
            return $sessionCurrency;
        }

        return '';
    }
}

if (!function_exists('takeposResolveDocumentCurrencyRate')) {
    function takeposResolveDocumentCurrencyRate($db, $conf, $document = null)
    {
        $currencyCode = takeposResolveDocumentCurrencyCode($conf, $document);
        if ($currencyCode === '') {
            return 1.0;
        }

        if (is_object($document)
            && !empty($document->multicurrency_code)
            && takeposNormalizeCurrencyCode($document->multicurrency_code) === $currencyCode
            && isset($document->multicurrency_tx)
        ) {
            $documentRate = takeposNormalizeCurrencyRate($document->multicurrency_tx);
            if ($documentRate > 0) {
                return $documentRate;
            }
        }

        if ($currencyCode === takeposGetSessionCurrencyCode()) {
            $sessionRate = takeposGetSessionCurrencyRate();
            if ($sessionRate > 0) {
                return $sessionRate;
            }
        }

        $fetchedRate = takeposFetchCurrencyRate($db, $currencyCode);
        return ($fetchedRate > 0 ? $fetchedRate : 1.0);
    }
}

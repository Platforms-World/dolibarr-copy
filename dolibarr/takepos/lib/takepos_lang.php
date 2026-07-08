<?php
/**
 * TakePOS forced language helper.
 */

if (!function_exists('takeposForceUtf8Output')) {
    function takeposForceUtf8Output()
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }

        @ini_set('default_charset', 'UTF-8');

        if (function_exists('mb_internal_encoding')) {
            @mb_internal_encoding('UTF-8');
        }

        if (function_exists('mb_http_output')) {
            @mb_http_output('UTF-8');
        }
    }
}

if (!function_exists('takeposApplyForcedLanguage')) {
    function takeposApplyForcedLanguage($langs, $user = null)
    {
        takeposForceUtf8Output();

        $allowed = array('en_US', 'ar_JO');
        $forced = '';

        if (!empty($_SESSION['forcelang']) && in_array($_SESSION['forcelang'], $allowed, true)) {
            $forced = (string) $_SESSION['forcelang'];
        }

        if (!empty($_GET['langs']) && in_array($_GET['langs'], $allowed, true)) {
            $forced = (string) $_GET['langs'];
            $_SESSION['forcelang'] = $forced;
        }

        if ($forced === '') {
            return '';
        }

        if (is_object($langs) && method_exists($langs, 'setDefaultLang')) {
            $langs->setDefaultLang($forced);
        }

        if (is_object($user)) {
            if (!isset($user->conf) || !is_object($user->conf)) {
                $user->conf = new stdClass();
            }
            $user->conf->MAIN_LANG_DEFAULT = $forced;
        }

        return $forced;
    }
}


if (!function_exists('takeposCurrentLangCode')) {
    function takeposCurrentLangCode($langs = null, $user = null)
    {
        $candidates = array();

        if (!empty($_SESSION['forcelang'])) {
            $candidates[] = (string) $_SESSION['forcelang'];
        }
        if (!empty($_GET['langs'])) {
            $candidates[] = (string) $_GET['langs'];
        }
        if (is_object($langs)) {
            foreach (array('defaultlang', 'defaultlangfile', 'charset_output') as $prop) {
                if (!empty($langs->{$prop}) && is_string($langs->{$prop})) {
                    $candidates[] = (string) $langs->{$prop};
                }
            }
            if (method_exists($langs, 'getDefaultLang')) {
                $defaultLang = $langs->getDefaultLang();
                if (!empty($defaultLang) && is_string($defaultLang)) {
                    $candidates[] = (string) $defaultLang;
                }
            }
        }
        if (is_object($user) && isset($user->conf) && is_object($user->conf) && !empty($user->conf->MAIN_LANG_DEFAULT)) {
            $candidates[] = (string) $user->conf->MAIN_LANG_DEFAULT;
        }
        if (!empty($GLOBALS['conf']->global->MAIN_LANG_DEFAULT)) {
            $candidates[] = (string) $GLOBALS['conf']->global->MAIN_LANG_DEFAULT;
        }

        foreach ($candidates as $candidate) {
            $candidate = str_replace('-', '_', strtolower((string) $candidate));
            if (strpos($candidate, 'ar') === 0) {
                return 'ar_JO';
            }
            if (strpos($candidate, 'en') === 0) {
                return 'en_US';
            }
        }

        return 'en_US';
    }
}

if (!function_exists('takeposIsArabicLang')) {
    function takeposIsArabicLang($langs = null, $user = null)
    {
        return takeposCurrentLangCode($langs, $user) === 'ar_JO';
    }
}

if (!function_exists('takeposTranslateWithFallback')) {
    function takeposTranslateWithFallback($langs, $key, $fallbackArabic, $fallbackEnglish = '')
    {
        $translated = '';
        if (is_object($langs) && method_exists($langs, 'trans')) {
            $translated = (string) $langs->trans($key);
        }

        $normalized = trim((string) $translated);
        if ($normalized !== '' && $normalized !== (string) $key) {
            return $translated;
        }

        if (takeposIsArabicLang($langs, isset($GLOBALS['user']) ? $GLOBALS['user'] : null)) {
            return (string) $fallbackArabic;
        }

        return ($fallbackEnglish !== '' ? (string) $fallbackEnglish : (string) $key);
    }
}

<?php
/**
 * Arabic-safe category creation page for TakePOS workspace.
 *
 * This page avoids false duplicate-ref collisions when legacy transliteration
 * produces empty/unstable refs for non-Latin labels.
 */

require '../main.inc.php';

require_once __DIR__ . '/lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/class/TakeposAccess.class.php';
require_once __DIR__ . '/class/TakeposInputValidator.class.php';
require_once __DIR__ . '/class/TakeposUtf8.class.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

$langs->loadLangs(array('cashdesk', 'categories', 'main', 'admin', 'takeposcustom@takepos'));

$terminal = isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null;
TakeposAccess::requireFrontendAccess(
    $db,
    $user,
    'takepos.catalog.add_category',
    'takepos.use',
    $terminal,
    $langs->trans('TakeposCategoryCreateAccessDenied'),
    array('page' => 'category_create.php')
);

if (empty($user->admin) && !$user->hasRight('categorie', 'creer')) {
    accessforbidden($langs->trans('TakeposCategoryCreateNotAllowed'));
}

TakeposUtf8::bootstrapConnection($db);

/**
 * Legacy-style transliteration that often fails for Arabic.
 * This is used for debug visibility only.
 */
function takeposLegacyCategoryRefFromLabel($label)
{
    $label = TakeposInputValidator::normalizeUtf8Text($label, 255, true);
    if ($label === '') {
        return '';
    }

    $legacy = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label);
    if (!is_string($legacy)) {
        $legacy = '';
    }

    $legacy = preg_replace('/[^A-Za-z0-9_-]+/', '', $legacy);
    if ($legacy === null) {
        $legacy = '';
    }

    return strtoupper(trim($legacy));
}

/**
 * Arabic-safe deterministic ref generation.
 */
function takeposSafeCategoryRefFromLabel($label)
{
    $label = TakeposInputValidator::normalizeUtf8Text($label, 255, true);
    if ($label === '') {
        return '';
    }

    $legacy = takeposLegacyCategoryRefFromLabel($label);
    if ($legacy !== '') {
        return $legacy;
    }

    // Preserve uniqueness deterministically even when transliteration is empty.
    return 'CAT-' . strtoupper(substr(sha1($label), 0, 10));
}

function takeposCategoryFindByRef($db, $entity, $type, $ref)
{
    $ref = TakeposInputValidator::normalizeUtf8Text($ref, 255, true);
    if ($ref === '') {
        return null;
    }

    $sql = 'SELECT rowid, ref, label, fk_parent'
        . ' FROM ' . MAIN_DB_PREFIX . 'categorie'
        . ' WHERE entity = ' . ((int) $entity)
        . ' AND type = ' . ((int) $type)
        . " AND ref = '" . $db->escape($ref) . "'"
        . ' LIMIT 1';

    $resql = $db->query($sql);
    if (!$resql) {
        return null;
    }

    $obj = $db->fetch_object($resql);
    return $obj ?: null;
}

function takeposCategoryLabelExistsInParent($db, $entity, $type, $parentId, $label)
{
    $label = TakeposInputValidator::normalizeUtf8Text($label, 255, true);
    if ($label === '') {
        return null;
    }

    $sql = 'SELECT rowid, label, ref, fk_parent'
        . ' FROM ' . MAIN_DB_PREFIX . 'categorie'
        . ' WHERE entity = ' . ((int) $entity)
        . ' AND type = ' . ((int) $type)
        . " AND label = '" . $db->escape($label) . "'";

    if ((int) $parentId > 0) {
        $sql .= ' AND fk_parent = ' . ((int) $parentId);
    } else {
        $sql .= ' AND (fk_parent IS NULL OR fk_parent = 0)';
    }

    $sql .= ' LIMIT 1';

    $resql = $db->query($sql);
    if (!$resql) {
        return null;
    }

    $obj = $db->fetch_object($resql);
    return $obj ?: null;
}

function takeposBuildUniqueCategoryRef($db, $entity, $type, $baseRef)
{
    $baseRef = TakeposInputValidator::normalizeUtf8Text($baseRef, 200, true);
    if ($baseRef === '') {
        $baseRef = 'CAT-' . strtoupper(substr(sha1((string) dol_now()), 0, 10));
    }

    if (!takeposCategoryFindByRef($db, $entity, $type, $baseRef)) {
        return $baseRef;
    }

    for ($i = 2; $i <= 9999; $i++) {
        $candidate = $baseRef . '-' . $i;
        if (!takeposCategoryFindByRef($db, $entity, $type, $candidate)) {
            return $candidate;
        }
    }

    return $baseRef . '-' . strtoupper(substr(sha1($baseRef . '|' . dol_now()), 0, 6));
}

$action = GETPOST('action', 'aZ09');
$token = TakeposInputValidator::normalizeUtf8Text(GETPOST('token', 'none'), 128, true);
$sessionToken = isset($_SESSION['newtoken']) ? (string) $_SESSION['newtoken'] : '';

$entity = !empty($conf->entity) ? (int) $conf->entity : (!empty($user->entity) ? (int) $user->entity : 1);
$type = GETPOSTINT('category_type');
if ($type < 0) {
    $type = 0;
}
$parentId = GETPOSTINT('parent_id');
$label = TakeposInputValidator::normalizeUtf8Text(GETPOST('label', 'none'), 255, true);
$description = TakeposInputValidator::normalizeUtf8Text(GETPOST('description', 'none'), 2000, false);
$manualRef = TakeposInputValidator::normalizeUtf8Text(GETPOST('ref', 'none'), 128, true);

$message = '';
$error = '';
$debug = array();

$sampleLabel = 'تست عربي';
$sampleLegacyRef = takeposLegacyCategoryRefFromLabel($sampleLabel);
$sampleSafeRef = takeposSafeCategoryRefFromLabel($sampleLabel);
$sampleLegacyCollision = null;
if ($sampleLegacyRef !== '') {
    $sampleLegacyCollision = takeposCategoryFindByRef($db, $entity, $type, $sampleLegacyRef);
}

if ($action === 'create') {
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        $error = $langs->trans('TakeposCategoryCreateInvalidCsrf');
    } elseif ($label === '') {
        $error = $langs->trans('TakeposCategoryCreateLabelRequired');
    } else {
        $legacyRef = takeposLegacyCategoryRefFromLabel($label);
        $autoRef = takeposSafeCategoryRefFromLabel($label);
        $requestedRef = ($manualRef !== '' ? $manualRef : $autoRef);

        $debug['label'] = $label;
        $debug['legacy_ref'] = $legacyRef;
        $debug['auto_safe_ref'] = $autoRef;
        $debug['requested_ref'] = $requestedRef;

        $sameLabel = takeposCategoryLabelExistsInParent($db, $entity, $type, $parentId, $label);
        if ($sameLabel) {
            $error = $langs->trans('TakeposCategoryCreateSameLabelParent');
            $debug['label_collision_rowid'] = (int) $sameLabel->rowid;
            $debug['label_collision_ref'] = (string) $sameLabel->ref;
        }

        if ($error === '') {
            $existingRef = takeposCategoryFindByRef($db, $entity, $type, $requestedRef);
            if ($existingRef) {
                $debug['ref_collision_rowid'] = (int) $existingRef->rowid;
                $debug['ref_collision_label'] = (string) $existingRef->label;

                if ($manualRef !== '') {
                    // Preserve duplicate protection for explicit ref entry.
                    $error = $langs->trans('TakeposCategoryCreateDuplicateRef', $requestedRef);
                } else {
                    // Auto-generation path: avoid false duplicate by producing unique suffix.
                    $requestedRef = takeposBuildUniqueCategoryRef($db, $entity, $type, $requestedRef);
                    $debug['resolved_unique_ref'] = $requestedRef;
                }
            }
        }

        if ($error === '') {
            $cat = new Categorie($db);
            $cat->entity = $entity;
            $cat->type = (int) $type;
            $cat->label = $label;
            $cat->description = $description;
            $cat->ref = $requestedRef;
            if ($parentId > 0) {
                $cat->fk_parent = (int) $parentId;
            }

            $res = $cat->create($user);
            if ($res > 0) {
                header('Location: ' . DOL_URL_ROOT . '/categories/card.php?id=' . ((int) $res));
                exit;
            }

            $error = !empty($cat->error) ? $cat->error : $langs->trans('TakeposCategoryCreateUnableCreate');
            if (!empty($cat->errors) && is_array($cat->errors)) {
                $error .= ' ' . implode(' | ', $cat->errors);
            }
        }
    }
}

llxHeader('', $langs->trans('TakeposCategoryCreateTitle'));

print load_fiche_titre($langs->trans('TakeposCategoryCreateSubtitle'));
print '<div class="opacitymedium" style="margin-bottom:10px;">' . dol_escape_htmltag($langs->trans('TakeposCategoryCreateIntro')) . '</div>';

if ($message !== '') {
    print '<div class="ok">' . dol_escape_htmltag($message) . '</div>';
}
if ($error !== '') {
    print '<div class="error">' . dol_escape_htmltag($error) . '</div>';
}

print '<div class="info" style="margin:8px 0;">';
print '<strong>' . dol_escape_htmltag($langs->trans('TakeposCategoryCreateDebugSample', $sampleLabel)) . '</strong><br>';
print dol_escape_htmltag($langs->trans('TakeposCategoryCreateDebugLegacyRef')) . ': <code>' . dol_escape_htmltag($sampleLegacyRef === '' ? $langs->trans('TakeposCommonNone') : $sampleLegacyRef) . '</code><br>';
print dol_escape_htmltag($langs->trans('TakeposCategoryCreateDebugSafeRef')) . ': <code>' . dol_escape_htmltag($sampleSafeRef) . '</code><br>';
if ($sampleLegacyCollision) {
    print dol_escape_htmltag($langs->trans('TakeposCategoryCreateDebugLegacyCollision', (int) $sampleLegacyCollision->rowid, (string) $sampleLegacyCollision->label));
} else {
    print dol_escape_htmltag($langs->trans('TakeposCategoryCreateDebugLegacyLookup')) . ': ' . dol_escape_htmltag($sampleLegacyRef === '' ? $langs->trans('TakeposCategoryCreateDebugLegacyCollisionNone') : $langs->trans('TakeposCategoryCreateDebugLegacyCollisionMissing'));
}
print '</div>';

if (!empty($debug)) {
    print '<div class="warning" style="margin:8px 0;">';
    print '<strong>' . dol_escape_htmltag($langs->trans('TakeposCategoryCreateLastSubmitDebug')) . '</strong><br>';
    foreach ($debug as $k => $v) {
        print dol_escape_htmltag((string) $k) . ': <code>' . dol_escape_htmltag((string) $v) . '</code><br>';
    }
    print '</div>';
}

print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '">';
print '<input type="hidden" name="token" value="' . dol_escape_htmltag(newToken()) . '">';
print '<input type="hidden" name="action" value="create">';

print '<table class="border centpercent">';
print '<tr><td class="titlefield">' . dol_escape_htmltag($langs->trans('TakeposCommonLabel')) . '</td><td><input type="text" name="label" class="flat minwidth300" value="' . dol_escape_htmltag($label) . '" required></td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposCategoryCreateReferenceOptional')) . '</td><td><input type="text" name="ref" class="flat minwidth300" value="' . dol_escape_htmltag($manualRef) . '"> <span class="opacitymedium">' . dol_escape_htmltag($langs->trans('TakeposCategoryCreateReferenceHint')) . '</span></td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposCommonDescription')) . '</td><td><textarea name="description" class="flat minwidth300" rows="3">' . dol_escape_htmltag($description) . '</textarea></td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposCommonType')) . '</td><td><select name="category_type" class="flat">';
print '<option value="0"' . ((int) $type === 0 ? ' selected' : '') . '>' . dol_escape_htmltag($langs->trans('TakeposCategoryCreateTypeProducts')) . '</option>';
print '<option value="1"' . ((int) $type === 1 ? ' selected' : '') . '>' . dol_escape_htmltag($langs->trans('TakeposCategoryCreateTypeSuppliers')) . '</option>';
print '<option value="2"' . ((int) $type === 2 ? ' selected' : '') . '>' . dol_escape_htmltag($langs->trans('TakeposCategoryCreateTypeCustomers')) . '</option>';
print '</select></td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposCategoryCreateParentId')) . '</td><td><input type="number" min="0" name="parent_id" class="flat width100" value="' . ((int) $parentId) . '"> <span class="opacitymedium">' . dol_escape_htmltag($langs->trans('TakeposCategoryCreateParentRootHint')) . '</span></td></tr>';
print '</table>';

print '<div class="tabsAction" style="margin-top:10px;">';
print '<button type="submit" class="button button-save">' . dol_escape_htmltag($langs->trans('TakeposCategoryCreateSubmit')) . '</button> ';
print '<a class="button button-cancel" href="' . DOL_URL_ROOT . '/categories/index.php?type=' . ((int) $type) . '">' . dol_escape_htmltag($langs->trans('TakeposCategoryCreateBack')) . '</a>';
print '</div>';

print '</form>';

llxFooter();
$db->close();

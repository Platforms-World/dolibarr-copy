<?php

function poscoreAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load('poscore@poscore');

    $h = 0;
    $head = array();
    $head[$h][0] = DOL_URL_ROOT . '/custom/poscore/admin/settings.php';
    $head[$h][1] = $langs->trans('Settings');
    $head[$h][2] = 'settings';
    $h++;

    $head[$h][0] = DOL_URL_ROOT . '/custom/poscore/admin/terminal_list.php';
    $head[$h][1] = $langs->trans('Terminals');
    $head[$h][2] = 'terminals';
    $h++;

    $head[$h][0] = DOL_URL_ROOT . '/custom/poscore/admin/cashier_list.php';
    $head[$h][1] = $langs->trans('Cashiers');
    $head[$h][2] = 'cashiers';
    $h++;

    $head[$h][0] = DOL_URL_ROOT . '/custom/poscore/admin/shifts.php';
    $head[$h][1] = $langs->trans('Shifts');
    $head[$h][2] = 'shifts';
    $h++;

    $head[$h][0] = DOL_URL_ROOT . '/custom/poscore/admin/receipt_profiles.php';
    $head[$h][1] = $langs->trans('ReceiptProfiles');
    $head[$h][2] = 'receipt_profiles';
    $h++;

    return $head;
}

<?php
/* Copyright (C) 2026 */

/**
 * Prepare admin tabs.
 *
 * @return array<int, array<string, mixed>>
 */
function kafoerpproductimportexportAdminPrepareHead()
{
    global $langs;

    $langs->load('kafoerpproductimportexport@kafoerpproductimportexport');

    $head = array();
    $h = 0;

    $head[$h][0] = dol_buildpath('/kafoerpproductimportexport/admin/setup.php', 1);
    $head[$h][1] = $langs->trans('Settings');
    $head[$h][2] = 'settings';
    $h++;

    return $head;
}

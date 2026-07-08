<?php
/* Copyright (C) 2026 */

/**
 * Hook class for quick buttons on product pages.
 */
class ActionsKafoerpproductimportexport
{
    /** @var DoliDB */
    public $db;

    /** @var string */
    public $error = '';

    /** @var array<int, string> */
    public $errors = array();

    /** @var array<string, mixed> */
    public $results = array();

    /**
     * Constructor.
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Add buttons in product list/card top action area when context supports it.
     *
     * @param array<string, mixed> $parameters Hook parameters
     * @param object               $object Object
     * @param string               $action Action
     * @param HookManager          $hookmanager Hook manager
     * @return int
     */
    public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $user;

        if (empty($user->rights->kafoerpproductimportexport->read)) {
            return 0;
        }

        $langs->load('kafoerpproductimportexport@kafoerpproductimportexport');

        $baseUrl = dol_buildpath('/kafoerpproductimportexport/products_import.php', 1);

        print '<div class="inline-block divButAction">';
        if (!empty($user->rights->kafoerpproductimportexport->export)) {
            $templateUrl = $baseUrl . '?action=downloadtemplate';
            print '<a class="butAction" href="' . dol_escape_htmltag($templateUrl) . '">' . $langs->trans('KafoDownloadCsvTemplate') . '</a>';
        }
        if (!empty($user->rights->kafoerpproductimportexport->import)) {
            print '<a class="butAction" href="' . dol_escape_htmltag($baseUrl) . '">' . $langs->trans('KafoImportProducts') . '</a>';
        }
        print '</div>';

        return 0;
    }
}

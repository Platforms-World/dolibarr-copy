<?php
/* Copyright (C) 2026 */

require_once DOL_DOCUMENT_ROOT . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'DolibarrModules.class.php';

/**
 * Module descriptor.
 */
class modKafoerpproductimportexport extends DolibarrModules
{
    /**
     * Constructor.
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;
        $this->numero = 106420;

        $this->rights_class = 'kafoerpproductimportexport';
        $this->family = 'products';
        $this->module_position = 500;
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = 'kafo-ERP Import Export Product';
        $this->descriptionlong = 'Import and export supermarket/POS product data from CSV and ZIP packages.';
        $this->version = '1.0.0';
        $this->const_name = 'MAIN_MODULE_KAFOERPPRODUCTIMPORTEXPORT';
        $this->picto = 'barcode';

        $this->special = 0;
        $this->module_parts = array(
            'hooks' => array('productservicelist', 'productcard')
        );

        $this->dirs = array();
        $this->config_page_url = array('setup.php@kafoerpproductimportexport');
        $this->hidden = false;

        $this->depends = array('modProduct');
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->langfiles = array('kafoerpproductimportexport@kafoerpproductimportexport');

        $this->phpmin = array(7, 4);
        $this->need_dolibarr_version = array(22, 0);

        $this->const = array();

        $r = 0;
        $this->rights = array();

        $this->rights[$r][0] = 106421;
        $this->rights[$r][1] = 'Read kafo product import/export pages';
        $this->rights[$r][4] = 'read';
        $r++;

        $this->rights[$r][0] = 106422;
        $this->rights[$r][1] = 'Import products from ZIP';
        $this->rights[$r][4] = 'import';
        $r++;

        $this->rights[$r][0] = 106423;
        $this->rights[$r][1] = 'Export CSV template';
        $this->rights[$r][4] = 'export';
        $r++;

        $this->menu = array();

        $this->menu[] = array(
            'fk_menu' => 'fk_mainmenu=products',
            'type' => 'left',
            'titre' => 'KafoERPImportExportProductMenu',
            'mainmenu' => 'products',
            'leftmenu' => 'kafoerpproductimportexport',
            'url' => '/kafoerpproductimportexport/products_import.php',
            'langs' => 'kafoerpproductimportexport@kafoerpproductimportexport',
            'position' => 200,
            'enabled' => 'isModEnabled("kafoerpproductimportexport")',
            'perms' => '$user->hasRight("kafoerpproductimportexport", "read")',
            'target' => '',
            'user' => 0,
        );

        $this->menu[] = array(
            'fk_menu' => 'fk_mainmenu=home,fk_leftmenu=setup',
            'type' => 'left',
            'titre' => 'KafoERPImportExportProductSetup',
            'mainmenu' => 'home',
            'leftmenu' => 'kafoerpproductimportexportsetup',
            'url' => '/kafoerpproductimportexport/admin/setup.php',
            'langs' => 'kafoerpproductimportexport@kafoerpproductimportexport',
            'position' => 200,
            'enabled' => 'isModEnabled("kafoerpproductimportexport")',
            'perms' => '$user->admin',
            'target' => '',
            'user' => 0,
        );
    }

    /**
     * Init module.
     *
     * @param string $options Options
     * @return int
     */
    public function init($options = '')
    {
        $sql = array();
        return $this->_init($sql, $options);
    }

    /**
     * Remove module.
     *
     * @param string $options Options
     * @return int
     */
    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}

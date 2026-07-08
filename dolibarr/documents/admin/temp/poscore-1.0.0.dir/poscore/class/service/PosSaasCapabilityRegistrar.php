<?php

class PosSaasCapabilityRegistrar
{
    public $db;
    public $error = '';
    public $errors = array();

    const MODULE_CODE = 'poscore';
    const MODULE_LABEL = 'POS Management';

    public function __construct($db)
    {
        $this->db = $db;
    }

    protected function getRegistryService()
    {
        $path = DOL_DOCUMENT_ROOT . '/custom/saascore/class/service/SaasRegistryService.php';
        if (!file_exists($path)) {
            throw new Exception('saascore registry service not found at ' . $path);
        }
        require_once $path;
        return new SaasRegistryService($this->db);
    }

    public function registerAll()
    {
        try {
            $svc = $this->getRegistryService();

            $svc->registerModule(self::MODULE_CODE, array(
                'label' => self::MODULE_LABEL,
                'description' => 'Core point-of-sale management module for terminals, cashiers, shifts, POS operations control, and store linkage.'
            ));

            foreach ($this->getFeatures() as $code => $label) {
                $svc->registerFeature(self::MODULE_CODE, $code, array('label' => $label));
            }

            foreach ($this->getLimits() as $code => $label) {
                $svc->registerLimit(self::MODULE_CODE, $code, array('label' => $label));
            }

            foreach ($this->getPermissions() as $code => $label) {
                $svc->registerPermission(self::MODULE_CODE, $code, array('label' => $label));
            }

            return 1;
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            $this->errors[] = $e->getMessage();
            dol_syslog(__METHOD__ . ' failed: ' . $e->getMessage(), LOG_ERR);
            return -1;
        }
    }

    public function getFeatures()
    {
        return array(
            'multi_cashier' => 'Allow more than one cashier/user assignment in tenant POS scope',
            'multi_terminal' => 'Allow more than one POS terminal / selling point',
            'multi_warehouse' => 'Allow more than one warehouse/store linkage for POS operations',
            'shift_management' => 'Enable shift opening and shift closing workflow',
            'refund_sales' => 'Allow refund / return sales operations',
            'manual_discount' => 'Allow manual discount entry during POS operation',
            'price_override' => 'Allow changing unit price during POS transaction',
            'sell_without_stock' => 'Allow sales even if stock is insufficient',
            'reprint_receipt' => 'Allow receipt reprint functionality',
            'barcode_mode' => 'Enable barcode-based fast selling mode',
            'customer_selection' => 'Allow selecting linked customer during POS sale',
            'advanced_receipt_settings' => 'Enable advanced receipt setup, footer/header/custom fields',
            'terminal_status_control' => 'Allow terminal active/inactive operational control',
            'cashier_assignment' => 'Allow assigning specific users to specific cashiers/terminals',
            'sales_notes' => 'Allow notes on POS transaction header',
            'warehouse_restriction_rules' => 'Enable warehouse-specific POS behavior and controls',
            'quick_sale_mode' => 'Enable fast POS without extended form flow',
            'shift_cash_control' => 'Enable opening float / closing cash counting controls',
            'receipt_series_control' => 'Enable terminal-specific numbering / receipt references if needed',
            'pos_reports' => 'Enable POS operational reports pages',
        );
    }

    public function getLimits()
    {
        return array(
            'max_cashiers' => 'Maximum number of cashier definitions allowed for the tenant',
            'max_terminals' => 'Maximum number of POS terminals/selling points allowed',
            'max_warehouses' => 'Maximum number of POS-linked warehouses/stores allowed',
            'max_active_terminals' => 'Maximum number of simultaneously active terminals',
            'max_daily_transactions' => 'Maximum allowed POS transactions per day',
            'max_monthly_transactions' => 'Maximum allowed POS transactions per month',
            'max_pos_users' => 'Maximum number of users allowed to be assigned to POS operations',
            'max_receipt_templates' => 'Maximum allowed receipt configurations/templates',
        );
    }

    public function getPermissions()
    {
        return array(
            'view_pos_dashboard' => 'View POS dashboard',
            'manage_pos_settings' => 'Manage POS settings',
            'create_terminal' => 'Create terminal',
            'edit_terminal' => 'Edit terminal',
            'delete_terminal' => 'Delete terminal',
            'activate_terminal' => 'Activate terminal',
            'deactivate_terminal' => 'Deactivate terminal',
            'create_cashier' => 'Create cashier',
            'edit_cashier' => 'Edit cashier',
            'delete_cashier' => 'Delete cashier',
            'assign_cashier_user' => 'Assign cashier to user',
            'open_shift' => 'Open shift',
            'close_shift' => 'Close shift',
            'create_sale' => 'Create sale',
            'edit_draft_sale' => 'Edit draft sale',
            'validate_sale' => 'Validate sale',
            'refund_sale' => 'Refund sale',
            'apply_discount' => 'Apply discount',
            'override_price' => 'Override price',
            'reprint_receipt' => 'Reprint receipt',
            'select_customer' => 'Select customer',
            'sell_without_stock' => 'Sell without stock',
            'view_pos_reports' => 'View POS reports',
            'manage_receipt_settings' => 'Manage receipt settings',
            'assign_warehouse' => 'Assign warehouse',
            'view_shift_summary' => 'View shift summary',
            'close_other_user_shift' => 'Close other user shift',
            'cancel_sale' => 'Cancel sale',
            'void_receipt' => 'Void receipt',
            'use_barcode_mode' => 'Use barcode mode',
        );
    }
}

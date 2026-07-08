<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_request.php';

takeposApiRequireMethod(array('GET'));

$auth = takeposApiAuth($db, 'read', '');

takeposApiSuccess(array(
    'features' => array(
        // Action Buttons
        array('key' => 'ui.action.new',              'label' => 'New Sale',            'group' => 'Action Buttons', 'shortcut' => 'F1'),
        array('key' => 'ui.action.payment',          'label' => 'Payment',             'group' => 'Action Buttons', 'shortcut' => 'F2'),
        array('key' => 'ui.action.cash',             'label' => 'Direct Cash',         'group' => 'Action Buttons', 'shortcut' => 'F3'),
        array('key' => 'ui.action.visa',             'label' => 'Card Payment',        'group' => 'Action Buttons', 'shortcut' => 'F4'),
        array('key' => 'ui.action.open_drawer',      'label' => 'Open Drawer',         'group' => 'Action Buttons', 'shortcut' => 'F5'),
        array('key' => 'ui.action.reports',          'label' => 'Reports',             'group' => 'Action Buttons', 'shortcut' => 'F7'),
        array('key' => 'ui.action.hold',             'label' => 'Hold Sale',           'group' => 'Action Buttons', 'shortcut' => 'F8'),
        array('key' => 'ui.action.held',             'label' => 'Held Sales',          'group' => 'Action Buttons', 'shortcut' => 'F9'),
        array('key' => 'ui.action.history',          'label' => 'History',             'group' => 'Action Buttons', 'shortcut' => 'F10'),
        array('key' => 'ui.action.shift_desk',       'label' => 'Shift Desk',          'group' => 'Action Buttons', 'shortcut' => 'F11'),
        array('key' => 'ui.action.paid_in',          'label' => 'Paid In',             'group' => 'Action Buttons', 'shortcut' => 'F12'),
        array('key' => 'ui.action.paid_out',         'label' => 'Paid Out',            'group' => 'Action Buttons', 'shortcut' => null),
        array('key' => 'ui.action.exchange',         'label' => 'Exchange Desk',       'group' => 'Action Buttons', 'shortcut' => null),
        array('key' => 'ui.action.freezone',         'label' => 'Free-text Product',   'group' => 'Action Buttons', 'shortcut' => null),
        array('key' => 'ui.action.discount',         'label' => 'Invoice Discount',    'group' => 'Action Buttons', 'shortcut' => null),
        array('key' => 'ui.action.split',            'label' => 'Split Sale',          'group' => 'Action Buttons', 'shortcut' => null),
        array('key' => 'ui.action.calculator',       'label' => 'Calculator',          'group' => 'Action Buttons', 'shortcut' => null),
        array('key' => 'ui.action.product_info',     'label' => 'Product Info',        'group' => 'Action Buttons', 'shortcut' => null),
        array('key' => 'ui.action.print_ticket',     'label' => 'Print Ticket',        'group' => 'Action Buttons', 'shortcut' => null),
        // Catalog & Inventory
        array('key' => 'ui.shortcut.add_product',          'label' => 'Add Product',          'group' => 'Catalog & Inventory', 'shortcut' => null),
        array('key' => 'ui.shortcut.manage_products',      'label' => 'Manage Products',      'group' => 'Catalog & Inventory', 'shortcut' => null),
        array('key' => 'ui.shortcut.barcodes',             'label' => 'Product Barcodes',     'group' => 'Catalog & Inventory', 'shortcut' => null),
        array('key' => 'ui.shortcut.tax_rates',            'label' => 'Tax Rates',            'group' => 'Catalog & Inventory', 'shortcut' => null),
        array('key' => 'ui.shortcut.stock_overview',       'label' => 'Stock Overview',       'group' => 'Catalog & Inventory', 'shortcut' => null),
        array('key' => 'ui.shortcut.stock_adjustments',    'label' => 'Stock Adjustments',    'group' => 'Catalog & Inventory', 'shortcut' => null),
        array('key' => 'ui.shortcut.all_branches_stock',   'label' => 'All Branches Stock',   'group' => 'Catalog & Inventory', 'shortcut' => null),
        array('key' => 'ui.shortcut.stock_transfer',       'label' => 'Stock Transfer',       'group' => 'Catalog & Inventory', 'shortcut' => null),
        array('key' => 'ui.shortcut.stock_reconciliation', 'label' => 'Stock Reconciliation', 'group' => 'Catalog & Inventory', 'shortcut' => null),
        array('key' => 'ui.shortcut.inventory_count',      'label' => 'Inventory Count',      'group' => 'Catalog & Inventory', 'shortcut' => null),
        array('key' => 'ui.shortcut.piece_box',            'label' => 'Piece / Box Variants', 'group' => 'Catalog & Inventory', 'shortcut' => null),
        array('key' => 'ui.shortcut.cheques',              'label' => 'Cheques',              'group' => 'Catalog & Inventory', 'shortcut' => null),
        // Sales Operations
        array('key' => 'ui.shortcut.shift_desk',     'label' => 'Shift Desk',     'group' => 'Sales Operations', 'shortcut' => null),
        array('key' => 'ui.shortcut.refund_desk',    'label' => 'Refund Desk',    'group' => 'Sales Operations', 'shortcut' => null),
        array('key' => 'ui.shortcut.exchange_desk',  'label' => 'Exchange Desk',  'group' => 'Sales Operations', 'shortcut' => null),
        array('key' => 'ui.shortcut.loyalty_desk',   'label' => 'Loyalty Desk',   'group' => 'Sales Operations', 'shortcut' => null),
        array('key' => 'ui.shortcut.expenses',       'label' => 'Expenses',       'group' => 'Sales Operations', 'shortcut' => null),
        array('key' => 'ui.shortcut.expense_ledger', 'label' => 'Expense Ledger', 'group' => 'Sales Operations', 'shortcut' => null),
        array('key' => 'ui.shortcut.purchases',      'label' => 'Purchases',      'group' => 'Sales Operations', 'shortcut' => null),
        // Analytics
        array('key' => 'ui.shortcut.kpi_dashboard', 'label' => 'KPI Dashboard', 'group' => 'Analytics', 'shortcut' => null),
    ),
), array('entity' => $auth['entity']));

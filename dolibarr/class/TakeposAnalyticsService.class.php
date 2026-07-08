<?php
require_once __DIR__ . '/TakeposAudit.class.php';
require_once __DIR__ . '/TakeposMigration.class.php';
require_once __DIR__ . '/TakeposStoreService.class.php';
require_once __DIR__ . '/TakeposUserAccess.class.php';
require_once __DIR__ . '/TakeposRefundService.class.php';

/**
 * Advanced KPI / analytics service.
 */
class TakeposAnalyticsService
{
    private static function fetchRows($db, $sql)
    {
        $rows = array();
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $rows[] = (array) $obj;
            }
        }
        return $rows;
    }

    private static function isIsoDate($value)
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value);
    }

    private static function allowedStoreIds($db, $user, $entity)
    {
        if (!TakeposStoreService::enforceStoreRestrictionEnabled($db)) {
            return null;
        }
        if (!empty($user->admin) || TakeposUserAccess::userHasPermission($db, $user, 'takepos.store.view_all')) {
            return null;
        }
        return TakeposStoreService::getUserStoreIds($db, $entity, (int) $user->id);
    }

    private static function buildInvoiceWhere($db, $entity, $filters = array(), $invoiceAlias = 'f')
    {
        $where = array();
        $where[] = $invoiceAlias . '.entity = ' . ((int) $entity);
        $where[] = $invoiceAlias . ".module_source = 'takepos'";

        if (!empty($filters['date_from']) && self::isIsoDate($filters['date_from'])) {
            $where[] = 'COALESCE(' . $invoiceAlias . ".datef, " . $invoiceAlias . ".datec) >= '" . $db->escape($filters['date_from']) . " 00:00:00'";
        }
        if (!empty($filters['date_to']) && self::isIsoDate($filters['date_to'])) {
            $where[] = 'COALESCE(' . $invoiceAlias . ".datef, " . $invoiceAlias . ".datec) <= '" . $db->escape($filters['date_to']) . " 23:59:59'";
        }
        if (!empty($filters['cashier_id'])) {
            $where[] = $invoiceAlias . '.fk_user_author = ' . ((int) $filters['cashier_id']);
        }
        if (!empty($filters['terminal_code'])) {
            $where[] = $invoiceAlias . ".pos_source = '" . $db->escape((string) $filters['terminal_code']) . "'";
        }
        if (!empty($filters['store_id'])) {
            $where[] = "EXISTS (SELECT 1 FROM " . MAIN_DB_PREFIX . "takepos_terminal t WHERE t.entity = " . $invoiceAlias . ".entity AND t.terminal_code = " . $invoiceAlias . ".pos_source AND t.fk_store = " . ((int) $filters['store_id']) . " AND t.active = 1)";
        }
        if (!empty($filters['payment_method'])) {
            $where[] = "EXISTS (
                SELECT 1
                FROM " . MAIN_DB_PREFIX . "paiement_facture pf2
                INNER JOIN " . MAIN_DB_PREFIX . "paiement p2 ON p2.rowid = pf2.fk_paiement
                INNER JOIN " . MAIN_DB_PREFIX . "c_paiement cp2 ON cp2.id = p2.fk_paiement
                WHERE pf2.fk_facture = " . $invoiceAlias . ".rowid AND cp2.code = '" . $db->escape((string) $filters['payment_method']) . "'
            )";
        }

        if (array_key_exists('allowed_store_ids', $filters) && is_array($filters['allowed_store_ids'])) {
            $ids = array();
            foreach ($filters['allowed_store_ids'] as $sid) {
                $sid = (int) $sid;
                if ($sid > 0) {
                    $ids[] = $sid;
                }
            }
            if (empty($ids)) {
                $where[] = '1 = 0';
            } else {
                $where[] = "EXISTS (SELECT 1 FROM " . MAIN_DB_PREFIX . "takepos_terminal tx WHERE tx.entity = " . $invoiceAlias . ".entity AND tx.terminal_code = " . $invoiceAlias . ".pos_source AND tx.fk_store IN (" . implode(',', $ids) . ") AND tx.active = 1)";
            }
        }

        return implode(' AND ', $where);
    }

    public static function collect($db, $user, $filters = array())
    {
        $entity = !empty($user->entity) ? (int) $user->entity : 1;

        $allowedStoreIds = self::allowedStoreIds($db, $user, $entity);
        if (is_array($allowedStoreIds)) {
            if (!empty($filters['store_id']) && !in_array((int) $filters['store_id'], $allowedStoreIds, true)) {
                throw new Exception('Store access denied for analytics filter.');
            }
            $filters['allowed_store_ids'] = $allowedStoreIds;
        }

        $where = self::buildInvoiceWhere($db, $entity, $filters, 'f');

        $summarySql = "SELECT COUNT(DISTINCT f.rowid) AS tickets_count, COALESCE(SUM(f.total_ttc), 0) AS gross_sales FROM " . MAIN_DB_PREFIX . "facture f WHERE " . $where;
        $summaryRows = self::fetchRows($db, $summarySql);
        $summary = !empty($summaryRows[0]) ? $summaryRows[0] : array('tickets_count' => 0, 'gross_sales' => 0);

        $refundWhere = "r.entity = " . $entity . " AND r.status = 'completed'";
        if (!empty($filters['date_from']) && self::isIsoDate($filters['date_from'])) {
            $refundWhere .= " AND r.date_creation >= '" . $db->escape($filters['date_from']) . " 00:00:00'";
        }
        if (!empty($filters['date_to']) && self::isIsoDate($filters['date_to'])) {
            $refundWhere .= " AND r.date_creation <= '" . $db->escape($filters['date_to']) . " 23:59:59'";
        }
        if (!empty($filters['store_id'])) {
            $refundWhere .= " AND r.fk_store = " . ((int) $filters['store_id']);
        }
        if (is_array($allowedStoreIds)) {
            if (!empty($allowedStoreIds)) {
                $refundWhere .= " AND r.fk_store IN (" . implode(',', array_map('intval', $allowedStoreIds)) . ")";
            } else {
                $refundWhere .= " AND 1 = 0";
            }
        }

        $refundSql = "SELECT COUNT(*) AS refund_count, COALESCE(SUM(r.total_amount), 0) AS refund_amount FROM " . TakeposRefundService::tableRefund() . " r WHERE " . $refundWhere;
        $refundRow = self::fetchRows($db, $refundSql);
        $refundSummary = !empty($refundRow[0]) ? $refundRow[0] : array('refund_count' => 0, 'refund_amount' => 0);

        $grossSales = (float) $summary['gross_sales'];
        $ticketsCount = (int) $summary['tickets_count'];
        $refundAmount = (float) $refundSummary['refund_amount'];
        $netSales = $grossSales - $refundAmount;
        $avgBasket = ($ticketsCount > 0 ? ($grossSales / $ticketsCount) : 0);

        $topCashier = self::fetchRows($db, "SELECT COALESCE(NULLIF(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.lastname,'')), ' '), u.login, CONCAT('User#', f.fk_user_author)) AS cashier_name, COALESCE(SUM(f.total_ttc),0) AS amount FROM " . MAIN_DB_PREFIX . "facture f LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = f.fk_user_author WHERE " . $where . " GROUP BY f.fk_user_author, cashier_name ORDER BY amount DESC LIMIT 1");

        $topStore = self::fetchRows($db, "SELECT COALESCE(st.label,'(No Store)') AS store_name, COALESCE(SUM(f.total_ttc),0) AS amount FROM " . MAIN_DB_PREFIX . "facture f LEFT JOIN " . MAIN_DB_PREFIX . "takepos_terminal tt ON tt.entity = f.entity AND tt.terminal_code = f.pos_source LEFT JOIN " . MAIN_DB_PREFIX . "takepos_store st ON st.rowid = tt.fk_store AND st.entity = f.entity WHERE " . $where . " GROUP BY store_name ORDER BY amount DESC LIMIT 1");

        $salesByHour = self::fetchRows($db, "SELECT DATE_FORMAT(COALESCE(f.datef, f.datec), '%H:00') AS hour_slot, COUNT(*) AS tickets, COALESCE(SUM(f.total_ttc),0) AS amount FROM " . MAIN_DB_PREFIX . "facture f WHERE " . $where . " GROUP BY hour_slot ORDER BY hour_slot ASC");

        $ticketsByCashier = self::fetchRows($db, "SELECT COALESCE(NULLIF(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.lastname,'')), ' '), u.login, CONCAT('User#', f.fk_user_author)) AS cashier_name, COUNT(*) AS tickets, COALESCE(SUM(f.total_ttc),0) AS amount FROM " . MAIN_DB_PREFIX . "facture f LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = f.fk_user_author WHERE " . $where . " GROUP BY f.fk_user_author, cashier_name ORDER BY tickets DESC");

        $paymentMix = self::fetchRows($db, "SELECT cp.code AS payment_code, cp.libelle AS payment_label, COALESCE(SUM(pf.amount),0) AS amount FROM " . MAIN_DB_PREFIX . "paiement_facture pf INNER JOIN " . MAIN_DB_PREFIX . "paiement p ON p.rowid = pf.fk_paiement INNER JOIN " . MAIN_DB_PREFIX . "c_paiement cp ON cp.id = p.fk_paiement INNER JOIN " . MAIN_DB_PREFIX . "facture f ON f.rowid = pf.fk_facture WHERE " . $where . " GROUP BY cp.code, cp.libelle ORDER BY amount DESC");

        $topProducts = self::fetchRows($db, "SELECT COALESCE(p.ref,'') AS product_ref, COALESCE(NULLIF(p.label,''), fd.label, fd.description, CONCAT('Product#', fd.fk_product)) AS product_label, COALESCE(SUM(fd.qty),0) AS qty, COALESCE(SUM(fd.total_ttc),0) AS amount FROM " . MAIN_DB_PREFIX . "facture f INNER JOIN " . MAIN_DB_PREFIX . "facturedet fd ON fd.fk_facture = f.rowid LEFT JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = fd.fk_product WHERE " . $where . " GROUP BY fd.fk_product, product_ref, product_label ORDER BY amount DESC LIMIT 10");

        $slowProducts = self::fetchRows($db, "SELECT COALESCE(p.ref,'') AS product_ref, COALESCE(NULLIF(p.label,''), fd.label, fd.description, CONCAT('Product#', fd.fk_product)) AS product_label, COALESCE(SUM(fd.qty),0) AS qty, COALESCE(SUM(fd.total_ttc),0) AS amount FROM " . MAIN_DB_PREFIX . "facture f INNER JOIN " . MAIN_DB_PREFIX . "facturedet fd ON fd.fk_facture = f.rowid LEFT JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = fd.fk_product WHERE " . $where . " GROUP BY fd.fk_product, product_ref, product_label HAVING qty > 0 ORDER BY qty ASC LIMIT 10");

        $discountImpact = self::fetchRows($db, "SELECT COALESCE(SUM((fd.subprice * fd.qty) * (fd.remise_percent / 100)), 0) AS discount_amount FROM " . MAIN_DB_PREFIX . "facture f INNER JOIN " . MAIN_DB_PREFIX . "facturedet fd ON fd.fk_facture = f.rowid WHERE " . $where);

        $terminalPerformance = self::fetchRows($db, "SELECT COALESCE(f.pos_source,'(N/A)') AS terminal_code, COUNT(*) AS tickets, COALESCE(SUM(f.total_ttc),0) AS amount FROM " . MAIN_DB_PREFIX . "facture f WHERE " . $where . " GROUP BY terminal_code ORDER BY amount DESC");

        $discrepancyWhere = "s.entity = " . $entity;
        if (!empty($filters['date_from']) && self::isIsoDate($filters['date_from'])) {
            $discrepancyWhere .= " AND s.date_open >= '" . $db->escape($filters['date_from']) . " 00:00:00'";
        }
        if (!empty($filters['date_to']) && self::isIsoDate($filters['date_to'])) {
            $discrepancyWhere .= " AND s.date_open <= '" . $db->escape($filters['date_to']) . " 23:59:59'";
        }
        if (!empty($filters['store_id'])) {
            $discrepancyWhere .= " AND s.fk_store = " . ((int) $filters['store_id']);
        }
        if (is_array($allowedStoreIds)) {
            if (!empty($allowedStoreIds)) {
                $discrepancyWhere .= " AND s.fk_store IN (" . implode(',', array_map('intval', $allowedStoreIds)) . ")";
            } else {
                $discrepancyWhere .= " AND 1 = 0";
            }
        }

        $discrepancySummary = self::fetchRows($db, "SELECT COUNT(*) AS discrepancy_count, COALESCE(SUM(ABS(s.cash_difference)), 0) AS discrepancy_total FROM " . MAIN_DB_PREFIX . "takepos_shift s WHERE " . $discrepancyWhere . " AND ABS(COALESCE(s.cash_difference,0)) > 0.00001");
        $shiftReconciliation = self::fetchRows($db, "SELECT s.rowid, s.shift_ref, s.status, s.date_open, s.date_close, s.expected_cash, s.counted_cash, s.cash_difference FROM " . MAIN_DB_PREFIX . "takepos_shift s WHERE " . $discrepancyWhere . " ORDER BY s.rowid DESC LIMIT 50");

        $shiftPerformance = self::fetchRows($db, "SELECT s.fk_cashier_user, u.login AS cashier_login, COUNT(*) AS shifts_count, COALESCE(SUM(s.total_cash_sales + s.total_card_sales + s.total_other_sales),0) AS sales_amount FROM " . MAIN_DB_PREFIX . "takepos_shift s LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = s.fk_cashier_user WHERE " . $discrepancyWhere . " GROUP BY s.fk_cashier_user, u.login ORDER BY sales_amount DESC");

        $storeComparison = self::fetchRows($db, "SELECT COALESCE(st.label,'(No Store)') AS store_name, COUNT(DISTINCT f.rowid) AS tickets, COALESCE(SUM(f.total_ttc),0) AS amount FROM " . MAIN_DB_PREFIX . "facture f LEFT JOIN " . MAIN_DB_PREFIX . "takepos_terminal tt ON tt.entity = f.entity AND tt.terminal_code = f.pos_source LEFT JOIN " . MAIN_DB_PREFIX . "takepos_store st ON st.rowid = tt.fk_store AND st.entity = f.entity WHERE " . $where . " GROUP BY store_name ORDER BY amount DESC");

        $voidCount = 0;
        if (TakeposMigration::tableExists($db, MAIN_DB_PREFIX . 'takepos_audit')) {
            $voidRow = self::fetchRows($db, "SELECT COUNT(*) AS nb FROM " . MAIN_DB_PREFIX . "takepos_audit a WHERE a.entity = " . $entity . " AND a.event_code = 'cancel_invoice_success'");
            if (!empty($voidRow[0]['nb'])) {
                $voidCount = (int) $voidRow[0]['nb'];
            }
        }

        return array(
            'cards' => array(
                'gross_sales' => $grossSales,
                'net_sales' => $netSales,
                'refund_amount' => $refundAmount,
                'refund_count' => (int) $refundSummary['refund_count'],
                'avg_basket' => $avgBasket,
                'tickets_count' => $ticketsCount,
                'top_cashier' => (!empty($topCashier[0]) ? $topCashier[0]['cashier_name'] : ''),
                'top_store' => (!empty($topStore[0]) ? $topStore[0]['store_name'] : ''),
                'discrepancy_count' => (!empty($discrepancySummary[0]['discrepancy_count']) ? (int) $discrepancySummary[0]['discrepancy_count'] : 0),
                'void_count' => $voidCount,
            ),
            'sales_by_hour' => $salesByHour,
            'tickets_per_cashier' => $ticketsByCashier,
            'refund_summary' => self::fetchRows($db, "SELECT r.reason_code, COUNT(*) AS refund_count, COALESCE(SUM(r.total_amount),0) AS refund_amount FROM " . TakeposRefundService::tableRefund() . " r WHERE " . $refundWhere . " GROUP BY r.reason_code ORDER BY refund_amount DESC"),
            'payment_mix' => $paymentMix,
            'top_products' => $topProducts,
            'slow_products' => $slowProducts,
            'cash_discrepancy_summary' => $discrepancySummary,
            'shift_reconciliation_summary' => $shiftReconciliation,
            'shift_performance' => $shiftPerformance,
            'store_comparison' => $storeComparison,
            'terminal_performance' => $terminalPerformance,
            'discount_impact' => (!empty($discountImpact[0]) ? $discountImpact[0] : array('discount_amount' => 0)),
        );
    }

    public static function filterLookups($db, $user)
    {
        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $allowedStoreIds = self::allowedStoreIds($db, $user, $entity);
        $storeWhere = 's.entity = ' . $entity . ' AND s.active = 1';
        if (is_array($allowedStoreIds)) {
            if (!empty($allowedStoreIds)) {
                $storeWhere .= ' AND s.rowid IN (' . implode(',', array_map('intval', $allowedStoreIds)) . ')';
            } else {
                $storeWhere .= ' AND 1 = 0';
            }
        }

        return array(
            'cashiers' => self::fetchRows($db, "SELECT u.rowid, u.login, u.firstname, u.lastname FROM " . MAIN_DB_PREFIX . "user u WHERE u.entity IN (" . getEntity('user') . ") AND u.statut = 1 ORDER BY u.login ASC"),
            'stores' => self::fetchRows($db, "SELECT s.rowid, s.code, s.label FROM " . MAIN_DB_PREFIX . "takepos_store s WHERE " . $storeWhere . " ORDER BY s.code ASC"),
            'terminals' => self::fetchRows($db, "SELECT t.rowid, t.terminal_code, t.label FROM " . MAIN_DB_PREFIX . "takepos_terminal t WHERE t.entity = " . $entity . " AND t.active = 1 ORDER BY t.terminal_code ASC"),
            'payment_methods' => self::fetchRows($db, "SELECT cp.code, cp.libelle FROM " . MAIN_DB_PREFIX . "c_paiement cp WHERE cp.entity IN (" . getEntity('c_paiement') . ") AND cp.active = 1 ORDER BY cp.libelle ASC"),
        );
    }
}


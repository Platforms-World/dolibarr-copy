<?php
/**
 * Dashboard analytics helper for TakePOS.
 */
class TakeposDashboardService
{
    /** @var DoliDB */
    private $db;
    /** @var Translate */
    private $langs;
    /** @var int */
    private $entity;

    public function __construct($db, $langs = null, $entity = 1)
    {
        $this->db = $db;
        $this->langs = $langs;
        $this->entity = (int) $entity;
    }

    public function getDataset($filters = array())
    {
        $from = $this->normalizeDate(isset($filters['date_from']) ? $filters['date_from'] : date('Y-m-01'));
        $to = $this->normalizeDate(isset($filters['date_to']) ? $filters['date_to'] : date('Y-m-d'));
        if ($from > $to) {
            $tmp = $from;
            $from = $to;
            $to = $tmp;
        }

        return array(
            'meta' => array(
                'date_from' => $from,
                'date_to' => $to,
                'generated_at' => date('Y-m-d H:i:s'),
            ),
            'kpis' => $this->getKpis($from, $to),
            'sales_trend' => $this->getSalesTrend($from, $to),
            'top_products' => $this->getTopProducts($from, $to),
            'supplier_summary' => $this->getSupplierSummary($from, $to),
            'inventory_alerts' => $this->getInventoryAlerts(),
            'cheque_summary' => $this->getChequeSummary(),
            'decision_insights' => $this->getDecisionInsights($from, $to),
        );
    }

    private function normalizeDate($value)
    {
        $value = preg_replace('/[^0-9\-]/', '', (string) $value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return date('Y-m-d');
        }
        return $value;
    }

    private function tableExists($table)
    {
        $sql = 'SELECT 1 FROM ' . $table . ' WHERE 1 = 0';
        $resql = $this->db->query($sql);
        if ($resql) {
            $this->db->free($resql);
            return true;
        }
        return false;
    }

    private function fetchScalar($sql, $default = 0)
    {
        $resql = $this->db->query($sql);
        if (!$resql) {
            return $default;
        }
        $obj = $this->db->fetch_object($resql);
        $this->db->free($resql);
        if (!$obj) {
            return $default;
        }
        $values = get_object_vars($obj);
        $value = reset($values);
        return ($value === null || $value === false || $value === '') ? $default : $value;
    }

    private function getKpis($from, $to)
    {
        $out = array(
            'sales_total' => 0,
            'invoice_count' => 0,
            'avg_invoice' => 0,
            'distinct_customers' => 0,
            'cheques_pending' => 0,
            'cheques_overdue' => 0,
            'inventory_low' => 0,
        );

        if ($this->tableExists('llx_facture')) {
            $where = " f.entity = {$this->entity} AND f.datef >= '" . $this->db->escape($from) . "' AND f.datef <= '" . $this->db->escape($to) . " 23:59:59' AND f.fk_statut IN (1,2) AND (f.type IS NULL OR f.type IN (0,1)) ";
            $out['sales_total'] = (float) $this->fetchScalar("SELECT COALESCE(SUM(f.total_ttc),0) AS amount FROM llx_facture f WHERE {$where}", 0);
            $out['invoice_count'] = (int) $this->fetchScalar("SELECT COUNT(*) AS nb FROM llx_facture f WHERE {$where}", 0);
            $out['distinct_customers'] = (int) $this->fetchScalar("SELECT COUNT(DISTINCT f.fk_soc) AS nb FROM llx_facture f WHERE {$where} AND f.fk_soc IS NOT NULL", 0);
            $out['avg_invoice'] = $out['invoice_count'] > 0 ? round($out['sales_total'] / $out['invoice_count'], 2) : 0;
        }

        if ($this->tableExists('llx_takepos_cheque')) {
            $out['cheques_pending'] = (int) $this->fetchScalar("SELECT COUNT(*) AS nb FROM llx_takepos_cheque c WHERE c.entity = {$this->entity} AND c.status = 'pending'", 0);
            $out['cheques_overdue'] = (int) $this->fetchScalar("SELECT COUNT(*) AS nb FROM llx_takepos_cheque c WHERE c.entity = {$this->entity} AND c.status = 'pending' AND c.collection_date < '" . $this->db->escape(date('Y-m-d')) . "'", 0);
        }

        if ($this->tableExists('llx_product') && $this->tableExists('llx_product_stock')) {
            $sql = "SELECT COUNT(*) AS nb
                    FROM llx_product p
                    INNER JOIN llx_product_stock ps ON ps.fk_product = p.rowid
                    WHERE p.entity IN (0, {$this->entity})
                      AND COALESCE(ps.reel,0) <= CASE WHEN COALESCE(p.seuil_stock_alerte,0) > 0 THEN p.seuil_stock_alerte ELSE 5 END";
            $out['inventory_low'] = (int) $this->fetchScalar($sql, 0);
        }

        return $out;
    }

    private function getSalesTrend($from, $to)
    {
        $labels = array();
        $values = array();
        $rows = array();
        $cursor = strtotime($from);
        $end = strtotime($to);
        while ($cursor <= $end) {
            $day = date('Y-m-d', $cursor);
            $labels[] = $day;
            $values[$day] = 0;
            $cursor = strtotime('+1 day', $cursor);
        }

        if ($this->tableExists('llx_facture')) {
            $sql = "SELECT DATE(f.datef) AS d, COALESCE(SUM(f.total_ttc),0) AS amount
                    FROM llx_facture f
                    WHERE f.entity = {$this->entity}
                      AND f.datef >= '" . $this->db->escape($from) . "'
                      AND f.datef <= '" . $this->db->escape($to) . " 23:59:59'
                      AND f.fk_statut IN (1,2)
                      AND (f.type IS NULL OR f.type IN (0,1))
                    GROUP BY DATE(f.datef)
                    ORDER BY DATE(f.datef) ASC";
            $resql = $this->db->query($sql);
            if ($resql) {
                while ($obj = $this->db->fetch_object($resql)) {
                    $day = (string) $obj->d;
                    if (array_key_exists($day, $values)) {
                        $values[$day] = round((float) $obj->amount, 2);
                    }
                }
                $this->db->free($resql);
            }
        }

        foreach ($labels as $day) {
            $rows[] = array('label' => $day, 'value' => (float) $values[$day]);
        }

        return $rows;
    }

    private function getTopProducts($from, $to)
    {
        $rows = array();
        if (!$this->tableExists('llx_facturedet') || !$this->tableExists('llx_facture')) {
            return $rows;
        }

        $sql = "SELECT
                    COALESCE(p.ref, fd.desc, CONCAT('Product #', fd.fk_product)) AS product_ref,
                    COALESCE(p.label, fd.desc, CONCAT('Product #', fd.fk_product)) AS product_label,
                    SUM(COALESCE(fd.qty,0)) AS qty,
                    SUM(COALESCE(fd.total_ht,0)) AS amount
                FROM llx_facturedet fd
                INNER JOIN llx_facture f ON f.rowid = fd.fk_facture
                LEFT JOIN llx_product p ON p.rowid = fd.fk_product
                WHERE f.entity = {$this->entity}
                  AND f.datef >= '" . $this->db->escape($from) . "'
                  AND f.datef <= '" . $this->db->escape($to) . " 23:59:59'
                  AND f.fk_statut IN (1,2)
                GROUP BY COALESCE(p.ref, fd.desc, CONCAT('Product #', fd.fk_product)), COALESCE(p.label, fd.desc, CONCAT('Product #', fd.fk_product))
                ORDER BY qty DESC, amount DESC
                LIMIT 10";
        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $rows[] = array(
                    'ref' => (string) $obj->product_ref,
                    'label' => (string) $obj->product_label,
                    'qty' => (float) $obj->qty,
                    'amount' => round((float) $obj->amount, 2),
                );
            }
            $this->db->free($resql);
        }
        return $rows;
    }

    private function getSupplierSummary($from, $to)
    {
        $rows = array();
        if ($this->tableExists('llx_takepos_cheque') && $this->tableExists('llx_societe')) {
            $sql = "SELECT s.nom AS supplier, COUNT(*) AS cheque_count, COALESCE(SUM(c.amount),0) AS amount
                    FROM llx_takepos_cheque c
                    LEFT JOIN llx_societe s ON s.rowid = c.fk_supplier
                    WHERE c.entity = {$this->entity}
                      AND c.cheque_date >= '" . $this->db->escape($from) . "'
                      AND c.cheque_date <= '" . $this->db->escape($to) . "'
                    GROUP BY s.nom
                    ORDER BY amount DESC
                    LIMIT 10";
            $resql = $this->db->query($sql);
            if ($resql) {
                while ($obj = $this->db->fetch_object($resql)) {
                    $rows[] = array(
                        'supplier' => (string) ($obj->supplier ?: '—'),
                        'cheque_count' => (int) $obj->cheque_count,
                        'amount' => round((float) $obj->amount, 2),
                    );
                }
                $this->db->free($resql);
            }
        }
        return $rows;
    }

    private function getInventoryAlerts()
    {
        $rows = array();
        if (!$this->tableExists('llx_product') || !$this->tableExists('llx_product_stock')) {
            return $rows;
        }

        // FIX (stock-branch-v5): Added p.rowid AS product_id so the dashboard JS
        // can build a direct link to purchases.php pre-filled for this product.
        // Also raised LIMIT from 10 to 20 to show more actionable alerts.
        $sql = "SELECT p.rowid AS product_id, p.ref, p.label, COALESCE(ps.reel,0) AS stock, COALESCE(p.seuil_stock_alerte,0) AS threshold
                FROM llx_product p
                INNER JOIN llx_product_stock ps ON ps.fk_product = p.rowid
                WHERE p.entity IN (0, {$this->entity})
                  AND COALESCE(ps.reel,0) <= CASE WHEN COALESCE(p.seuil_stock_alerte,0) > 0 THEN p.seuil_stock_alerte ELSE 5 END
                ORDER BY stock ASC, p.label ASC
                LIMIT 20";
        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $rows[] = array(
                    'product_id' => (int) $obj->product_id,  // FIX (stock-branch-v5)
                    'ref' => (string) $obj->ref,
                    'label' => (string) $obj->label,
                    'stock' => round((float) $obj->stock, 2),
                    'threshold' => round((float) ($obj->threshold > 0 ? $obj->threshold : 5), 2),
                );
            }
            $this->db->free($resql);
        }
        return $rows;
    }

    private function getChequeSummary()
    {
        $out = array(
            'due_today' => 0,
            'due_7_days' => 0,
            'overdue' => 0,
            'bounced' => 0,
            'pending_amount' => 0,
            'upcoming' => array(),
        );
        if (!$this->tableExists('llx_takepos_cheque')) {
            return $out;
        }

        $today = date('Y-m-d');
        $in7 = date('Y-m-d', strtotime('+7 days'));
        $out['due_today'] = (int) $this->fetchScalar("SELECT COUNT(*) AS nb FROM llx_takepos_cheque c WHERE c.entity = {$this->entity} AND c.status = 'pending' AND c.collection_date = '" . $this->db->escape($today) . "'", 0);
        $out['due_7_days'] = (int) $this->fetchScalar("SELECT COUNT(*) AS nb FROM llx_takepos_cheque c WHERE c.entity = {$this->entity} AND c.status = 'pending' AND c.collection_date >= '" . $this->db->escape($today) . "' AND c.collection_date <= '" . $this->db->escape($in7) . "'", 0);
        $out['overdue'] = (int) $this->fetchScalar("SELECT COUNT(*) AS nb FROM llx_takepos_cheque c WHERE c.entity = {$this->entity} AND c.status = 'pending' AND c.collection_date < '" . $this->db->escape($today) . "'", 0);
        $out['bounced'] = (int) $this->fetchScalar("SELECT COUNT(*) AS nb FROM llx_takepos_cheque c WHERE c.entity = {$this->entity} AND c.status = 'bounced'", 0);
        $out['pending_amount'] = (float) $this->fetchScalar("SELECT COALESCE(SUM(c.amount),0) AS amount FROM llx_takepos_cheque c WHERE c.entity = {$this->entity} AND c.status = 'pending'", 0);

        $sql = "SELECT c.ref, c.amount, c.collection_date AS due_date, c.status, s.nom AS supplier
                FROM llx_takepos_cheque c
                LEFT JOIN llx_societe s ON s.rowid = c.fk_supplier
                WHERE c.entity = {$this->entity}
                  AND c.status IN ('pending','bounced')
                ORDER BY CASE WHEN c.status='bounced' THEN 0 ELSE 1 END, c.collection_date ASC
                LIMIT 10";
        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $out['upcoming'][] = array(
                    'ref' => (string) $obj->ref,
                    'supplier' => (string) ($obj->supplier ?: '—'),
                    'due_date' => (string) $obj->due_date,
                    'status' => (string) $obj->status,
                    'amount' => round((float) $obj->amount, 2),
                );
            }
            $this->db->free($resql);
        }

        return $out;
    }

    private function getDecisionInsights($from, $to)
    {
        $kpis = $this->getKpis($from, $to);
        $cheques = $this->getChequeSummary();
        $inventory = $this->getInventoryAlerts();
        $trend = $this->getSalesTrend($from, $to);
        $insights = array();

        if ($cheques['overdue'] > 0) {
            $insights[] = array('severity' => 'high', 'title' => 'Cheque follow-up', 'text' => sprintf('There are %d overdue cheques requiring immediate follow-up.', $cheques['overdue']));
        }
        if ($kpis['inventory_low'] > 0) {
            $insights[] = array('severity' => 'medium', 'title' => 'Low stock risk', 'text' => sprintf('%d items are at or below reorder level.', $kpis['inventory_low']));
        }
        if ($kpis['invoice_count'] > 0 && $kpis['avg_invoice'] < 10) {
            $insights[] = array('severity' => 'medium', 'title' => 'Average basket', 'text' => 'Average invoice value is low for the selected period. Consider bundles or upsell prompts.');
        }

        if (count($trend) >= 2) {
            $first = (float) $trend[0]['value'];
            $last = (float) $trend[count($trend) - 1]['value'];
            if ($first > 0 && $last < $first * 0.7) {
                $insights[] = array('severity' => 'medium', 'title' => 'Sales trend softening', 'text' => 'Sales at the end of the period are materially lower than at the start. Review pricing, availability, and cashier throughput.');
            }
        }

        if (empty($insights)) {
            $insights[] = array('severity' => 'low', 'title' => 'Stable operating picture', 'text' => 'No critical exceptions were detected in sales, stock, or cheque operations for the selected period.');
        }

        return $insights;
    }
}

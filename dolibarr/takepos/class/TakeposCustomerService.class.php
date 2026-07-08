<?php
require_once __DIR__ . '/TakeposLoyaltyService.class.php';
require_once __DIR__ . '/TakeposInputValidator.class.php';

/**
 * Customer lookup and CRM summary service.
 */
class TakeposCustomerService
{
    public static function searchCustomers($db, $entity, $query = '', $limit = 20)
    {
        $entity = (int) $entity;
        $limit = max(1, min(200, (int) $limit));
        $query = TakeposInputValidator::normalizeUtf8Text($query, 190, true);

        $sql = "SELECT rowid, code_client, nom, name_alias, email, phone, town, datec"
            . " FROM " . MAIN_DB_PREFIX . "societe"
            . " WHERE entity = " . $entity
            . " AND status >= 1";
        if ($query !== '') {
            $like = "'%" . $db->escape($query) . "%'";
            $sql .= " AND (nom LIKE " . $like . " OR name_alias LIKE " . $like . " OR code_client LIKE " . $like . " OR email LIKE " . $like . " OR phone LIKE " . $like . ")";
        }
        $sql .= " ORDER BY nom ASC LIMIT " . $limit;

        $rows = array();
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $rows[] = array(
                    'id' => (int) $obj->rowid,
                    'code' => (string) $obj->code_client,
                    'name' => (string) $obj->nom,
                    'alias' => (string) $obj->name_alias,
                    'email' => (string) $obj->email,
                    'phone' => (string) $obj->phone,
                    'town' => (string) $obj->town,
                    'created' => (string) $obj->datec,
                );
            }
        }

        return $rows;
    }

    public static function getCustomer($db, $entity, $customerId)
    {
        $sql = "SELECT rowid, code_client, nom, name_alias, email, phone, town, datec"
            . " FROM " . MAIN_DB_PREFIX . "societe"
            . " WHERE entity = " . ((int) $entity)
            . " AND rowid = " . ((int) $customerId)
            . " LIMIT 1";
        $resql = $db->query($sql);
        if (!$resql) {
            return null;
        }

        $obj = $db->fetch_object($resql);
        if (!$obj) {
            return null;
        }

        return array(
            'id' => (int) $obj->rowid,
            'code' => (string) $obj->code_client,
            'name' => (string) $obj->nom,
            'alias' => (string) $obj->name_alias,
            'email' => (string) $obj->email,
            'phone' => (string) $obj->phone,
            'town' => (string) $obj->town,
            'created' => (string) $obj->datec,
        );
    }

    public static function purchaseSummary($db, $entity, $customerId)
    {
        $summary = array(
            'purchase_count' => 0,
            'gross_sales' => 0.0,
            'last_purchase_date' => '',
        );

        $sql = "SELECT COUNT(*) AS purchase_count, COALESCE(SUM(f.total_ttc), 0) AS gross_sales, MAX(COALESCE(f.datef, f.datec)) AS last_purchase_date"
            . " FROM " . MAIN_DB_PREFIX . "facture f"
            . " WHERE f.entity = " . ((int) $entity)
            . " AND f.module_source = 'takepos'"
            . " AND f.fk_soc = " . ((int) $customerId)
            . " AND f.fk_statut >= 1";
        $resql = $db->query($sql);
        if ($resql && ($obj = $db->fetch_object($resql))) {
            $summary['purchase_count'] = (int) $obj->purchase_count;
            $summary['gross_sales'] = (float) $obj->gross_sales;
            $summary['last_purchase_date'] = !empty($obj->last_purchase_date) ? (string) $obj->last_purchase_date : '';
        }

        return $summary;
    }

    public static function recentTickets($db, $entity, $customerId, $limit = 15)
    {
        $rows = array();
        $limit = max(1, min(100, (int) $limit));

        $sql = "SELECT rowid, ref, total_ttc, fk_statut, paye, pos_source, COALESCE(datef, datec) AS invoice_date"
            . " FROM " . MAIN_DB_PREFIX . "facture"
            . " WHERE entity = " . ((int) $entity)
            . " AND module_source = 'takepos'"
            . " AND fk_soc = " . ((int) $customerId)
            . " ORDER BY rowid DESC LIMIT " . $limit;

        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $rows[] = array(
                    'invoice_id' => (int) $obj->rowid,
                    'invoice_ref' => (string) $obj->ref,
                    'invoice_date' => (string) $obj->invoice_date,
                    'total_ttc' => (float) $obj->total_ttc,
                    'status' => (int) $obj->fk_statut,
                    'paid' => (int) $obj->paye,
                    'terminal' => (string) $obj->pos_source,
                );
            }
        }

        return $rows;
    }

    public static function customerSummary($db, $entity, $customerId)
    {
        $customer = self::getCustomer($db, (int) $entity, (int) $customerId);
        if (!$customer) {
            return null;
        }

        $purchase = self::purchaseSummary($db, (int) $entity, (int) $customerId);
        $loyalty = TakeposLoyaltyService::customerLoyaltySummary($db, (int) $entity, (int) $customerId);

        return array(
            'customer' => $customer,
            'purchase' => $purchase,
            'loyalty' => $loyalty,
        );
    }
}

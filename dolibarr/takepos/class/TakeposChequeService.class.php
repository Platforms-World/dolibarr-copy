<?php
require_once __DIR__ . '/TakeposMigration.class.php';
require_once __DIR__ . '/TakeposUserAccess.class.php';
if (file_exists(__DIR__ . '/TakeposAudit.class.php')) require_once __DIR__ . '/TakeposAudit.class.php';

class TakeposChequeService
{
    const STATUS_PENDING = 'pending';
    const STATUS_COLLECTED = 'collected';
    const STATUS_BOUNCED = 'bounced';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_PARTIAL = 'partial';

    const DUE_OVERDUE = 'overdue';
    const DUE_TODAY = 'today';
    const DUE_UPCOMING = 'upcoming';
    const DUE_FUTURE = 'future';
    const DUE_CLOSED = 'closed';

    private static function table()
    {
        return MAIN_DB_PREFIX . 'takepos_cheque';
    }

    public static function ensureSchema($db)
    {
        $table = self::table();
        $sql = "CREATE TABLE " . $table . " ("
            . " rowid INTEGER AUTO_INCREMENT PRIMARY KEY,"
            . " entity INTEGER NOT NULL DEFAULT 1,"
            . " ref VARCHAR(32) NOT NULL,"
            . " cheque_number VARCHAR(64) NOT NULL,"
            . " fk_supplier INTEGER NOT NULL DEFAULT 0,"
            . " fk_purchase INTEGER NOT NULL DEFAULT 0,"
            . " bank_name VARCHAR(128) NOT NULL DEFAULT '',"
            . " amount DOUBLE(24,8) NOT NULL DEFAULT 0,"
            . " cheque_date DATE DEFAULT NULL,"
            . " collection_date DATE DEFAULT NULL,"
            . " status VARCHAR(24) NOT NULL DEFAULT 'pending',"
            . " note_private TEXT DEFAULT NULL,"
            . " fk_user_author INTEGER NOT NULL DEFAULT 0,"
            . " fk_user_modif INTEGER NOT NULL DEFAULT 0,"
            . " datec DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,"
            . " tms TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " UNIQUE KEY uk_takepos_cheque_entity_ref (entity, ref),"
            . " KEY idx_takepos_cheque_entity_status (entity, status),"
            . " KEY idx_takepos_cheque_entity_supplier (entity, fk_supplier),"
            . " KEY idx_takepos_cheque_entity_purchase (entity, fk_purchase),"
            . " KEY idx_takepos_cheque_entity_cheque_date (entity, cheque_date),"
            . " KEY idx_takepos_cheque_entity_collection_date (entity, collection_date)"
            . ") ENGINE=innodb";
        if (!TakeposMigration::ensureTable($db, $table, $sql)) return false;

        $cols = array(
            'entity' => "INTEGER NOT NULL DEFAULT 1",
            'ref' => "VARCHAR(32) NOT NULL",
            'cheque_number' => "VARCHAR(64) NOT NULL",
            'fk_supplier' => "INTEGER NOT NULL DEFAULT 0",
            'fk_purchase' => "INTEGER NOT NULL DEFAULT 0",
            'bank_name' => "VARCHAR(128) NOT NULL DEFAULT ''",
            'amount' => "DOUBLE(24,8) NOT NULL DEFAULT 0",
            'cheque_date' => "DATE DEFAULT NULL",
            'collection_date' => "DATE DEFAULT NULL",
            'status' => "VARCHAR(24) NOT NULL DEFAULT 'pending'",
            'note_private' => "TEXT DEFAULT NULL",
            'fk_user_author' => "INTEGER NOT NULL DEFAULT 0",
            'fk_user_modif' => "INTEGER NOT NULL DEFAULT 0",
            'datec' => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
            'tms' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        );
        foreach ($cols as $k => $v) {
            if (!TakeposMigration::ensureColumn($db, $table, $k, $v)) return false;
        }

        if (!TakeposMigration::ensureIndex($db, $table, 'uk_takepos_cheque_entity_ref', '(entity, ref)', 'UNIQUE')) return false;
        if (!TakeposMigration::ensureIndex($db, $table, 'idx_takepos_cheque_entity_status', '(entity, status)')) return false;
        if (!TakeposMigration::ensureIndex($db, $table, 'idx_takepos_cheque_entity_supplier', '(entity, fk_supplier)')) return false;
        if (!TakeposMigration::ensureIndex($db, $table, 'idx_takepos_cheque_entity_purchase', '(entity, fk_purchase)')) return false;
        if (!TakeposMigration::ensureIndex($db, $table, 'idx_takepos_cheque_entity_cheque_date', '(entity, cheque_date)')) return false;
        if (!TakeposMigration::ensureIndex($db, $table, 'idx_takepos_cheque_entity_collection_date', '(entity, collection_date)')) return false;

        return true;
    }

    public static function canRead($dbOrUser, $user = null)
    {
        $db = null;
        if ($user === null) {
            $user = $dbOrUser;
        } else {
            $db = $dbOrUser;
        }

        if (!empty($user->admin)) {
            return true;
        }
        if (is_object($db) && class_exists('TakeposUserAccess') && TakeposUserAccess::userHasPermission($db, $user, 'takepos.cheque.read')) {
            return true;
        }

        return ($user->hasRight('produit', 'lire') || $user->hasRight('service', 'lire'));
    }

    public static function canCreate($dbOrUser, $user = null)
    {
        $db = null;
        if ($user === null) {
            $user = $dbOrUser;
        } else {
            $db = $dbOrUser;
        }

        if (!empty($user->admin)) {
            return true;
        }
        if (is_object($db) && class_exists('TakeposUserAccess') && TakeposUserAccess::userHasPermission($db, $user, 'takepos.cheque.create')) {
            return true;
        }

        return ($user->hasRight('produit', 'creer') || $user->hasRight('service', 'creer'));
    }

    public static function statuses()
    {
        return array(self::STATUS_PENDING, self::STATUS_COLLECTED, self::STATUS_BOUNCED, self::STATUS_CANCELLED, self::STATUS_PARTIAL);
    }

    public static function dueWindows()
    {
        return array('', 'overdue', 'today', 'next7', 'next30');
    }

    private static function trans($key, $fallback)
    {
        global $langs;
        if (is_object($langs)) {
            $value = $langs->trans($key);
            if ($value !== $key) return $value;
        }
        return $fallback;
    }

    public static function statusLabel($status)
    {
        $map = array(
            self::STATUS_PENDING => self::trans('TakeposChequeStatusPending', 'Pending'),
            self::STATUS_COLLECTED => self::trans('TakeposChequeStatusCollected', 'Collected'),
            self::STATUS_BOUNCED => self::trans('TakeposChequeStatusBounced', 'Bounced'),
            self::STATUS_CANCELLED => self::trans('TakeposChequeStatusCancelled', 'Cancelled'),
            self::STATUS_PARTIAL => self::trans('TakeposChequeStatusPartial', 'Partially collected'),
        );
        return isset($map[$status]) ? $map[$status] : $status;
    }

    public static function dueWindowLabel($code)
    {
        $map = array(
            '' => self::trans('TakeposChequeDueWindowAll', 'All due dates'),
            'overdue' => self::trans('TakeposChequeDueWindowOverdue', 'Overdue'),
            'today' => self::trans('TakeposChequeDueWindowToday', 'Due today'),
            'next7' => self::trans('TakeposChequeDueWindowNext7', 'Next 7 days'),
            'next30' => self::trans('TakeposChequeDueWindowNext30', 'Next 30 days'),
        );
        return isset($map[$code]) ? $map[$code] : $code;
    }

    public static function dueStateLabel($state)
    {
        $map = array(
            self::DUE_OVERDUE => self::trans('TakeposChequeDueStateOverdue', 'Overdue'),
            self::DUE_TODAY => self::trans('TakeposChequeDueStateToday', 'Due today'),
            self::DUE_UPCOMING => self::trans('TakeposChequeDueStateUpcoming', 'Upcoming'),
            self::DUE_FUTURE => self::trans('TakeposChequeDueStateFuture', 'Scheduled later'),
            self::DUE_CLOSED => self::trans('TakeposChequeDueStateClosed', 'Closed'),
        );
        return isset($map[$state]) ? $map[$state] : $state;
    }

    public static function listSuppliers($db, $entity)
    {
        $rows = array();
        $sql = "SELECT rowid, code_fournisseur, nom, name_alias FROM " . MAIN_DB_PREFIX . "societe WHERE entity = " . ((int) $entity) . " AND fournisseur > 0 AND status = 1 ORDER BY nom ASC";
        $resql = $db->query($sql);
        if ($resql) while ($obj = $db->fetch_object($resql)) $rows[] = $obj;
        return $rows;
    }

    public static function listRecentPurchases($db, $entity, $limit = 50)
    {
        $rows = array();
        $sql = "SELECT p.rowid, p.ref, p.purchase_date, p.total_ttc, p.fk_supplier, s.nom AS supplier_name"
            . " FROM " . MAIN_DB_PREFIX . "takepos_purchase p"
            . " LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = p.fk_supplier"
            . " WHERE p.entity = " . ((int) $entity)
            . " ORDER BY p.purchase_date DESC, p.rowid DESC"
            . " LIMIT " . ((int) $limit);
        $resql = $db->query($sql);
        if ($resql) while ($obj = $db->fetch_object($resql)) $rows[] = $obj;
        return $rows;
    }

    public static function getPurchaseById($db, $entity, $purchaseId)
    {
        if ((int) $purchaseId <= 0) return null;
        $sql = "SELECT p.rowid, p.ref, p.purchase_date, p.total_ttc, p.fk_supplier, s.nom AS supplier_name"
            . " FROM " . MAIN_DB_PREFIX . "takepos_purchase p"
            . " LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = p.fk_supplier"
            . " WHERE p.entity = " . ((int) $entity) . " AND p.rowid = " . ((int) $purchaseId);
        $resql = $db->query($sql);
        return ($resql ? $db->fetch_object($resql) : null);
    }

    private static function validSupplier($db, $entity, $supplierId)
    {
        if ((int) $supplierId <= 0) return true;
        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "societe WHERE entity = " . ((int) $entity) . " AND fournisseur > 0 AND rowid = " . ((int) $supplierId);
        $resql = $db->query($sql);
        return ($resql && $db->num_rows($resql) > 0);
    }

    private static function validPurchase($db, $entity, $purchaseId)
    {
        if ((int) $purchaseId <= 0) return true;
        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "takepos_purchase WHERE entity = " . ((int) $entity) . " AND rowid = " . ((int) $purchaseId);
        $resql = $db->query($sql);
        return ($resql && $db->num_rows($resql) > 0);
    }

    private static function normalizeDate($value)
    {
        $value = trim((string) $value);
        if ($value === '') return '';
        if (preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $value)) return $value;
        return '';
    }

    private static function normalize($db, $user, $payload)
    {
        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $status = isset($payload['status']) ? trim((string) $payload['status']) : self::STATUS_PENDING;
        if (!in_array($status, self::statuses(), true)) throw new Exception(self::trans('TakeposChequeErrorInvalidStatus', 'Invalid cheque status.'));

        $number = trim((string) (isset($payload['cheque_number']) ? $payload['cheque_number'] : ''));
        if ($number === '') throw new Exception(self::trans('TakeposChequeErrorNumberRequired', 'Cheque number is required.'));

        $amount = price2num((string) (isset($payload['amount']) ? $payload['amount'] : 0), 'MU');
        if ($amount <= 0) throw new Exception(self::trans('TakeposChequeErrorAmountPositive', 'Cheque amount must be greater than zero.'));

        $chequeDate = self::normalizeDate(isset($payload['cheque_date']) ? $payload['cheque_date'] : '');
        if ($chequeDate === '') throw new Exception(self::trans('TakeposChequeErrorChequeDateRequired', 'Cheque date is required.'));

        $collectionDate = self::normalizeDate(isset($payload['collection_date']) ? $payload['collection_date'] : '');
        if ($collectionDate === '') throw new Exception(self::trans('TakeposChequeErrorCollectionDateRequired', 'Collection date is required.'));

        $supplierId = isset($payload['supplier_id']) ? (int) $payload['supplier_id'] : 0;
        if (!self::validSupplier($db, $entity, $supplierId)) throw new Exception(self::trans('TakeposChequeErrorSupplierInvalid', 'Invalid supplier.'));

        $purchaseId = isset($payload['purchase_id']) ? (int) $payload['purchase_id'] : 0;
        if (!self::validPurchase($db, $entity, $purchaseId)) throw new Exception(self::trans('TakeposChequeErrorPurchaseInvalid', 'Invalid purchase receipt.'));

        return array(
            'entity' => $entity,
            'cheque_number' => $number,
            'fk_supplier' => $supplierId,
            'fk_purchase' => $purchaseId,
            'bank_name' => trim((string) (isset($payload['bank_name']) ? $payload['bank_name'] : '')),
            'amount' => $amount,
            'cheque_date' => $chequeDate,
            'collection_date' => $collectionDate,
            'status' => $status,
            'note_private' => trim((string) (isset($payload['note_private']) ? $payload['note_private'] : '')),
        );
    }

    private static function nextRef($db, $entity)
    {
        $sql = "SELECT MAX(CAST(SUBSTRING(ref, 4) AS UNSIGNED)) AS seq FROM " . self::table() . " WHERE entity = " . ((int) $entity) . " AND ref LIKE 'CHQ%'";
        $resql = $db->query($sql);
        $next = 1;
        if ($resql) {
            $obj = $db->fetch_object($resql);
            if ($obj && !empty($obj->seq)) $next = ((int) $obj->seq) + 1;
        }
        return 'CHQ' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    public static function createCheque($db, $user, $payload)
    {
        $data = self::normalize($db, $user, $payload);
        $data['ref'] = self::nextRef($db, $data['entity']);

        $sql = "INSERT INTO " . self::table() . " (entity, ref, cheque_number, fk_supplier, fk_purchase, bank_name, amount, cheque_date, collection_date, status, note_private, fk_user_author, fk_user_modif, datec) VALUES ("
            . ((int) $data['entity']) . ", '" . $db->escape($data['ref']) . "', '" . $db->escape($data['cheque_number']) . "', "
            . ((int) $data['fk_supplier']) . ", " . ((int) $data['fk_purchase']) . ", '" . $db->escape($data['bank_name']) . "', "
            . price2num((string) $data['amount'], 'MU') . ", '" . $db->escape($data['cheque_date']) . "', '" . $db->escape($data['collection_date']) . "', '" . $db->escape($data['status']) . "', "
            . ($data['note_private'] !== '' ? "'" . $db->escape($data['note_private']) . "'" : 'NULL') . ", " . ((int) $user->id) . ", " . ((int) $user->id) . ", NOW())";
        if (!$db->query($sql)) throw new Exception($db->lasterror());

        $id = (int) $db->last_insert_id(self::table());
        if (class_exists('TakeposAudit')) {
            TakeposAudit::logEvent($db, $user, 'cheque_created', TakeposAudit::SEVERITY_INFO, array('cheque_id' => $id, 'ref' => $data['ref'], 'amount' => $data['amount']), self::trans('TakeposChequeAuditCreated', 'Supplier cheque created'), 'takepos_cheque', $id, $data['amount']);
        }
        return $id;
    }

    public static function updateCheque($db, $user, $id, $payload)
    {
        $current = self::getChequeById($db, !empty($user->entity) ? (int) $user->entity : 1, $id);
        if (!$current) throw new Exception(self::trans('TakeposChequeErrorRecordNotFound', 'Cheque record not found.'));

        $data = self::normalize($db, $user, $payload);
        $sql = "UPDATE " . self::table() . " SET cheque_number='" . $db->escape($data['cheque_number']) . "', fk_supplier=" . ((int) $data['fk_supplier'])
            . ", fk_purchase=" . ((int) $data['fk_purchase']) . ", bank_name='" . $db->escape($data['bank_name']) . "', amount=" . price2num((string) $data['amount'], 'MU')
            . ", cheque_date='" . $db->escape($data['cheque_date']) . "', collection_date='" . $db->escape($data['collection_date']) . "', status='" . $db->escape($data['status']) . "', note_private="
            . ($data['note_private'] !== '' ? "'" . $db->escape($data['note_private']) . "'" : 'NULL') . ", fk_user_modif=" . ((int) $user->id) . ", tms=NOW() WHERE rowid=" . ((int) $id) . " AND entity=" . ((int) $data['entity']);
        if (!$db->query($sql)) throw new Exception($db->lasterror());

        if (class_exists('TakeposAudit')) {
            TakeposAudit::logEvent($db, $user, 'cheque_updated', TakeposAudit::SEVERITY_INFO, array('cheque_id' => $id, 'ref' => $current->ref, 'amount' => $data['amount']), self::trans('TakeposChequeAuditUpdated', 'Supplier cheque updated'), 'takepos_cheque', $id, $data['amount']);
        }
        return $id;
    }

    public static function getChequeById($db, $entity, $id)
    {
        $sql = "SELECT c.*, s.nom AS supplier_name, p.ref AS purchase_ref, p.purchase_date, p.total_ttc AS purchase_total"
            . " FROM " . self::table() . " c"
            . " LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = c.fk_supplier"
            . " LEFT JOIN " . MAIN_DB_PREFIX . "takepos_purchase p ON p.rowid = c.fk_purchase"
            . " WHERE c.entity = " . ((int) $entity) . " AND c.rowid = " . ((int) $id);
        $resql = $db->query($sql);
        $row = ($resql ? $db->fetch_object($resql) : null);
        if ($row) {
            $row->due_state = self::dueState($row);
            $row->due_state_label = self::dueStateLabel($row->due_state);
        }
        return $row;
    }

    public static function dueState($row, $today = '')
    {
        $today = $today ? $today : date('Y-m-d');
        $status = isset($row->status) ? (string) $row->status : '';
        if ($status === self::STATUS_COLLECTED || $status === self::STATUS_CANCELLED) return self::DUE_CLOSED;

        $collectionDate = isset($row->collection_date) ? (string) $row->collection_date : '';
        if ($collectionDate === '') return self::DUE_FUTURE;
        if ($collectionDate < $today) return self::DUE_OVERDUE;
        if ($collectionDate === $today) return self::DUE_TODAY;

        $days = (int) floor((strtotime($collectionDate) - strtotime($today)) / 86400);
        if ($days <= 7) return self::DUE_UPCOMING;
        return self::DUE_FUTURE;
    }

    public static function dueClass($state)
    {
        $map = array(
            self::DUE_OVERDUE => 'due-overdue',
            self::DUE_TODAY => 'due-today',
            self::DUE_UPCOMING => 'due-upcoming',
            self::DUE_FUTURE => 'due-future',
            self::DUE_CLOSED => 'due-closed',
        );
        return isset($map[$state]) ? $map[$state] : 'due-future';
    }

    public static function listCheques($db, $entity, $filters = array(), $limit = 250)
    {
        $filters = is_array($filters) ? $filters : array();
        $rows = array();
        $sql = "SELECT c.*, s.nom AS supplier_name, p.ref AS purchase_ref, p.purchase_date, p.total_ttc AS purchase_total"
            . " FROM " . self::table() . " c"
            . " LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = c.fk_supplier"
            . " LEFT JOIN " . MAIN_DB_PREFIX . "takepos_purchase p ON p.rowid = c.fk_purchase"
            . " WHERE c.entity = " . ((int) $entity);

        if (!empty($filters['status']) && in_array($filters['status'], self::statuses(), true)) {
            $sql .= " AND c.status = '" . $db->escape($filters['status']) . "'";
        }
        if (!empty($filters['supplier_id'])) {
            $sql .= " AND c.fk_supplier = " . ((int) $filters['supplier_id']);
        }
        if (!empty($filters['purchase_id'])) {
            $sql .= " AND c.fk_purchase = " . ((int) $filters['purchase_id']);
        }
        if (!empty($filters['date_from'])) {
            $dateFrom = self::normalizeDate($filters['date_from']);
            if ($dateFrom !== '') $sql .= " AND c.collection_date >= '" . $db->escape($dateFrom) . "'";
        }
        if (!empty($filters['date_to'])) {
            $dateTo = self::normalizeDate($filters['date_to']);
            if ($dateTo !== '') $sql .= " AND c.collection_date <= '" . $db->escape($dateTo) . "'";
        }
        if (!empty($filters['search'])) {
            $search = $db->escape(trim((string) $filters['search']));
            $sql .= " AND (c.cheque_number LIKE '%" . $search . "%' OR c.bank_name LIKE '%" . $search . "%' OR c.ref LIKE '%" . $search . "%' OR s.nom LIKE '%" . $search . "%')";
        }

        $today = date('Y-m-d');
        if (!empty($filters['due_window'])) {
            $window = (string) $filters['due_window'];
            if ($window === 'overdue') {
                $sql .= " AND c.status IN ('pending', 'partial', 'bounced') AND c.collection_date < '" . $db->escape($today) . "'";
            } elseif ($window === 'today') {
                $sql .= " AND c.status IN ('pending', 'partial', 'bounced') AND c.collection_date = '" . $db->escape($today) . "'";
            } elseif ($window === 'next7') {
                $sql .= " AND c.status IN ('pending', 'partial', 'bounced') AND c.collection_date >= '" . $db->escape($today) . "' AND c.collection_date <= DATE_ADD('" . $db->escape($today) . "', INTERVAL 7 DAY)";
            } elseif ($window === 'next30') {
                $sql .= " AND c.status IN ('pending', 'partial', 'bounced') AND c.collection_date >= '" . $db->escape($today) . "' AND c.collection_date <= DATE_ADD('" . $db->escape($today) . "', INTERVAL 30 DAY)";
            }
        }

        $sql .= " ORDER BY (CASE WHEN c.status IN ('pending','partial','bounced') THEN 0 ELSE 1 END), c.collection_date ASC, c.cheque_date DESC, c.rowid DESC LIMIT " . ((int) $limit);

        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $obj->due_state = self::dueState($obj, $today);
                $obj->due_state_label = self::dueStateLabel($obj->due_state);
                $rows[] = $obj;
            }
        }
        return $rows;
    }

    public static function summarize($rows)
    {
        $summary = array(
            'total' => 0.0,
            'count' => 0,
            'pending' => 0.0,
            'collected' => 0.0,
            'bounced' => 0.0,
            'overdue' => 0.0,
            'due_today' => 0.0,
            'upcoming_7' => 0.0,
            'overdue_count' => 0,
            'due_today_count' => 0,
            'upcoming_7_count' => 0,
        );
        $today = date('Y-m-d');

        foreach ($rows as $row) {
            $amt = (float) $row->amount;
            $summary['total'] += $amt;
            $summary['count']++;

            if ($row->status === self::STATUS_PENDING || $row->status === self::STATUS_PARTIAL) $summary['pending'] += $amt;
            if ($row->status === self::STATUS_COLLECTED) $summary['collected'] += $amt;
            if ($row->status === self::STATUS_BOUNCED) $summary['bounced'] += $amt;

            $dueState = isset($row->due_state) ? $row->due_state : self::dueState($row, $today);
            if ($dueState === self::DUE_OVERDUE) {
                $summary['overdue'] += $amt;
                $summary['overdue_count']++;
            } elseif ($dueState === self::DUE_TODAY) {
                $summary['due_today'] += $amt;
                $summary['due_today_count']++;
            } elseif ($dueState === self::DUE_UPCOMING) {
                $summary['upcoming_7'] += $amt;
                $summary['upcoming_7_count']++;
            }
        }

        return $summary;
    }

    public static function buildAlerts($summary)
    {
        $alerts = array();
        if (!empty($summary['overdue_count'])) {
            $alerts[] = array(
                'type' => 'danger',
                'label' => self::trans('TakeposChequeAlertOverdue', 'Overdue cheques'),
                'count' => (int) $summary['overdue_count'],
                'amount' => (float) $summary['overdue'],
            );
        }
        if (!empty($summary['due_today_count'])) {
            $alerts[] = array(
                'type' => 'warning',
                'label' => self::trans('TakeposChequeAlertDueToday', 'Cheques due today'),
                'count' => (int) $summary['due_today_count'],
                'amount' => (float) $summary['due_today'],
            );
        }
        if (!empty($summary['upcoming_7_count'])) {
            $alerts[] = array(
                'type' => 'info',
                'label' => self::trans('TakeposChequeAlertUpcoming7', 'Cheques due in the next 7 days'),
                'count' => (int) $summary['upcoming_7_count'],
                'amount' => (float) $summary['upcoming_7'],
            );
        }
        return $alerts;
    }
}

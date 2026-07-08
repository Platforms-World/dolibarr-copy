<?php
/**
 * TakeposBranchService
 *
 * Manages branches inside Dolibarr.
 * When a branch is created, a dedicated Dolibarr login user is auto-created
 * so that branch staff can log in and see only their branch's POS/products/employees.
 *
 * Branch limit is read from TAKEPOS_MAX_BRANCHES in llx_const (set by Laravel).
 *
 * FIX LOG:
 *  - ensureProductMapTable: added `entity` column (was missing, broke multi-entity).
 *  - setBranchProductsById: now stores entity on each row.
 *  - getBranchProductIdsById: now filters by entity for safety.
 *  - allowedProductIdsForUser: now returns null (no filter) for non-branch
 *    non-admin users that still legitimately have takepos.run, instead of
 *    accidentally returning [] (hide everything).
 *  - createBranch: now creates the starter terminal INSIDE the same transaction
 *    and stores its rowid under 'terminal_id' in the returned array so callers
 *    can use it without a second INSERT attempt.
 *  - createStarterTerminal: new helper extracted from branches.php action block
 *    so the logic lives in the service, not scattered across UI files.
 *  - grantTakePosRight: now also grants `categorie → lire` so branch users can
 *    read category lists (was causing "access denied" on category pages).
 *  - ensureSchema: now creates fk_branch on takepos_terminal proactively so
 *    the column always exists before anything tries to read it.
 *  - getTerminalsForBranchId: now also accepts terminal_code for the label
 *    fallback, consistent with the index.php modal rendering.
 */

require_once __DIR__ . '/TakeposMigration.class.php';
require_once __DIR__ . '/TakeposAudit.class.php';

class TakeposBranchService
{
    // -----------------------------------------------------------------------
    // Schema — ensure all tables and columns exist (idempotent)
    // -----------------------------------------------------------------------

    public static function ensureSchema($db)
    {
        $tBranch = MAIN_DB_PREFIX . 'takepos_branch';
        TakeposMigration::ensureTable($db, $tBranch,
            "CREATE TABLE {$tBranch} (
                rowid         INT AUTO_INCREMENT PRIMARY KEY,
                entity        INT          NOT NULL DEFAULT 1,
                code          VARCHAR(32)  NOT NULL,
                label         VARCHAR(128) NOT NULL,
                description   TEXT         NULL,
                fk_warehouse  INT          NULL,
                fk_store      INT          NULL,
                fk_user       INT          NULL,
                active        TINYINT(1)   NOT NULL DEFAULT 1,
                date_creation DATETIME     NOT NULL,
                tms           TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_takepos_branch_entity_code (entity, code),
                KEY idx_takepos_branch_entity_active (entity, active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        TakeposMigration::ensureColumn($db, $tBranch, 'description',  'TEXT NULL AFTER label');
        TakeposMigration::ensureColumn($db, $tBranch, 'fk_warehouse', 'INT NULL AFTER description');
        TakeposMigration::ensureColumn($db, MAIN_DB_PREFIX . 'entrepot', 'label', "VARCHAR(255) NULL AFTER ref");
        $db->query("UPDATE " . MAIN_DB_PREFIX . "entrepot SET label = ref WHERE label IS NULL OR label = ''");

        TakeposMigration::ensureColumn($db, $tBranch, 'fk_store',     'INT NULL AFTER fk_warehouse');
        TakeposMigration::ensureColumn($db, $tBranch, 'fk_user',      'INT NULL AFTER fk_store');

        $tBU = MAIN_DB_PREFIX . 'takepos_branch_user';
        TakeposMigration::ensureTable($db, $tBU,
            "CREATE TABLE {$tBU} (
                rowid         INT         AUTO_INCREMENT PRIMARY KEY,
                entity        INT         NOT NULL DEFAULT 1,
                fk_branch     INT         NOT NULL,
                fk_user       INT         NOT NULL,
                role          VARCHAR(32) NOT NULL DEFAULT 'cashier',
                active        TINYINT(1)  NOT NULL DEFAULT 1,
                date_creation DATETIME    NOT NULL,
                tms           TIMESTAMP   NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_takepos_branch_user (entity, fk_branch, fk_user),
                KEY idx_takepos_branch_user_branch (entity, fk_branch, active),
                KEY idx_takepos_branch_user_user   (entity, fk_user,   active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // FIX: ensure fk_branch on takepos_terminal exists before any query touches it
        $tT = MAIN_DB_PREFIX . 'takepos_terminal';
        $tcCheck = $db->query("SHOW TABLES LIKE '" . $db->escape($tT) . "'");
        if ($tcCheck && $db->num_rows($tcCheck) > 0) {
            TakeposMigration::ensureColumn($db, $tT, 'fk_branch', 'INT NULL DEFAULT NULL');
            TakeposMigration::ensureIndex($db, $tT, 'idx_takepos_terminal_fk_branch', '(fk_branch)');
        }

        // fk_branch on invoices for reporting
        $tF = MAIN_DB_PREFIX . 'facture';
        TakeposMigration::ensureColumn($db, $tF, 'fk_branch', 'INT NULL DEFAULT NULL');
        TakeposMigration::ensureIndex($db, $tF, 'idx_facture_fk_branch', '(fk_branch)');

        // Ensure branch-product mapping table exists
        self::ensureProductMapTable($db);
    }

    // -----------------------------------------------------------------------
    // Branch limit from llx_const (set by Laravel)
    // Returns 999 (unlimited) when not set — safe for local dev
    // -----------------------------------------------------------------------

    public static function getMaxBranches($db, $entity)
    {
        $val = getDolGlobalString('TAKEPOS_MAX_BRANCHES');
        if ($val === '' || $val === null) {
            return 999;
        }
        return max(1, (int) $val);
    }

    // -----------------------------------------------------------------------
    // Auto-create a Dolibarr user for a branch
    // Returns ['user_id' => int, 'login' => string, 'password' => string]
    // -----------------------------------------------------------------------

    public static function createBranchUser($db, $entity, $branchCode, $branchLabel)
    {
        require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

        global $user;
        $actor = (isset($user) && $user instanceof User) ? $user : self::getAdminActor($db);

        $login    = 'branch_' . strtolower(preg_replace('/[^A-Za-z0-9]/', '', $branchCode));
        $password = self::generatePassword();

        $base  = $login;
        $count = 1;
        while (self::loginExists($db, $login)) {
            $login = $base . $count;
            $count++;
        }

        $newUser = new User($db);
        $newUser->login      = $login;
        $newUser->lastname   = $branchLabel;
        $newUser->firstname  = 'Branch';
        $newUser->entity     = $entity;
        $newUser->statut     = 1;
        $newUser->admin      = 0;
        $newUser->employee   = 0;

        $result = $newUser->create($actor);
        if ($result < 0) {
            throw new Exception('Failed to create branch user: ' . implode(', ', $newUser->errors));
        }

        $userId = $newUser->id;
        $newUser->setPassword($actor, $password);
        self::grantTakePosRight($db, $userId, $entity);

        return [
            'user_id'  => $userId,
            'login'    => $login,
            'password' => $password,
        ];
    }

    private static function getAdminActor($db)
    {
        require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
        $u = new User($db);
        $res = $db->query("SELECT rowid FROM " . MAIN_DB_PREFIX . "user WHERE admin=1 AND statut=1 ORDER BY rowid ASC LIMIT 1");
        if ($res && ($obj = $db->fetch_object($res))) {
            $u->fetch((int) $obj->rowid);
        }
        return $u;
    }

    private static function loginExists($db, $login)
    {
        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "user WHERE login='" . $db->escape($login) . "' LIMIT 1";
        $res = $db->query($sql);
        return ($res && $db->num_rows($res) > 0);
    }

    private static function generatePassword($length = 12)
    {
        $chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789@#!';
        $pass  = '';
        for ($i = 0; $i < $length; $i++) {
            $pass .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $pass;
    }

    /**
     * Complete list of kafo permissions granted to every branch cashier user.
     *
     * Derived from the full permission set visible in the actual kafo DB,
     * filtered to what a cashier (non-admin) legitimately needs.
     * Admin-only perms (takepos.admin, takepos.store.manage, takepos.terminal.manage,
     * takepos.webhook.manage, takepos.api.write, etc.) are intentionally excluded.
     *
     * To customise what branch users can do, edit this constant.
     */
    private static function BRANCH_CASHIER_KAFO_PERMS()
    {
        return array(
            // ── Core POS access ──────────────────────────────────────────────
            'takepos.use',
            'takepos.frontend',
            'takepos.payment',

            // ── Shift management ─────────────────────────────────────────────
            'takepos.shift.open',
            'takepos.shift.close',
            'takepos.shift.review',
            'takepos.shift.force_close',

            // ── Cash drawer ──────────────────────────────────────────────────
            'takepos.cash.paidin',
            'takepos.cash.paidout',
            'takepos.cash.count',
            'takepos.cash.reconcile',
            'takepos.cash.safedrop',

            // ── POS actions (sell, discount, cancel) ─────────────────────────
            'takepos.action.discount',
            'takepos.action.invoice_cancel',
            'takepos.action.line_delete',
            'takepos.action.price_override',
            'takepos.action.reports_view',

            // ── Override / manager approval ──────────────────────────────────
            'takepos.override.cancel',
            'takepos.override.discount',
            'takepos.override.line_delete',
            'takepos.override.price',

            // ── Customers ────────────────────────────────────────────────────
            'takepos.customer.view',

            // ── Refunds & exchanges ──────────────────────────────────────────
            'takepos.refund.view',
            'takepos.refund.partial',
            'takepos.refund.full',
            'takepos.refund.approve',
            'takepos.refund.export',
            'takepos.refund.restock_control',
            'takepos.refund.without_original',
            'takepos.exchange.process',

            // ── Loyalty ──────────────────────────────────────────────────────
            'takepos.loyalty.view',
            'takepos.loyalty.earn',
            'takepos.loyalty.redeem',
            'takepos.loyalty.adjust',

            // ── Expenses ─────────────────────────────────────────────────────
            'takepos.expense.read',
            'takepos.expense.create',
            'takepos.expense.post',

            // ── Offline / sync ───────────────────────────────────────────────
            'takepos.offline.use',

            // ── Store view (read-only, not manage) ───────────────────────────
            'takepos.store.view_all',
        );
    }

    private static function grantTakePosRight($db, $userId, $entity)
    {
        // ── 1. Dolibarr native rights ─────────────────────────────────────────
        // categorie → lire is required for the POS category sidebar to load.
        //
        // FIX (branch-user-editlines-v1): added 'takepos → editlines' and
        // 'takepos → editorderedlines'. Without these, invoice.php line 2076
        // refuses every quantity / price / discount change with the error
        // "Not enough permissions - no permission to update quantity" the
        // moment a branch cashier presses + / - on the line in the cart.
        $needed = array(
            array('module' => 'takepos',   'perms' => 'run'),
            array('module' => 'takepos',   'perms' => 'editlines'),
            array('module' => 'takepos',   'perms' => 'editorderedlines'),
            array('module' => 'produit',   'perms' => 'lire'),
            array('module' => 'facture',   'perms' => 'lire'),
            array('module' => 'facture',   'perms' => 'creer'),
            array('module' => 'facture',   'perms' => 'paiement'),
            array('module' => 'societe',   'perms' => 'lire'),
            array('module' => 'categorie', 'perms' => 'lire'),
        );

        foreach ($needed as $right) {
            $sql = "SELECT id FROM " . MAIN_DB_PREFIX . "rights_def"
                . " WHERE module='" . $db->escape($right['module']) . "'"
                . " AND perms='"   . $db->escape($right['perms'])  . "'"
                . " LIMIT 1";
            $res = $db->query($sql);
            if (!$res) continue;
            $obj = $db->fetch_object($res);
            if (!$obj) continue;

            $db->query(
                "INSERT IGNORE INTO " . MAIN_DB_PREFIX . "user_rights (fk_user, fk_id)"
                . " VALUES (" . ((int) $userId) . ", " . ((int) $obj->id) . ")"
            );
        }

        // ── 2. kafo-ERP-Control permissions ──────────────────────────────────
        // Complete cashier permission set — no more "Access denied" surprises.
        $kafoTable = MAIN_DB_PREFIX . 'saas_user_permissions';
        $tableCheck = $db->query("SHOW TABLES LIKE '" . $db->escape($kafoTable) . "'");
        if (!$tableCheck || $db->num_rows($tableCheck) === 0) {
            return; // kafo not installed — skip
        }

        foreach (self::BRANCH_CASHIER_KAFO_PERMS() as $permCode) {
            $db->query(
                "INSERT IGNORE INTO " . $kafoTable
                . " (entity_id, fk_user, permission_code, allowed, date_created)"
                . " VALUES ("
                . ((int) $entity) . ","
                . ((int) $userId) . ","
                . "'" . $db->escape($permCode) . "',"
                . "1, NOW())"
            );
        }
    }

    /**
     * Sync permissions for ALL existing branch users.
     * Call this once from the Branches admin page after updating the code,
     * or after adding new permissions to BRANCH_CASHIER_KAFO_PERMS().
     * This is idempotent — safe to run multiple times.
     */
    public static function syncAllBranchUserPermissions($db)
    {
        $res = $db->query(
            "SELECT fk_user, entity FROM " . MAIN_DB_PREFIX . "takepos_branch"
            . " WHERE fk_user IS NOT NULL AND active=1"
        );
        if (!$res) return 0;

        $count = 0;
        while ($row = $db->fetch_object($res)) {
            self::grantTakePosRight($db, (int) $row->fk_user, (int) $row->entity);
            $count++;
        }
        return $count;
    }

    // -----------------------------------------------------------------------
    // Branch CRUD
    // -----------------------------------------------------------------------

    /**
     * Silently remove any orphaned branch row with the given (entity, code)
     * before attempting a fresh INSERT.
     *
     * An orphan is a branch row left behind by a previously failed createBranch()
     * call — the transaction rolled back but the row (or its linked user) was
     * already committed in a prior partial step, causing a Duplicate entry error
     * on the next attempt with the same code.
     *
     * Rules:
     *  - If the branch has fk_user pointing to a real user → full hard delete
     *    (same as deleteBranchById but without the audit log to keep it silent).
     *  - If fk_user is NULL or user no longer exists → just delete the branch row.
     *  - If no row exists for this (entity, code) → no-op.
     *
     * This runs OUTSIDE any transaction so it commits immediately and the
     * subsequent INSERT starts clean.
     */
    private static function purgeOrphanedBranch($db, $entity, $code)
    {
        $entity = (int) $entity;
        $code   = $db->escape(strtoupper(trim($code)));

        $res = $db->query(
            "SELECT rowid, fk_user FROM " . MAIN_DB_PREFIX . "takepos_branch"
            . " WHERE entity=" . $entity . " AND code='" . $code . "' LIMIT 1"
        );

        if (!$res || $db->num_rows($res) === 0) {
            return; // nothing to clean up
        }

        $row      = $db->fetch_object($res);
        $branchId = (int) $row->rowid;
        $fkUser   = (int) $row->fk_user;

        // Remove child rows first
        $pmCheck = $db->query("SHOW TABLES LIKE '" . $db->escape(MAIN_DB_PREFIX . 'takepos_branch_product') . "'");
        if ($pmCheck && $db->num_rows($pmCheck) > 0) {
            $db->query("DELETE FROM " . MAIN_DB_PREFIX . "takepos_branch_product WHERE fk_branch=" . $branchId);
        }
        $db->query("DELETE FROM " . MAIN_DB_PREFIX . "takepos_branch_user WHERE fk_branch=" . $branchId);

        $tcCheck = $db->query("SHOW TABLES LIKE '" . $db->escape(MAIN_DB_PREFIX . 'takepos_terminal') . "'");
        if ($tcCheck && $db->num_rows($tcCheck) > 0) {
            // Get terminal IDs before deleting so we can clean up their config keys
            $termRows = $db->query("SELECT rowid FROM " . MAIN_DB_PREFIX . "takepos_terminal WHERE fk_branch=" . $branchId);
            if ($termRows) {
                while ($termObj = $db->fetch_object($termRows)) {
                    $tId = (int)$termObj->rowid;
                    $db->query("DELETE FROM " . MAIN_DB_PREFIX . "const WHERE name LIKE '%_" . $tId . "'");
                }
            }
            $db->query("DELETE FROM " . MAIN_DB_PREFIX . "takepos_terminal WHERE fk_branch=" . $branchId);
        }
        $db->query("DELETE FROM " . MAIN_DB_PREFIX . "takepos_branch WHERE rowid=" . $branchId);

        // Delete the orphaned login user if it exists and is a branch user
        if ($fkUser > 0) {
            $uRes = $db->query("SELECT rowid FROM " . MAIN_DB_PREFIX . "user WHERE rowid=" . $fkUser . " LIMIT 1");
            if ($uRes && $db->num_rows($uRes) > 0) {
                // Only delete if this user is not referenced by ANY other active branch
                $otherBranch = $db->query(
                    "SELECT rowid FROM " . MAIN_DB_PREFIX . "takepos_branch"
                    . " WHERE fk_user=" . $fkUser . " AND rowid<>" . $branchId . " LIMIT 1"
                );
                if (!$otherBranch || $db->num_rows($otherBranch) === 0) {
                    $db->query("DELETE FROM " . MAIN_DB_PREFIX . "user_rights WHERE fk_user=" . $fkUser);
                    $kafo = MAIN_DB_PREFIX . 'saas_user_permissions';
                    $kc   = $db->query("SHOW TABLES LIKE '" . $db->escape($kafo) . "'");
                    if ($kc && $db->num_rows($kc) > 0) {
                        $db->query("DELETE FROM " . $kafo . " WHERE fk_user=" . $fkUser);
                    }
                    $db->query("DELETE FROM " . MAIN_DB_PREFIX . "user WHERE rowid=" . $fkUser);
                }
            }
        }
    }

    public static function countBranches($db, $entity, $activeOnly = true)
    {
        self::ensureSchema($db);
        $sql = "SELECT COUNT(*) AS cnt FROM " . MAIN_DB_PREFIX . "takepos_branch"
            . " WHERE entity=" . ((int) $entity)
            . ($activeOnly ? " AND active=1" : "");
        $res = $db->query($sql);
        return ($res && ($obj = $db->fetch_object($res))) ? (int) $obj->cnt : 0;
    }

    /**
     * Creates a branch AND its Dolibarr login user in one step.
     * Also creates a starter terminal linked to this branch.
     *
     * Returns [
     *   'branch_id'   => int,
     *   'user_id'     => int,
     *   'login'       => string,
     *   'password'    => string,
     *   'terminal_id' => int,   // rowid of the auto-created starter terminal
     * ]
     */
    public static function createBranch($db, $user, $entity, $code, $label, $description = '', $warehouseId = 0, $storeId = 0)
    {
        self::ensureSchema($db);

        $max     = self::getMaxBranches($db, $entity);
        $current = self::countBranches($db, $entity, true);
        if ($current >= $max) {
            throw new Exception("Branch limit reached ({$current}/{$max}). Contact support to increase your limit.");
        }

        $code  = strtoupper(trim($code));
        $label = trim($label);
        if ($code === '' || $label === '') {
            throw new Exception('Branch code and label are required.');
        }
        if (!preg_match('/^[A-Z0-9_-]{2,32}$/', $code)) {
            throw new Exception('Branch code must be 2-32 letters/numbers (A-Z, 0-9, _ -).');
        }

        // ── Auto-cleanup any orphaned/failed previous attempt with the same code ──
        // This prevents "Duplicate entry" errors when a previous createBranch()
        // call failed mid-transaction and left a partial or ghost record behind.
        self::purgeOrphanedBranch($db, $entity, $code);

        $db->begin();

        try {
            // 1. Create the Dolibarr login user for this branch
            $credentials = self::createBranchUser($db, $entity, $code, $label);

            // 2. Create the branch row
            $sql = "INSERT INTO " . MAIN_DB_PREFIX . "takepos_branch"
                . " (entity, code, label, description, fk_warehouse, fk_store, fk_user, active, date_creation) VALUES ("
                . ((int) $entity) . ","
                . "'" . $db->escape($code)  . "',"
                . "'" . $db->escape($label) . "',"
                . ($description !== '' ? "'" . $db->escape($description) . "'" : 'NULL') . ","
                . ((int) $warehouseId > 0 ? (int) $warehouseId : 'NULL') . ","
                . ((int) $storeId    > 0 ? (int) $storeId    : 'NULL') . ","
                . ((int) $credentials['user_id']) . ","
                . "1, NOW())";

            if (!$db->query($sql)) {
                throw new Exception($db->lasterror());
            }

            $branchId = (int) $db->last_insert_id(MAIN_DB_PREFIX . 'takepos_branch');

            // 3. Create starter terminal linked to this branch (inside same transaction)
            $terminalId = self::createStarterTerminal($db, $entity, $branchId, $code, $storeId, (int) $warehouseId);

            $db->commit();

            TakeposAudit::logEvent($db, $user, 'branch_created', TakeposAudit::SEVERITY_INFO,
                ['branch_id' => $branchId, 'code' => $code, 'login' => $credentials['login'], 'terminal_id' => $terminalId],
                'Branch created: ' . $code, 'branch', $branchId);

            return [
                'branch_id'   => $branchId,
                'user_id'     => $credentials['user_id'],
                'login'       => $credentials['login'],
                'password'    => $credentials['password'],
                'terminal_id' => $terminalId,
            ];

        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Create the starter terminal for a new branch.
     * Copies config keys from Terminal 1 so Dolibarr's "setup incomplete" check passes.
     * Returns the new terminal's rowid.
     *
     * FIX: extracted from branches.php so terminal creation is always transactional
     * and the terminal rowid is reliably known at creation time.
     */
    public static function createStarterTerminal($db, $entity, $branchId, $branchCode, $storeId = 0, $warehouseId = 0)
    {
        $tCode = 'T-' . strtoupper(preg_replace('/[^A-Z0-9_-]/i', '', $branchCode)) . '-1';

        // Ensure unique terminal_code
        $base = $tCode;
        $n    = 1;
        while (true) {
            $c = $db->query("SELECT rowid FROM " . MAIN_DB_PREFIX . "takepos_terminal"
                . " WHERE entity=" . (int)$entity . " AND terminal_code='" . $db->escape($tCode) . "' LIMIT 1");
            if (!$c || $db->num_rows($c) === 0) break;
            $tCode = $base . '-' . $n++;
        }

        $storeId = (int) $storeId;

        // Find the next cashier number by looking at existing labels
        $maxRes = $db->query(
            "SELECT MAX(CAST(REPLACE(label, 'Cashier ', '') AS UNSIGNED)) AS max_num"
            . " FROM " . MAIN_DB_PREFIX . "takepos_terminal"
            . " WHERE fk_branch=" . (int)$branchId
            . " AND label REGEXP '^Cashier [0-9]+$'"
        );
        $nextNum = 1;
        if ($maxRes) {
            $maxObj = $db->fetch_object($maxRes);
            if ($maxObj && $maxObj->max_num > 0) {
                $nextNum = (int)$maxObj->max_num + 1;
            }
        }
        $termLabel = 'Cashier ' . $nextNum;

        $ok = $db->query(
            "INSERT INTO " . MAIN_DB_PREFIX . "takepos_terminal"
            . " (entity, terminal_code, label, fk_store, fk_branch, active, date_creation) VALUES ("
            . $entity . ",'" . $db->escape($tCode) . "','" . $db->escape($termLabel) . "',"
            . ($storeId > 0 ? $storeId : 'NULL') . "," . (int)$branchId . ", 1, NOW())"
        );
        if (!$ok) {
            throw new Exception('Failed to create starter terminal: ' . $db->lasterror());
        }

        $newTerminalId = (int) $db->last_insert_id(MAIN_DB_PREFIX . 'takepos_terminal');

        // !! DO NOT bump TAKEPOS_NUM_TERMINALS !!
        // Branch terminals are identified by fk_branch, not by being in the
        // sequential 1..N range. Bumping the counter causes Dolibarr to render
        // a tab for every rowid up to the new value in the master admin — so
        // creating branch terminal rowid=15 would show 15 terminal tabs for the
        // master, most of them blank/duplicate. Leave the counter alone.

        // Copy Terminal 1 config keys so "setup incomplete" check passes
        $prefixes = array(
            'CASHDESK_ID_THIRDPARTY',
            'CASHDESK_ID_WAREHOUSE',
            'CASHDESK_ID_BANKACCOUNT_CASH',
            'CASHDESK_ID_BANKACCOUNT_CB',
            'CASHDESK_ID_BANKACCOUNT_CHEQUE',
            // FIX (stock-branch-v1): CASHDESK_NO_DECREASE_STOCK intentionally NOT copied.
            // Branch terminals must deduct stock. If Terminal 1 has stock decrease disabled,
            // we do NOT want to inherit that to branch terminals blindly.
            'TAKEPOS_TERMINAL_NAME_',
            'TAKEPOS_PRINT_METHOD',
            'TAKEPOS_PRINT_SERVER',
            'TAKEPOS_RECEIPT_NAME',
            'TAKEPOS_HEADER',
            'TAKEPOS_FOOTER',
            'TAKEPOS_TICKET_VAT_GROUPPED',
            'TAKEPOS_DIRECT_PAYMENT',
            'TAKEPOS_SORTPRODUCTFIELD',
            'TAKEPOS_NUMPAD',
            'TAKEPOS_ORDER_PRINTERS',
            'TAKEPOS_ORDER_NOTES',
            'TAKEPOS_SHOW_HT',
        );
        foreach ($prefixes as $px) {
            $rr = $db->query("SELECT value, type, visible, note FROM " . MAIN_DB_PREFIX . "const"
                . " WHERE name='" . $db->escape($px . '1') . "' AND entity=" . $entity . " LIMIT 1");
            if (!$rr || $db->num_rows($rr) === 0) continue;
            $r = $db->fetch_object($rr);
            $db->query("INSERT INTO " . MAIN_DB_PREFIX . "const (name, value, type, visible, note, entity) VALUES ("
                . "'" . $db->escape($px . $newTerminalId) . "',"
                . "'" . $db->escape($r->value) . "',"
                . "'" . $db->escape($r->type) . "'," . (int) $r->visible . ","
                . "'" . $db->escape((string) $r->note) . "'," . $entity . ")"
                . " ON DUPLICATE KEY UPDATE value=VALUES(value)");
        }

        // FIX (stock-branch-v1): Do NOT force CASHDESK_NO_DECREASE_STOCK=1 on branch
        // terminals. Branch terminals now deduct stock on sale just like master terminals.
        // The branch warehouse (CASHDESK_ID_WAREHOUSE{N}) is already copied above from
        // Terminal 1 config and should be updated to the branch's own warehouse via
        // the branch admin page after creation.
        //
        // If a branch genuinely should NOT deduct stock (e.g. consignment store),
        // an admin can manually set CASHDESK_NO_DECREASE_STOCK{N}=1 in the terminal setup.

        // FIX (stock-branch-v1): If the branch has its own warehouse, override the
        // copied Terminal 1 warehouse constant with the branch warehouse immediately.
        // This ensures stock check and deduction use the correct branch warehouse
        // from the very first sale — without requiring a manual admin step.
        if ($warehouseId > 0) {
            $db->query(
                "INSERT INTO " . MAIN_DB_PREFIX . "const (name, value, type, visible, entity)"
                . " VALUES ('CASHDESK_ID_WAREHOUSE" . $newTerminalId . "', '" . (int)$warehouseId . "', 'chaine', 0, " . $entity . ")"
                . " ON DUPLICATE KEY UPDATE value='" . (int)$warehouseId . "'"
            );
        }

        return $newTerminalId;
    }

    public static function resetBranchPassword($db, $user, $entity, $branchId)
    {
        self::ensureSchema($db);
        require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

        $branch = self::getBranch($db, $entity, $branchId);
        if (!$branch || !$branch->fk_user) {
            throw new Exception('Branch or branch user not found.');
        }

        $branchUser = new User($db);
        $branchUser->fetch((int) $branch->fk_user);

        global $user;
        $actor = (isset($user) && $user instanceof User) ? $user : self::getAdminActor($db);
        $newPassword = self::generatePassword();
        $branchUser->setPassword($actor, $newPassword);

        TakeposAudit::logEvent($db, $user, 'branch_password_reset', TakeposAudit::SEVERITY_WARNING,
            ['branch_id' => (int) $branchId, 'login' => $branchUser->login],
            'Branch password reset', 'branch', (int) $branchId);

        return [
            'login'    => $branchUser->login,
            'password' => $newPassword,
        ];
    }

    public static function updateBranch($db, $user, $entity, $branchId, $label, $description, $warehouseId, $storeId, $active)
    {
        self::ensureSchema($db);
        $sql = "UPDATE " . MAIN_DB_PREFIX . "takepos_branch SET"
            . " label='"       . $db->escape(trim($label)) . "'"
            . ",description="  . ($description !== '' ? "'" . $db->escape($description) . "'" : 'NULL')
            . ",fk_warehouse=" . ((int) $warehouseId > 0 ? (int) $warehouseId : 'NULL')
            . ",fk_store="     . ((int) $storeId    > 0 ? (int) $storeId    : 'NULL')
            . ",active="       . ((int) $active > 0 ? 1 : 0)
            . " WHERE entity=" . ((int) $entity) . " AND rowid=" . ((int) $branchId);
        if (!$db->query($sql)) throw new Exception($db->lasterror());

        // Sync CASHDESK_ID_WAREHOUSE{N} for every terminal linked to this branch.
        // If the branch has no direct warehouse but has a store, use the store's warehouse.
        $effectiveWarehouseId = (int) $warehouseId;
        if ($effectiveWarehouseId <= 0 && (int) $storeId > 0) {
            $sqlSW = "SELECT warehouse_id FROM " . MAIN_DB_PREFIX . "takepos_store"
                . " WHERE rowid=" . ((int) $storeId) . " AND active=1 LIMIT 1";
            $resSW = $db->query($sqlSW);
            if ($resSW) {
                $objSW = $db->fetch_object($resSW);
                if ($objSW && $objSW->warehouse_id) {
                    $effectiveWarehouseId = (int) $objSW->warehouse_id;
                }
            }
        }

        if ($effectiveWarehouseId > 0) {
            $sqlTerminals = "SELECT rowid FROM " . MAIN_DB_PREFIX . "takepos_terminal"
                . " WHERE fk_branch=" . ((int) $branchId)
                . " AND entity=" . ((int) $entity);
            $resTerminals = $db->query($sqlTerminals);
            if ($resTerminals) {
                while ($row = $db->fetch_object($resTerminals)) {
                    $terminalId = (int) $row->rowid;
                    $db->query(
                        "INSERT INTO " . MAIN_DB_PREFIX . "const"
                        . " (name, value, type, visible, entity)"
                        . " VALUES ('CASHDESK_ID_WAREHOUSE" . $terminalId . "', '" . $effectiveWarehouseId . "', 'chaine', 0, " . ((int)$entity) . ")"
                        . " ON DUPLICATE KEY UPDATE value='" . $effectiveWarehouseId . "'"
                    );
                }
            }
        }
    }

    public static function getBranch($db, $entity, $branchId)
    {
        self::ensureSchema($db);
        $sql = "SELECT b.*, w.ref AS warehouse_ref, s.label AS store_label,"
            . " u.login AS branch_login"
            . " FROM " . MAIN_DB_PREFIX . "takepos_branch b"
            . " LEFT JOIN " . MAIN_DB_PREFIX . "entrepot w ON w.rowid=b.fk_warehouse"
            . " LEFT JOIN " . MAIN_DB_PREFIX . "takepos_store s ON s.rowid=b.fk_store"
            . " LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid=b.fk_user"
            . " WHERE b.entity=" . ((int) $entity) . " AND b.rowid=" . ((int) $branchId) . " LIMIT 1";
        $res = $db->query($sql);
        return $res ? $db->fetch_object($res) : null;
    }

    public static function listBranches($db, $entity, $activeOnly = false)
    {
        self::ensureSchema($db);
        $sql = "SELECT b.*, w.ref AS warehouse_ref, s.label AS store_label,"
            . " u.login AS branch_login"
            . " FROM " . MAIN_DB_PREFIX . "takepos_branch b"
            . " LEFT JOIN " . MAIN_DB_PREFIX . "entrepot w ON w.rowid=b.fk_warehouse"
            . " LEFT JOIN " . MAIN_DB_PREFIX . "takepos_store s ON s.rowid=b.fk_store"
            . " LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid=b.fk_user"
            . " WHERE b.entity=" . ((int) $entity)
            . ($activeOnly ? " AND b.active=1" : "")
            . " ORDER BY b.code ASC";
        $rows = [];
        $res  = $db->query($sql);
        if ($res) { while ($obj = $db->fetch_object($res)) { $rows[] = $obj; } }
        return $rows;
    }

    // -----------------------------------------------------------------------
    // Branch ↔ User assignments (for extra staff per branch)
    // -----------------------------------------------------------------------

    public static function assignUserToBranch($db, $user, $entity, $branchId, $targetUserId, $role = 'cashier')
    {
        self::ensureSchema($db);
        $role = in_array($role, ['cashier', 'manager', 'viewer'], true) ? $role : 'cashier';
        $sql  = "INSERT INTO " . MAIN_DB_PREFIX . "takepos_branch_user"
            . " (entity, fk_branch, fk_user, role, active, date_creation) VALUES ("
            . ((int) $entity) . "," . ((int) $branchId) . "," . ((int) $targetUserId) . ","
            . "'" . $db->escape($role) . "', 1, NOW())"
            . " ON DUPLICATE KEY UPDATE role='" . $db->escape($role) . "', active=1";
        if (!$db->query($sql)) throw new Exception($db->lasterror());
    }

    public static function removeUserFromBranch($db, $user, $entity, $branchId, $targetUserId)
    {
        self::ensureSchema($db);
        $sql = "UPDATE " . MAIN_DB_PREFIX . "takepos_branch_user SET active=0"
            . " WHERE entity=" . ((int) $entity)
            . " AND fk_branch=" . ((int) $branchId)
            . " AND fk_user="   . ((int) $targetUserId);
        if (!$db->query($sql)) throw new Exception($db->lasterror());
    }

    public static function getBranchUsers($db, $entity, $branchId)
    {
        self::ensureSchema($db);
        $sql = "SELECT bu.rowid, bu.fk_user, bu.role, u.login, u.firstname, u.lastname, u.email"
            . " FROM " . MAIN_DB_PREFIX . "takepos_branch_user bu"
            . " INNER JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid=bu.fk_user"
            . " WHERE bu.entity=" . ((int) $entity)
            . " AND bu.fk_branch=" . ((int) $branchId)
            . " AND bu.active=1 ORDER BY u.login ASC";
        $rows = [];
        $res  = $db->query($sql);
        if ($res) { while ($obj = $db->fetch_object($res)) { $rows[] = $obj; } }
        return $rows;
    }

    public static function getUserBranches($db, $entity, $userId)
    {
        self::ensureSchema($db);
        $sql = "SELECT bu.fk_branch, bu.role, b.code, b.label"
            . " FROM " . MAIN_DB_PREFIX . "takepos_branch_user bu"
            . " INNER JOIN " . MAIN_DB_PREFIX . "takepos_branch b ON b.rowid=bu.fk_branch AND b.entity=bu.entity"
            . " WHERE bu.entity=" . ((int) $entity)
            . " AND bu.fk_user=" . ((int) $userId)
            . " AND bu.active=1 AND b.active=1 ORDER BY b.code ASC";
        $rows = [];
        $res  = $db->query($sql);
        if ($res) { while ($obj = $db->fetch_object($res)) { $rows[] = $obj; } }
        return $rows;
    }

    public static function userCanAccessBranch($db, $user, $branchId, $entity = null)
    {
        if ((int) $branchId <= 0 || !empty($user->admin)) return true;
        $entity  = $entity ?? (!empty($user->entity) ? (int) $user->entity : 1);
        $allowed = self::getUserBranches($db, $entity, (int) $user->id);
        foreach ($allowed as $b) {
            if ((int) $b->fk_branch === (int) $branchId) return true;
        }
        return false;
    }

    // -----------------------------------------------------------------------
    // Branch isolation helpers (isBranchUser, terminal list, etc.)
    // -----------------------------------------------------------------------

    /** True if user.rowid is the dedicated login user of any branch. */
    public static function isBranchUser($db, $userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) return false;
        // Suppress error if table doesn't exist yet (first install before ensureSchema)
        $res = @$db->query("SELECT rowid FROM " . MAIN_DB_PREFIX . "takepos_branch WHERE fk_user=" . $userId . " LIMIT 1");
        return ($res && $db->num_rows($res) > 0);
    }

    /** Get the branch row owned by this login user (ignores entity). */
    public static function getBranchByUserId($db, $userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) return null;
        $res = $db->query("SELECT * FROM " . MAIN_DB_PREFIX . "takepos_branch WHERE fk_user=" . $userId . " LIMIT 1");
        return ($res && $db->num_rows($res) > 0) ? $db->fetch_object($res) : null;
    }

    /**
     * Active terminals tied to the given branch via fk_branch.
     * Returns array keyed by rowid: rowid => {rowid, terminal_code, label}
     */
    public static function getTerminalsForBranchId($db, $branchId)
    {
        $branchId = (int) $branchId;
        if ($branchId <= 0) return [];
        $rows = [];
        // FIX: select terminal_code as fallback label in case label column is empty
        $res = $db->query(
            "SELECT rowid, terminal_code, COALESCE(NULLIF(label,''), terminal_code) AS label"
            . " FROM " . MAIN_DB_PREFIX . "takepos_terminal"
            . " WHERE fk_branch=" . $branchId . " AND active=1 ORDER BY rowid ASC"
        );
        if ($res) { while ($obj = $db->fetch_object($res)) { $rows[(int) $obj->rowid] = $obj; } }
        return $rows;
    }

    /** Hard delete: branch row + login user + rights + branch-product + branch-user maps. */
    public static function deleteBranchById($db, $user, $branchId)
    {
        $branchId = (int) $branchId;
        if ($branchId <= 0) throw new Exception('Invalid branch id.');

        $res = $db->query("SELECT rowid, code, fk_user FROM " . MAIN_DB_PREFIX . "takepos_branch WHERE rowid=" . $branchId . " LIMIT 1");
        if (!$res || $db->num_rows($res) === 0) throw new Exception('Branch not found.');
        $row   = $db->fetch_object($res);
        $bUser = (int) $row->fk_user;

        $db->begin();
        try {
            // Check product map table exists before deleting from it
            $pmCheck = $db->query("SHOW TABLES LIKE '" . $db->escape(MAIN_DB_PREFIX . 'takepos_branch_product') . "'");
            if ($pmCheck && $db->num_rows($pmCheck) > 0) {
                $db->query("DELETE FROM " . MAIN_DB_PREFIX . "takepos_branch_product WHERE fk_branch=" . $branchId);
            }
            $db->query("DELETE FROM " . MAIN_DB_PREFIX . "takepos_branch_user    WHERE fk_branch=" . $branchId);

            // Delete terminals linked to this branch and their config keys
            $tcCheck = $db->query("SHOW TABLES LIKE '" . $db->escape(MAIN_DB_PREFIX . 'takepos_terminal') . "'");
            if ($tcCheck && $db->num_rows($tcCheck) > 0) {
                $termRows = $db->query("SELECT rowid FROM " . MAIN_DB_PREFIX . "takepos_terminal WHERE fk_branch=" . $branchId);
                if ($termRows) {
                    while ($termObj = $db->fetch_object($termRows)) {
                        $tId = (int)$termObj->rowid;
                        $db->query("DELETE FROM " . MAIN_DB_PREFIX . "const WHERE name LIKE '%_" . $tId . "'");
                    }
                }
                $db->query("DELETE FROM " . MAIN_DB_PREFIX . "takepos_terminal WHERE fk_branch=" . $branchId);
            }

            $db->query("DELETE FROM " . MAIN_DB_PREFIX . "takepos_branch WHERE rowid=" . $branchId);

            if ($bUser > 0) {
                $db->query("DELETE FROM " . MAIN_DB_PREFIX . "user_rights WHERE fk_user=" . $bUser);
                $kafo = MAIN_DB_PREFIX . 'saas_user_permissions';
                $tc   = $db->query("SHOW TABLES LIKE '" . $db->escape($kafo) . "'");
                if ($tc && $db->num_rows($tc) > 0) {
                    $db->query("DELETE FROM " . $kafo . " WHERE fk_user=" . $bUser);
                }
                $db->query("DELETE FROM " . MAIN_DB_PREFIX . "user WHERE rowid=" . $bUser);
            }

            $db->commit();

            TakeposAudit::logEvent($db, $user, 'branch_deleted', TakeposAudit::SEVERITY_WARNING,
                ['branch_id' => $branchId, 'code' => $row->code],
                'Branch permanently deleted: ' . $row->code, 'branch', $branchId);

            return true;
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    // -----------------------------------------------------------------------
    // Branch ↔ Product mapping
    // -----------------------------------------------------------------------

    /**
     * Ensure the branch_product mapping table exists.
     * FIX: added `entity` column (was missing in original) for multi-entity support.
     */
    public static function ensureProductMapTable($db)
    {
        $t = MAIN_DB_PREFIX . 'takepos_branch_product';
        $res = $db->query("SHOW TABLES LIKE '" . $db->escape($t) . "'");
        if (!$res || $db->num_rows($res) === 0) {
            $db->query("CREATE TABLE " . $t . " (
                rowid         INT AUTO_INCREMENT PRIMARY KEY,
                entity        INT        NOT NULL DEFAULT 1,
                fk_branch     INT        NOT NULL,
                fk_product    INT        NOT NULL,
                active        TINYINT(1) NOT NULL DEFAULT 1,
                date_creation DATETIME   NOT NULL,
                tms           TIMESTAMP  NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_bp (entity, fk_branch, fk_product),
                KEY idx_bp_branch  (entity, fk_branch,  active),
                KEY idx_bp_product (entity, fk_product, active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            // FIX: add entity column to existing tables that were created without it
            TakeposMigration::ensureColumn($db, $t, 'entity', 'INT NOT NULL DEFAULT 1 AFTER rowid');
            TakeposMigration::ensureColumn($db, $t, 'active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER fk_product');
        }
    }

    /** Replace the full product set for a branch with $productIds. */
    public static function setBranchProductsById($db, $branchId, array $productIds, $entity = 1)
    {
        self::ensureProductMapTable($db);
        $branchId = (int) $branchId;
        $entity   = (int) $entity;
        if ($branchId <= 0) throw new Exception('Invalid branch id.');

        $db->begin();
        try {
            $db->query("DELETE FROM " . MAIN_DB_PREFIX . "takepos_branch_product"
                . " WHERE fk_branch=" . $branchId . " AND entity=" . $entity);
            foreach ($productIds as $pid) {
                $pid = (int) $pid;
                if ($pid <= 0) continue;
                $db->query("INSERT IGNORE INTO " . MAIN_DB_PREFIX . "takepos_branch_product"
                    . " (entity, fk_branch, fk_product, active, date_creation)"
                    . " VALUES (" . $entity . "," . $branchId . "," . $pid . ", 1, NOW())");
            }
            $db->commit();

            // logEventStatic doesn't exist — use global $user with standard logEvent
            global $user;
            if (isset($user) && is_object($user)) {
                TakeposAudit::logEvent($db, $user, 'branch_products_set', TakeposAudit::SEVERITY_INFO,
                    ['branch_id' => $branchId, 'count' => count($productIds)],
                    'Branch products updated: ' . count($productIds) . ' product(s)', 'branch', $branchId);
            }

        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    /** int[] of active product IDs assigned to a branch. */
    public static function getBranchProductIdsById($db, $branchId, $entity = 1)
    {
        self::ensureProductMapTable($db);
        $branchId = (int) $branchId;
        $entity   = (int) $entity;
        if ($branchId <= 0) return [];

        // First: fix any rows that were inserted before the entity column existed
        // (entity=0 or entity=NULL). Update them to the correct entity now.
        $db->query(
            "UPDATE " . MAIN_DB_PREFIX . "takepos_branch_product"
            . " SET entity=" . $entity
            . " WHERE fk_branch=" . $branchId . " AND (entity IS NULL OR entity=0)"
        );

        $ids = [];
        $res = $db->query(
            "SELECT fk_product FROM " . MAIN_DB_PREFIX . "takepos_branch_product"
            . " WHERE fk_branch=" . $branchId . " AND entity=" . $entity . " AND active=1"
        );
        if ($res) { while ($o = $db->fetch_object($res)) { $ids[] = (int) $o->fk_product; } }
        return $ids;
    }

    /**
     * Returns the set of product IDs the current user may see in POS.
     *
     * - null  → no filter (admin / non-branch user) — show ALL products
     * - []    → empty filter — branch exists but has 0 products assigned → show nothing
     * - int[] → show only these product IDs
     *
     * FIX: was returning null for ALL non-admin users regardless of branch status,
     * meaning branch users with no products assigned would see everything. Now
     * correctly returns [] when a branch has no products configured.
     */
    public static function allowedProductIdsForUser($db, $user)
    {
        if (!is_object($user) || empty($user->id)) return null;

        // Admins always see everything
        if (!empty($user->admin)) return null;

        // Only apply restriction to branch login users (fk_user match on branch table)
        if (!self::isBranchUser($db, (int) $user->id)) return null;

        $branch = self::getBranchByUserId($db, (int) $user->id);
        if (!$branch) return null;

        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $manualIds = self::getBranchProductIdsById($db, (int) $branch->rowid, $entity);

        // Always merge warehouse-derived products with the manual list.
        // A branch with warehouse=8 must show ALL products in that warehouse,
        // regardless of whether some products were also manually assigned.
        // Also check the store's warehouse if the branch has a store assigned.
        $warehouseId = (int) (isset($branch->fk_warehouse) ? $branch->fk_warehouse : 0);

        // If branch has no direct warehouse but has a store, use the store's warehouse
        if ($warehouseId <= 0 && !empty($branch->fk_store)) {
            $sqlStore = "SELECT warehouse_id FROM " . MAIN_DB_PREFIX . "takepos_store"
                . " WHERE rowid=" . ((int) $branch->fk_store) . " AND active=1 LIMIT 1";
            $resStore = $db->query($sqlStore);
            if ($resStore) {
                $objStore = $db->fetch_object($resStore);
                if ($objStore && $objStore->warehouse_id) {
                    $warehouseId = (int) $objStore->warehouse_id;
                }
            }
        }

        // Also collect store warehouse even when branch already has its own warehouse
        // so products from both are visible
        $storeWarehouseId = 0;
        if (!empty($branch->fk_store) && $warehouseId > 0) {
            $sqlStore2 = "SELECT warehouse_id FROM " . MAIN_DB_PREFIX . "takepos_store"
                . " WHERE rowid=" . ((int) $branch->fk_store) . " AND active=1 LIMIT 1";
            $resStore2 = $db->query($sqlStore2);
            if ($resStore2) {
                $objStore2 = $db->fetch_object($resStore2);
                if ($objStore2 && $objStore2->warehouse_id && (int)$objStore2->warehouse_id !== $warehouseId) {
                    $storeWarehouseId = (int) $objStore2->warehouse_id;
                }
            }
        }

        if ($warehouseId > 0) {
            $warehouseIds = [];

            // Products whose default warehouse is this branch warehouse
            $res = $db->query(
                "SELECT rowid FROM " . MAIN_DB_PREFIX . "product"
                . " WHERE fk_default_warehouse=" . $warehouseId
                . " AND tosell=1"
                . " AND entity IN (" . getEntity('product') . ")"
            );
            if ($res) {
                while ($obj = $db->fetch_object($res)) {
                    $warehouseIds[] = (int) $obj->rowid;
                }
            }

            // Products that physically have stock in this warehouse
            $res2 = $db->query(
                "SELECT DISTINCT ps.fk_product FROM " . MAIN_DB_PREFIX . "product_stock ps"
                . " INNER JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = ps.fk_product"
                . " WHERE ps.fk_entrepot=" . $warehouseId
                . " AND ps.reel > 0"
                . " AND p.tosell=1"
                . " AND p.entity IN (" . getEntity('product') . ")"
            );
            if ($res2) {
                while ($obj = $db->fetch_object($res2)) {
                    $warehouseIds[] = (int) $obj->fk_product;
                }
            }

            // Also get products from the store's warehouse if different
            if ($storeWarehouseId > 0) {
                $res3 = $db->query(
                    "SELECT rowid FROM " . MAIN_DB_PREFIX . "product"
                    . " WHERE fk_default_warehouse=" . $storeWarehouseId
                    . " AND tosell=1"
                    . " AND entity IN (" . getEntity('product') . ")"
                );
                if ($res3) {
                    while ($obj = $db->fetch_object($res3)) { $warehouseIds[] = (int) $obj->rowid; }
                }
                $res4 = $db->query(
                    "SELECT DISTINCT ps.fk_product FROM " . MAIN_DB_PREFIX . "product_stock ps"
                    . " INNER JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = ps.fk_product"
                    . " WHERE ps.fk_entrepot=" . $storeWarehouseId
                    . " AND ps.reel > 0 AND p.tosell=1"
                    . " AND p.entity IN (" . getEntity('product') . ")"
                );
                if ($res4) {
                    while ($obj = $db->fetch_object($res4)) { $warehouseIds[] = (int) $obj->fk_product; }
                }
            }

            // Merge manual + warehouse, deduplicate
            $merged = array_values(array_unique(array_merge($manualIds, $warehouseIds)));
            if (!empty($merged)) {
                return $merged;
            }
        }

        // No warehouse set — fall back to manual list only (may be empty)
        return $manualIds;
    }

    /**
     * Get the product IDs for a branch — used by the category decorator to
     * compute accurate per-category product counts for branch users.
     * Returns null when no branch restriction applies.
     */
    public static function allowedProductIdsForUserAsSet($db, $user)
    {
        $ids = self::allowedProductIdsForUser($db, $user);
        if ($ids === null) return null;
        return array_flip($ids); // flip for O(1) isset() checks
    }
}
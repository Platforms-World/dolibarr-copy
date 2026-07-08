<?php
/*
 * TakePOS API v1 - Shared POS context helpers
 *
 * Builds the "what can this user work with" dataset (stores, terminals,
 * warehouses) that is returned by auth_login.php and set_terminal.php.
 *
 * Drop this file into takepos/api/v1/ alongside the other helpers.
 */
if (!defined('TAKEPOS_API_V1_CONTEXT_INCLUDED')) {
    define('TAKEPOS_API_V1_CONTEXT_INCLUDED', 1);

    require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposStoreService.class.php';
    require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposTerminalService.class.php';
    require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposShiftService.class.php';

    /**
     * Resolve the store ids a user is allowed to see.
     *
     * @return array{restricted:bool, ids:int[]}  ids is only meaningful when restricted=true.
     */
    function takeposApiResolveAccessibleStoreIds($db, $entity, $user)
    {
        $restricted = (TakeposStoreService::enforceStoreRestrictionEnabled($db) && empty($user->admin));
        $ids = array();
        if ($restricted && !empty($user->id)) {
            $ids = TakeposStoreService::getUserStoreIds($db, (int) $entity, (int) $user->id);
        }

        return array('restricted' => $restricted, 'ids' => $ids);
    }

    /**
     * Format a raw terminal row (same shape as terminals.php) into an API array.
     */
    function takeposApiContextFormatTerminal($row)
    {
        $metadata = json_decode((string) $row->metadata_json, true);
        if (!is_array($metadata)) {
            $metadata = array();
        }

        // Warehouse resolution, mirroring what the web POS actually uses:
        //   1. the per-terminal global constant CASHDESK_ID_WAREHOUSE{code}
        //      (set in admin/terminal.php — this is what the POS top bar shows),
        //   2. the terminal's own metadata_json.warehouse_id,
        //   3. the terminal's store warehouse_id.
        $warehouseId = null;
        $cfgWarehouseId = takeposApiTerminalConfiguredWarehouseId($row->terminal_code);
        if ($cfgWarehouseId > 0) {
            $warehouseId = $cfgWarehouseId;
        } elseif (!empty($metadata['warehouse_id'])) {
            $warehouseId = (int) $metadata['warehouse_id'];
        } elseif (!empty($row->warehouse_id)) {
            $warehouseId = (int) $row->warehouse_id;
        }

        return array(
            'id' => (int) $row->rowid,
            'terminal_code' => (string) $row->terminal_code,
            'label' => (string) $row->label,
            'store_id' => (!empty($row->fk_store) ? (int) $row->fk_store : null),
            'store_label' => (!empty($row->store_label) ? (string) $row->store_label : null),
            'status' => (int) $row->active,
            'default_customer_id' => (!empty($metadata['default_customer_id']) ? (int) $metadata['default_customer_id'] : null),
            'warehouse_id' => $warehouseId,
            'warehouse_ref' => null,   // filled in by takeposApiBuildPosContext
            'warehouse_label' => null, // filled in by takeposApiBuildPosContext
            'last_seen' => (!empty($row->last_seen) ? (string) $row->last_seen : null),
        );
    }

    /**
     * The warehouse a terminal is bound to in the classic TakePOS way: the
     * Dolibarr global constant CASHDESK_ID_WAREHOUSE{terminalcode}. Returns 0
     * when not configured.
     */
    function takeposApiTerminalConfiguredWarehouseId($terminalCode)
    {
        $code = trim((string) $terminalCode);
        if ($code === '') {
            return 0;
        }
        $wid = (int) getDolGlobalInt('CASHDESK_ID_WAREHOUSE' . $code);
        return ($wid > 0 ? $wid : 0);
    }

    /**
     * List terminals for an entity, honoring the user's store restriction.
     *
     * @return array[]  Formatted terminal arrays.
     */
    function takeposApiListAccessibleTerminals($db, $entity, $access)
    {
        TakeposTerminalService::ensureSchema($db);

        // Resolve the branch the user belongs to (owner or staff member).
        // Non-admin branch users only see terminals in their branch.
        // Admins see all terminals.
        $branchId = 0;
        $userId   = (!empty($access['user_id']) ? (int) $access['user_id'] : 0);
        $isAdmin  = (!empty($access['is_admin']));

        if ($userId > 0 && !$isAdmin) {
            $branchTable     = MAIN_DB_PREFIX . 'takepos_branch';
            $branchUserTable = MAIN_DB_PREFIX . 'takepos_branch_user';

            $chk = @$db->query("SHOW TABLES LIKE '" . $db->escape($branchTable) . "'");
            if ($chk && $db->num_rows($chk) > 0) {
                // 1. Branch owner (matches web POS: fk_user = user_id, no active filter)
                $res = $db->query("SELECT rowid FROM " . $branchTable
                    . " WHERE fk_user = " . $userId . " LIMIT 1");
                if ($res && ($o = $db->fetch_object($res))) {
                    $branchId = (int) $o->rowid;
                }

                // 2. Branch staff member
                if ($branchId === 0) {
                    $chk2 = @$db->query("SHOW TABLES LIKE '" . $db->escape($branchUserTable) . "'");
                    if ($chk2 && $db->num_rows($chk2) > 0) {
                        $res2 = $db->query("SELECT fk_branch FROM " . $branchUserTable
                            . " WHERE fk_user = " . $userId . " AND active = 1 LIMIT 1");
                        if ($res2 && ($o2 = $db->fetch_object($res2))) {
                            $branchId = (int) $o2->fk_branch;
                        }
                    }
                }
            }
        }

        $sql = 'SELECT t.rowid, t.terminal_code, t.label, t.fk_store, t.active, t.last_seen, t.metadata_json, s.label AS store_label, s.warehouse_id';
        $sql .= ' FROM ' . TakeposTerminalService::tableTerminal() . ' t';
        $sql .= ' LEFT JOIN ' . TakeposStoreService::tableStore() . ' s ON s.rowid = t.fk_store AND s.entity = t.entity';
        $sql .= ' WHERE t.entity = ' . ((int) $entity);

        if ($branchId > 0) {
            // Branch user: only terminals in their branch
            $sql .= ' AND t.fk_branch = ' . $branchId;
        } else {
            // Admin or non-branch user: only master terminals (fk_branch IS NULL)
            // This matches the web POS which shows only TAKEPOS_NUM_TERMINALS
            // master terminals to admin users, never branch terminals.
            $sql .= ' AND (t.fk_branch IS NULL OR t.fk_branch = 0)';
        }

        $sql .= ' ORDER BY t.terminal_code ASC, t.rowid ASC';

        $rows = array();
        $resql = $db->query($sql);
        if ($resql) {
            while ($row = $db->fetch_object($resql)) {
                if (!empty($access['restricted']) && !empty($row->fk_store)
                    && !in_array((int) $row->fk_store, $access['ids'], true)) {
                    continue;
                }
                $rows[] = takeposApiContextFormatTerminal($row);
            }
        }

        return $rows;
    }

    /**
     * Fetch warehouse (entrepot) records for a set of ids.
     *
     * @param int[] $warehouseIds
     * @return array[]  Each: id, ref, label.
     */
    function takeposApiFetchWarehouses($db, $warehouseIds)
    {
        $clean = array();
        foreach ((array) $warehouseIds as $wid) {
            $wid = (int) $wid;
            if ($wid > 0) {
                $clean[$wid] = $wid;
            }
        }
        if (empty($clean)) {
            return array();
        }

        $sql = 'SELECT rowid, ref, label FROM ' . MAIN_DB_PREFIX . 'entrepot';
        $sql .= ' WHERE entity IN (' . getEntity('stock') . ')';
        $sql .= ' AND rowid IN (' . implode(',', array_map('intval', $clean)) . ')';
        $sql .= ' ORDER BY ref ASC';

        $rows = array();
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $rows[] = array(
                    'id' => (int) $obj->rowid,
                    'ref' => (string) $obj->ref,
                    'label' => (string) $obj->label,
                );
            }
        }

        return $rows;
    }

    /**
     * Resolve the warehouse ids a user is connected to (not just any warehouse
     * in the entity). A user is linked to warehouses through:
     *   1. the stores assigned to them (takepos_user_store -> store.warehouse_id),
     *   2. a branch they own       (takepos_branch.fk_user  -> fk_warehouse),
     *   3. branches they staff     (takepos_branch_user      -> branch.fk_warehouse).
     *
     * @return int[] distinct warehouse ids
     */
    function takeposApiResolveUserWarehouseIds($db, $entity, $user)
    {
        $entity = (int) $entity;
        $userId = (int) (isset($user->id) ? $user->id : 0);
        $ids = array();
        if ($userId <= 0) {
            return $ids;
        }

        // 1) Warehouses behind the user's assigned stores.
        $sql = 'SELECT DISTINCT s.warehouse_id'
            . ' FROM ' . TakeposStoreService::tableUserStore() . ' us'
            . ' INNER JOIN ' . TakeposStoreService::tableStore() . ' s ON s.rowid = us.fk_store AND s.entity = us.entity'
            . ' WHERE us.entity = ' . $entity . ' AND us.fk_user = ' . $userId
            . ' AND us.active = 1 AND s.active = 1 AND s.warehouse_id IS NOT NULL AND s.warehouse_id > 0';
        $res = $db->query($sql);
        if ($res) {
            while ($o = $db->fetch_object($res)) {
                if (!empty($o->warehouse_id)) {
                    $ids[(int) $o->warehouse_id] = (int) $o->warehouse_id;
                }
            }
        }

        // 2) + 3) Warehouses behind the user's branch(es). Branch login users are
        // treated as global in this module (getBranchByUserId ignores entity), so
        // we don't over-constrain by entity here. Tables may not exist yet.
        $branchTable = MAIN_DB_PREFIX . 'takepos_branch';
        $chk = @$db->query("SHOW TABLES LIKE '" . $db->escape($branchTable) . "'");
        if ($chk && $db->num_rows($chk) > 0) {
            // Owned branch
            $sql = 'SELECT DISTINCT fk_warehouse FROM ' . $branchTable
                . ' WHERE fk_user = ' . $userId
                . ' AND active = 1 AND fk_warehouse IS NOT NULL AND fk_warehouse > 0';
            $res = @$db->query($sql);
            if ($res) {
                while ($o = $db->fetch_object($res)) {
                    if (!empty($o->fk_warehouse)) {
                        $ids[(int) $o->fk_warehouse] = (int) $o->fk_warehouse;
                    }
                }
            }

            // Branches the user is staff of
            $branchUserTable = MAIN_DB_PREFIX . 'takepos_branch_user';
            $chk2 = @$db->query("SHOW TABLES LIKE '" . $db->escape($branchUserTable) . "'");
            if ($chk2 && $db->num_rows($chk2) > 0) {
                $sql = 'SELECT DISTINCT b.fk_warehouse FROM ' . $branchUserTable . ' bu'
                    . ' INNER JOIN ' . $branchTable . ' b ON b.rowid = bu.fk_branch AND b.entity = bu.entity'
                    . ' WHERE bu.fk_user = ' . $userId
                    . ' AND bu.active = 1 AND b.active = 1 AND b.fk_warehouse IS NOT NULL AND b.fk_warehouse > 0';
                $res = @$db->query($sql);
                if ($res) {
                    while ($o = $db->fetch_object($res)) {
                        if (!empty($o->fk_warehouse)) {
                            $ids[(int) $o->fk_warehouse] = (int) $o->fk_warehouse;
                        }
                    }
                }
            }
        }

        return array_values($ids);
    }

    /**
     * The user's currently open shift (the terminal they are "on" right now),
     * as a full shift row, or null when no shift is open for them.
     */
    function takeposApiResolveUserActiveShift($db, $entity, $user)
    {
        $userId = (int) (isset($user->id) ? $user->id : 0);
        if ($userId <= 0) {
            return null;
        }
        $active = TakeposShiftService::getActiveShiftForCashier($db, (int) $entity, $userId);
        if (!$active) {
            return null;
        }
        $full = TakeposShiftService::getShiftById($db, (int) $entity, (int) $active->rowid);
        return ($full ? $full : null);
    }

    /**
     * Compact shift payload for the login/context response, focused on which
     * terminal the open shift is running on.
     */
    function takeposApiContextShiftPayload($row)
    {
        return array(
            'id' => (int) $row->rowid,
            'shift_ref' => (!empty($row->shift_ref) ? (string) $row->shift_ref : null),
            'status' => (string) $row->status,
            'terminal_id' => (int) $row->fk_terminal,
            'terminal_code' => (!empty($row->terminal_code) ? (string) $row->terminal_code : null),
            'terminal_label' => (!empty($row->terminal_label) ? (string) $row->terminal_label : null),
            'store_id' => (!empty($row->fk_store) ? (int) $row->fk_store : null),
            'store_label' => (!empty($row->store_label) ? (string) $row->store_label : null),
            'opening_amount' => (float) price2num($row->opening_float, 'MT'),
            'opened_at' => (!empty($row->date_open) ? (string) $row->date_open : null),
        );
    }

    /**
     * Unfinished POS sales (draft carts) the user can act on: every TakePOS draft
     * invoice on a terminal the user can reach (all of them for admins / when store
     * restriction is off). Author info is included so the client can tell who
     * started each cart, and parked ("held") carts are flagged. Not author-scoped,
     * because a register is typically shared.
     *
     * @param string[] $accessibleTerminalCodes terminal codes the user may use
     * @param bool      $restricted              whether to limit to those codes
     * @return array[] compact cart rows
     */
    function takeposApiResolveUserOpenCarts($db, $entity, $user, $accessibleTerminalCodes = array(), $restricted = false, $limit = 50)
    {
        $entity = (int) $entity;
        $limit = (int) $limit;
        if ($limit <= 0) {
            $limit = 50;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $facture = MAIN_DB_PREFIX . 'facture';
        $facturedet = MAIN_DB_PREFIX . 'facturedet';
        $userTable = MAIN_DB_PREFIX . 'user';

        // Identify TakePOS draft carts the same way the web POS does: a POS draft
        // either carries module_source='takepos' or has the (PROV-POS<terminal>-…)
        // reference. Scope by the terminals the user can reach (not by author), so
        // any open cart on a shared register is returned.
        //
        // FIX: Use getEntity('invoice') instead of a hard entity int so that
        // SuperAdmin users (whose entity=0 gets remapped to 1 during token creation)
        // still see invoices that live in other entities they have access to.
        // Falls back to the hard entity filter when getEntity() is not available
        // (e.g. very old Dolibarr versions).
        if (function_exists('getEntity')) {
            $entityClause = ' f.entity IN (' . getEntity('invoice') . ')';
        } else {
            $entityClause = ' f.entity = ' . $entity;
        }

        // Exclude restaurant/bar table carts (place > 0): refs like (PROV-POS2-0-1).
        // The web POS in non-restaurant mode only uses place=0, ref ending in '-0)'.
        $where = ' WHERE ' . $entityClause
            . ' AND f.fk_statut = 0'
            . " AND (f.module_source = 'takepos' OR f.ref LIKE '(PROV-POS%')"
            . " AND f.ref LIKE '%-0)'";

        if ($restricted) {
            $codes = array();
            foreach ((array) $accessibleTerminalCodes as $code) {
                $code = trim((string) $code);
                if ($code !== '') {
                    $codes[] = "'" . $db->escape($code) . "'";
                }
            }
            if (empty($codes)) {
                return array(); // restricted user with no accessible terminals
            }
            $where .= ' AND f.pos_source IN (' . implode(',', $codes) . ')';
        }

        // Step 1: fetch invoices only — no joins, no subqueries, maximum compatibility.
        $sql = 'SELECT f.rowid, f.ref, f.pos_source, f.fk_soc, f.total_ht, f.total_tva, f.total_ttc,'
            . ' f.datec, f.fk_user_author'
            . ' FROM ' . $facture . ' f'
            . $where
            . ' ORDER BY f.rowid DESC'
            . ' LIMIT ' . $limit;

        $carts = array();
        $ids = array();
        $resql = $db->query($sql);
        if ($resql) {
            while ($o = $db->fetch_object($resql)) {
                $id = (int) $o->rowid;
                $ids[] = $id;

                $termCode = (!empty($o->pos_source) ? (string) $o->pos_source : null);
                if ($termCode === null && !empty($o->ref) && preg_match('/\(PROV-POS([^-\)]+)-/', (string) $o->ref, $m)) {
                    $termCode = (string) $m[1];
                }

                $carts[$id] = array(
                    'id' => $id,
                    'ref' => (string) $o->ref,
                    'terminal_code' => $termCode,
                    'terminal_id' => null,
                    'terminal_label' => null,
                    'thirdparty_id' => (!empty($o->fk_soc) ? (int) $o->fk_soc : null),
                    'author_id' => (!empty($o->fk_user_author) ? (int) $o->fk_user_author : null),
                    'author_login' => null,
                    'items_count' => 0,
                    'total_ht' => (float) price2num($o->total_ht, 'MT'),
                    'total_tva' => (float) price2num($o->total_tva, 'MT'),
                    'total_ttc' => (float) price2num($o->total_ttc, 'MT'),
                    'held' => false,
                    'date_creation' => (!empty($o->datec) ? (string) $o->datec : null),
                );
            }
        }

        // Step 2: enrich with author login and items_count in separate simple queries.
        if (!empty($ids)) {
            $inIds = implode(',', array_map('intval', $ids));

            // Author logins
            $sqlAuthors = 'SELECT u.rowid, u.login FROM ' . $userTable . ' u'
                . ' WHERE u.rowid IN ('
                . ' SELECT DISTINCT fk_user_author FROM ' . $facture
                . ' WHERE rowid IN (' . $inIds . ') AND fk_user_author IS NOT NULL AND fk_user_author > 0'
                . ')';
            $resAuthors = $db->query($sqlAuthors);
            $authorMap = array();
            if ($resAuthors) {
                while ($a = $db->fetch_object($resAuthors)) {
                    $authorMap[(int) $a->rowid] = (string) $a->login;
                }
            }
            foreach ($carts as $cid => $cart) {
                if (!empty($cart['author_id']) && isset($authorMap[(int) $cart['author_id']])) {
                    $carts[$cid]['author_login'] = $authorMap[(int) $cart['author_id']];
                }
            }

            // Items count per invoice
            $sqlCounts = 'SELECT fk_facture, COUNT(rowid) AS cnt FROM ' . $facturedet
                . ' WHERE fk_facture IN (' . $inIds . ')'
                . ' GROUP BY fk_facture';
            $resCounts = $db->query($sqlCounts);
            if ($resCounts) {
                while ($c = $db->fetch_object($resCounts)) {
                    $fid = (int) $c->fk_facture;
                    if (isset($carts[$fid])) {
                        $carts[$fid]['items_count'] = (int) $c->cnt;
                    }
                }
            }
        }

        // Flag the parked / held carts (if the held-sale table exists).
        if (!empty($ids)) {
            $heldTable = MAIN_DB_PREFIX . 'takepos_held_sale';
            $chk = @$db->query("SHOW TABLES LIKE '" . $db->escape($heldTable) . "'");
            if ($chk && $db->num_rows($chk) > 0) {
                $sqlHeld = 'SELECT DISTINCT fk_invoice FROM ' . $heldTable
                    . ' WHERE entity = ' . $entity . ' AND status = 1'
                    . ' AND fk_invoice IN (' . implode(',', array_map('intval', $ids)) . ')';
                $resHeld = @$db->query($sqlHeld);
                if ($resHeld) {
                    while ($h = $db->fetch_object($resHeld)) {
                        $fid = (int) $h->fk_invoice;
                        if (isset($carts[$fid])) {
                            $carts[$fid]['held'] = true;
                        }
                    }
                }
            }
        }

        return array_values($carts);
    }

    /**
     * Build the full POS context for a user: accessible stores, terminals and
     * warehouses, plus sensible defaults.
     *
     * @return array
     */
    function takeposApiBuildPosContext($db, $entity, $user, $boundTerminalId = 0)
    {
        $entity = (int) $entity;
        TakeposStoreService::ensureSchema($db);

        $access = takeposApiResolveAccessibleStoreIds($db, $entity, $user);
        // Enrich access array with user identity for branch-terminal filtering
        $access['user_id']  = (!empty($user->id) ? (int) $user->id : 0);
        $access['is_admin'] = (!empty($user->admin));

        // Stores
        $stores = array();
        $warehouseIds = array();
        foreach (TakeposStoreService::listStores($db, $entity, false) as $store) {
            if (!empty($access['restricted']) && !in_array((int) $store->rowid, $access['ids'], true)) {
                continue;
            }
            $stores[] = array(
                'id' => (int) $store->rowid,
                'code' => (string) $store->code,
                'label' => (string) $store->label,
                'description' => (isset($store->description) ? (string) $store->description : ''),
                'warehouse_id' => (!empty($store->warehouse_id) ? (int) $store->warehouse_id : null),
                'status' => (int) $store->active,
            );
            if (!empty($store->warehouse_id)) {
                $warehouseIds[] = (int) $store->warehouse_id;
            }
        }

        // Terminals
        $terminals = takeposApiListAccessibleTerminals($db, $entity, $access);
        foreach ($terminals as $terminal) {
            if (!empty($terminal['warehouse_id'])) {
                $warehouseIds[] = (int) $terminal['warehouse_id'];
            }
        }

        // Warehouses connected specifically to this user, via assigned stores /
        // branch links. The user's accessible terminals' warehouses are folded
        // in below (for admins with no store/branch link, the terminal warehouse
        // — CASHDESK_ID_WAREHOUSE{code} — is the one the POS shows).
        $userWarehouseIds = takeposApiResolveUserWarehouseIds($db, $entity, $user);
        foreach ($terminals as $terminal) {
            if (!empty($terminal['warehouse_id'])) {
                $userWarehouseIds[] = (int) $terminal['warehouse_id'];
            }
        }
        $userWarehouseIds = array_values(array_unique(array_map('intval', $userWarehouseIds)));
        $warehouseIds = array_merge($warehouseIds, $userWarehouseIds);

        // Resolve all warehouses once and index them so we can label terminals
        // and reuse the objects everywhere.
        $warehouses = takeposApiFetchWarehouses($db, $warehouseIds);
        $warehouseById = array();
        foreach ($warehouses as $wh) {
            $warehouseById[(int) $wh['id']] = $wh;
        }

        // Attach the warehouse ref/label onto each terminal.
        foreach ($terminals as $i => $terminal) {
            $wid = (int) (isset($terminal['warehouse_id']) ? $terminal['warehouse_id'] : 0);
            if ($wid > 0 && isset($warehouseById[$wid])) {
                $terminals[$i]['warehouse_ref'] = $warehouseById[$wid]['ref'];
                $terminals[$i]['warehouse_label'] = $warehouseById[$wid]['label'];
            }
        }

        // user_warehouses = warehouse objects for the ids connected to the user.
        $userWarehouses = array();
        foreach ($userWarehouseIds as $wid) {
            if (isset($warehouseById[$wid])) {
                $userWarehouses[] = $warehouseById[$wid];
            }
        }

        // Defaults: first active terminal/store, else first available
        $defaultTerminalId = null;
        foreach ($terminals as $terminal) {
            if (!empty($terminal['status'])) {
                $defaultTerminalId = (int) $terminal['id'];
                break;
            }
        }
        if ($defaultTerminalId === null && !empty($terminals)) {
            $defaultTerminalId = (int) $terminals[0]['id'];
        }

        $defaultStoreId = null;
        foreach ($stores as $store) {
            if (!empty($store['status'])) {
                $defaultStoreId = (int) $store['id'];
                break;
            }
        }
        if ($defaultStoreId === null && !empty($stores)) {
            $defaultStoreId = (int) $stores[0]['id'];
        }

        // The terminal the user is currently "on" — i.e. their open shift, if any.
        // If a terminal was explicitly bound (via set_terminal), resolve the shift
        // for that terminal. Otherwise fall back to the user's active shift.
        $boundTerminalId = (int) $boundTerminalId;

        // Also check global $auth (set by _auth.php on authenticated endpoints)
        // so that repeated calls to any context-returning endpoint pick up the
        // terminal that was bound via set_terminal.
        if ($boundTerminalId <= 0 && isset($GLOBALS['_takepos_auth']['token']['terminal_id'])) {
            $boundTerminalId = (int) $GLOBALS['_takepos_auth']['token']['terminal_id'];
        }

        if ($boundTerminalId > 0) {
            // Only get the shift for the bound terminal — never from another terminal
            $activeShiftRow = TakeposShiftService::getActiveShiftForTerminal($db, $entity, $boundTerminalId);
        } else {
            $activeShiftRow = takeposApiResolveUserActiveShift($db, $entity, $user);
        }
        $activeShift = ($activeShiftRow ? takeposApiContextShiftPayload($activeShiftRow) : null);

        // If a shift is open, prefer its terminal/store as the default selection
        // so the client lands on the register the user is already working on.
        if ($activeShift) {
            if (!empty($activeShift['terminal_id'])) {
                $defaultTerminalId = (int) $activeShift['terminal_id'];
            }
            if (!empty($activeShift['store_id'])) {
                $defaultStoreId = (int) $activeShift['store_id'];
            }
        }

        // The user's unfinished sales (draft carts), with their terminal resolved
        // from the terminal list and parked ("held") carts flagged.
        $terminalByCode = array();
        $accessibleCodes = array();
        foreach ($terminals as $terminal) {
            if (!empty($terminal['terminal_code'])) {
                $terminalByCode[(string) $terminal['terminal_code']] = $terminal;
                $accessibleCodes[] = (string) $terminal['terminal_code'];
            }
        }
        // Filter open invoices to the bound/active terminal only.
        // Priority: explicitly bound terminal > active shift terminal > all accessible.
        $cartTerminalCodes = $accessibleCodes;
        $cartRestricted    = !empty($access['restricted']);
        if ($boundTerminalId > 0) {
            // Terminal explicitly set — look up its terminal_code directly
            $termRes = $db->query("SELECT terminal_code FROM " . TakeposTerminalService::tableTerminal()
                . " WHERE rowid = " . $boundTerminalId . " AND entity = " . $entity . " LIMIT 1");
            if ($termRes && ($termObj = $db->fetch_object($termRes)) && !empty($termObj->terminal_code)) {
                $cartTerminalCodes = array((string) $termObj->terminal_code);
                $cartRestricted    = true;
            }
        } elseif ($activeShift && !empty($activeShift['terminal_code'])) {
            $cartTerminalCodes = array((string) $activeShift['terminal_code']);
            $cartRestricted    = true;
        }

        $openInvoices = takeposApiResolveUserOpenCarts(
            $db,
            $entity,
            $user,
            $cartTerminalCodes,
            $cartRestricted,
            50
        );
        foreach ($openInvoices as $i => $cart) {
            $code = (isset($cart['terminal_code']) ? (string) $cart['terminal_code'] : '');
            if ($code !== '' && isset($terminalByCode[$code])) {
                $openInvoices[$i]['terminal_id'] = (int) $terminalByCode[$code]['id'];
                $openInvoices[$i]['terminal_label'] = $terminalByCode[$code]['label'];
            }
        }

        return array(
            'store_restriction' => (!empty($access['restricted']) ? 1 : 0),
            'default_store_id' => $defaultStoreId,
            'default_terminal_id' => $defaultTerminalId,
            'current_terminal_id' => ($boundTerminalId > 0
                ? $boundTerminalId
                : ($activeShift ? (int) $activeShift['terminal_id'] : null)),
            'stores' => $stores,
            'terminals' => $terminals,
            'warehouses' => $warehouses,
            'user_warehouses' => $userWarehouses,
            'active_shift' => $activeShift,
            'open_invoices' => $openInvoices,
            'open_invoices_count' => count($openInvoices),

        );
    }
}
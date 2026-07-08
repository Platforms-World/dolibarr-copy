<?php
/**
 * TakeposBranchGuard
 *
 * Drop-in guard to call from invoice.php / ajax/ajax.php before saving a POS invoice.
 * Ensures the cashier's active terminal belongs to a branch they are assigned to,
 * and stamps fk_branch on the invoice row.
 *
 * Usage (add near top of invoice.php after $user is loaded):
 *
 *   require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposBranchGuard.class.php';
 *   TakeposBranchGuard::enforceAndStamp($db, $user, $terminal, $invoiceId);
 *
 * The guard is a no-op when TAKEPOS_ENFORCE_BRANCH_RESTRICTIONS is not set to '1'.
 */

require_once __DIR__ . '/TakeposBranchService.class.php';

class TakeposBranchGuard
{
    /**
     * Resolve which branch the given terminal belongs to.
     * Looks up llx_takepos_terminal.fk_store → llx_takepos_branch.fk_store.
     *
     * @return int|null  branch rowid, or null if terminal has no branch
     */
    public static function getBranchForTerminal($db, $terminalId)
    {
        if ((int) $terminalId <= 0) {
            return null;
        }

        $sql = "SELECT b.rowid"
             . " FROM " . MAIN_DB_PREFIX . "takepos_terminal t"
             . " INNER JOIN " . MAIN_DB_PREFIX . "takepos_branch b ON b.fk_store = t.fk_pos_source"
             . " WHERE t.rowid=" . ((int) $terminalId)
             . " AND b.active=1"
             . " LIMIT 1";

        $res = $db->query($sql);
        if ($res) {
            $obj = $db->fetch_object($res);
            if ($obj) {
                return (int) $obj->rowid;
            }
        }

        return null;
    }

    /**
     * Check user may use this terminal/branch and stamp fk_branch on the invoice.
     *
     * @param  object $db
     * @param  object $user         Dolibarr user
     * @param  int    $terminalId   Current POS terminal
     * @param  int    $invoiceId    Invoice rowid (0 to skip stamp)
     * @throws Exception if access denied
     */
    public static function enforceAndStamp($db, $user, $terminalId, $invoiceId = 0)
    {
        // Feature flag – skip entirely when not enabled
        if (getDolGlobalString('TAKEPOS_ENFORCE_BRANCH_RESTRICTIONS', '0') !== '1') {
            return;
        }

        // Admins bypass
        if (!empty($user->admin)) {
            return;
        }

        $entity   = !empty($user->entity) ? (int) $user->entity : 1;
        $branchId = self::getBranchForTerminal($db, $terminalId);

        if ($branchId === null) {
            // Terminal not linked to a branch – allowed
            return;
        }

        if (!TakeposBranchService::userCanAccessBranch($db, $user, $branchId, $entity)) {
            TakeposAudit::logEvent(
                $db, $user,
                'branch_access_denied',
                TakeposAudit::SEVERITY_WARNING,
                ['terminal_id' => $terminalId, 'branch_id' => $branchId],
                'Branch access denied for user ' . $user->login,
                'branch', $branchId
            );
            throw new Exception('Access denied: you are not assigned to the branch for this terminal.');
        }

        // Stamp the invoice
        if ((int) $invoiceId > 0) {
            self::stampInvoiceBranch($db, $invoiceId, $branchId);
        }
    }

    /**
     * Write fk_branch on an existing facture row.
     */
    public static function stampInvoiceBranch($db, $invoiceId, $branchId)
    {
        $sql = "UPDATE " . MAIN_DB_PREFIX . "facture"
             . " SET fk_branch=" . ((int) $branchId)
             . " WHERE rowid="   . ((int) $invoiceId)
             . " AND (fk_branch IS NULL OR fk_branch=0)";
        $db->query($sql);
    }

    /**
     * Return the branches a user is allowed, formatted for a JS/select dropdown.
     * Used to pre-filter terminal selection on POS login.
     *
     * @return array  [['id' => x, 'code' => '...', 'label' => '...'], ...]
     */
    public static function allowedBranchesForUser($db, $user)
    {
        if (!is_object($user) || empty($user->id)) {
            return [];
        }

        $entity = !empty($user->entity) ? (int) $user->entity : 1;

        if (!empty($user->admin)) {
            $branches = TakeposBranchService::listBranches($db, $entity, true);
            return array_map(function ($b) {
                return ['id' => (int) $b->rowid, 'code' => $b->code, 'label' => $b->label];
            }, $branches);
        }

        $userBranches = TakeposBranchService::getUserBranches($db, $entity, (int) $user->id);
        return array_map(function ($b) {
            return ['id' => (int) $b->fk_branch, 'code' => $b->code, 'label' => $b->label];
        }, $userBranches);
    }
}

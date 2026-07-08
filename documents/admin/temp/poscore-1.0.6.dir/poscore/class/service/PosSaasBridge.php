<?php

class PosSaasBridge
{
    /** @var DoliDB */
    public $db;
    public $error = '';

    public function __construct($db)
    {
        $this->db = $db;
    }

    protected function candidateRegistryPaths()
    {
        return array(
            DOL_DOCUMENT_ROOT.'/custom/saascore/class/service/SaasRegistryService.php',
            DOL_DOCUMENT_ROOT.'/custom/saascore/class/SaasRegistryService.php',
            DOL_DOCUMENT_ROOT.'/custom/saascore/lib/SaasRegistryService.php',
        );
    }

    protected function candidateAccessPaths()
    {
        return array(
            DOL_DOCUMENT_ROOT.'/custom/saascore/class/service/SaasAccessService.php',
            DOL_DOCUMENT_ROOT.'/custom/saascore/class/SaasAccessService.php',
            DOL_DOCUMENT_ROOT.'/custom/saascore/lib/SaasAccessService.php',
        );
    }

    protected function firstExisting(array $paths)
    {
        foreach ($paths as $file) {
            if (is_file($file)) return $file;
        }
        return '';
    }

    public function loadRegistryService()
    {
        $file = $this->firstExisting($this->candidateRegistryPaths());
        if (!$file) {
            $this->error = 'saascore registry service not found';
            return null;
        }
        require_once $file;
        if (!class_exists('SaasRegistryService')) {
            $this->error = 'SaasRegistryService class not found';
            return null;
        }
        return new SaasRegistryService($this->db);
    }

    public function loadAccessService()
    {
        $file = $this->firstExisting($this->candidateAccessPaths());
        if (!$file) {
            $this->error = 'saascore access service not found';
            return null;
        }
        require_once $file;
        if (!class_exists('SaasAccessService')) {
            $this->error = 'SaasAccessService class not found';
            return null;
        }
        return new SaasAccessService($this->db);
    }

    protected function callRegistry($service, $method, array $payload)
    {
        if (!method_exists($service, $method)) {
            return 1;
        }

        try {
            return $service->{$method}($payload);
        } catch (Throwable $e) {
            try {
                if ($method === 'registerModule') {
                    return $service->{$method}($payload['module_code'], $payload['module_label'], $payload['entity']);
                }
                if ($method === 'registerFeature') {
                    return $service->{$method}($payload['module_code'], $payload['feature_code'], $payload['feature_label'], $payload['entity']);
                }
                if ($method === 'registerLimit') {
                    return $service->{$method}($payload['module_code'], $payload['limit_code'], $payload['limit_label'], $payload['entity']);
                }
                if ($method === 'registerPermission') {
                    return $service->{$method}($payload['module_code'], $payload['permission_code'], $payload['permission_label'], $payload['entity']);
                }
            } catch (Throwable $e2) {
                $this->error = $e2->getMessage();
                return -1;
            }

            $this->error = $e->getMessage();
            return -1;
        }
    }

    public function registerCapabilities()
    {
        global $conf;

        $registry = $this->loadRegistryService();
        if (!$registry) {
            dol_syslog(__METHOD__.' '.$this->error, LOG_WARNING);
            return 1; // do not block module activation if registry helper is absent
        }

        $entity = (int) $conf->entity;

        $res = $this->callRegistry($registry, 'registerModule', array(
            'module_code' => 'poscore',
            'module_label' => 'POS Core',
            'entity' => $entity,
        ));
        if ($res < 0) {
            return -1;
        }

        $features = array(
            'pos_terminal'  => 'POS Terminal',
            'create_sale'   => 'Create Sale',
            'refund_sale'   => 'Refund Sale',
            'hold_sale'     => 'Hold Sale',
            'print_receipt' => 'Print Receipt',
            'x_report'      => 'X Report',
            'z_report'      => 'Z Report',
            'multi_cashier' => 'Multi Cashier',
        );
        foreach ($features as $code => $label) {
            $res = $this->callRegistry($registry, 'registerFeature', array(
                'module_code' => 'poscore',
                'feature_code' => $code,
                'feature_label' => $label,
                'entity' => $entity,
            ));
            if ($res < 0) {
                return -1;
            }
        }

        $limits = array(
            'max_cashiers'      => 'Max Cashiers',
            'max_terminals'     => 'Max Terminals',
            'max_sales_per_day' => 'Max Sales Per Day',
        );
        foreach ($limits as $code => $label) {
            $res = $this->callRegistry($registry, 'registerLimit', array(
                'module_code' => 'poscore',
                'limit_code' => $code,
                'limit_label' => $label,
                'entity' => $entity,
            ));
            if ($res < 0) {
                return -1;
            }
        }

        $permissions = array(
            'poscore.view'    => 'View POS',
            'poscore.cashier' => 'Cashier',
            'poscore.refund'  => 'Refund Sale',
            'poscore.reports' => 'POS Reports',
            'poscore.admin'   => 'POS Admin',
        );
        foreach ($permissions as $code => $label) {
            $res = $this->callRegistry($registry, 'registerPermission', array(
                'module_code' => 'poscore',
                'permission_code' => $code,
                'permission_label' => $label,
                'entity' => $entity,
            ));
            if ($res < 0) {
                return -1;
            }
        }

        return 1;
    }

    public function requireAccess($feature = 'pos_terminal', $permission = 'poscore.cashier', $tokenMethod = 'post')
    {
        global $conf, $user;

        if (empty($user->id)) {
            accessforbidden();
        }

        if ($tokenMethod === 'post' && !checkValideToken('', '', 'post')) {
            accessforbidden('Invalid CSRF token');
        }
        if ($tokenMethod === 'any' && !checkValideToken('', '', 'post') && !checkValideToken('', '', 'get')) {
            accessforbidden('Invalid CSRF token');
        }

        $access = $this->loadAccessService();
        if (!$access) {
            accessforbidden($this->error ?: 'saascore access service is missing');
        }

        $entity = (int) $conf->entity;
        if (!method_exists($access, 'isModuleEnabled') || !$access->isModuleEnabled($entity, 'poscore')) {
            accessforbidden('POS module is not enabled for this tenant');
        }
        if ($feature !== '' && (!method_exists($access, 'isFeatureEnabled') || !$access->isFeatureEnabled($entity, 'poscore', $feature))) {
            accessforbidden('POS feature is disabled');
        }
        if ($permission !== '' && (!method_exists($access, 'hasPermission') || !$access->hasPermission($entity, (int) $user->id, $permission))) {
            accessforbidden('Permission denied');
        }

        return $access;
    }

    public function getLimitValue($limitCode, $default = null)
    {
        global $conf;
        $access = $this->loadAccessService();
        if (!$access) {
            return $default;
        }
        if (method_exists($access, 'getLimitValue')) {
            return $access->getLimitValue((int) $conf->entity, 'poscore', $limitCode, $default);
        }
        return $default;
    }
}

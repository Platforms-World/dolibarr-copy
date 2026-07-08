<?php 
if (!defined('TAKEPOS_API_V1_AUTH_INCLUDED')) { 
    define('TAKEPOS_API_V1_AUTH_INCLUDED', 1); 
 
    require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php'; 
 
    function takeposApiRequestHeaders() 
    { 
        $headers = array(); 
        if (function_exists('getallheaders')) { 
            $headers = getallheaders(); 
            if (is_array($headers)) { 
                return $headers; 
            } 
        } 
 
        if (function_exists('apache_request_headers')) { 
            $headers = apache_request_headers(); 
            if (is_array($headers) and $headers) { 
                return $headers; 
            } 
        } 
 
        foreach ($_SERVER as $key => $value) { 
            if (strpos((string) $key, 'HTTP_') === 0) { 
                $name = str_replace('_', '-', substr((string) $key, 5)); 
                $headers[$name] = $value; 
            } 
        } 
 
        if (empty($headers['Authorization']) and empty($headers['AUTHORIZATION']) and empty($_SERVER['HTTP_AUTHORIZATION']) === false) { 
            $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION']; 
        } 
        if (empty($headers['Authorization']) and empty($headers['AUTHORIZATION']) and empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) === false) { 
            $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION']; 
        } 
 
        return $headers; 
    } 
 
    function takeposApiExtractBearerToken() 
    { 
        $header = ''; 
        foreach (takeposApiRequestHeaders() as $name => $value) { 
            if (strcasecmp((string) $name, 'Authorization') === 0) { 
                $header = trim((string) $value); 
                break; 
            } 
        } 
 
        if ($header === '') { 
            throw new TakeposApiException('TOKEN_MISSING', 'Authorization Bearer token is required', 401); 
        } 
        if (preg_match('/Bearer\s+(.+)$/i', $header, $matches)) { 
            $token = trim((string) $matches[1]); 
            if ($token !== '') { 
                return $token; 
            } 
        } 
 
        throw new TakeposApiException('AUTH_FAILED', 'Unauthorized', 401); 
    }
 
    function takeposApiLoadContextUser($db, $userId) 
    { 
        $userId = (int) $userId; 
        if ($userId === 0) { 
            return null; 
        } 
 
        $apiUser = new User($db); 
        $result = $apiUser->fetch($userId); 
        if ((int) $result === 0 or (int) $result === -1) { 
            throw new TakeposApiException('AUTH_FAILED', 'Unauthorized', 401); 
        } 
        if (method_exists($apiUser, 'getrights')) { 
            $apiUser->getrights(); 
        } 
 
        return $apiUser; 
    } 
 
    function takeposApiBuildSyntheticUser($entity, $tokenLabel) 
    { 
        $apiUser = new stdClass(); 
        $apiUser->id = 0; 
        $apiUser->entity = (int) $entity; 
        $apiUser->login = 'api:' . ($tokenLabel === '' ? 'unknown' : $tokenLabel); 
        $apiUser->admin = 0; 
        $apiUser->socid = 0; 
        return $apiUser; 
    } 
 
    function takeposApiApplyUserContext($apiUser, $entity) 
    { 
        global $user, $conf; 
 
        $user = $apiUser; 
        if (is_object($user)) { 
            $user->entity = (empty($user->entity) ? (int) $entity : (int) $user->entity); 
        } 
        if (is_object($conf)) { 
            $conf->entity = (int) $entity; 
        } 
    }
 
    function takeposApiAuth($db, $requiredScope = 'read', $requiredFeature = 'takepos.api_layer') 
    { 
        $requiredScope = strtolower(trim((string) $requiredScope)); 
        if ($requiredScope === '') { 
            $requiredScope = 'read'; 
        } 
 
        TakeposApiService::ensureSchema($db); 
        $token = takeposApiExtractBearerToken(); 
        $hash = TakeposApiService::hashToken($token); 
 
        $sql = 'SELECT rowid, entity, token_label, scope_csv, active, fk_created_by, date_expiration FROM ' . TakeposApiService::tableApiToken() . ' WHERE token_hash = ' . chr(39) . $db->escape($hash) . chr(39) . ' LIMIT 1'; 
        $resql = $db->query($sql); 
        if ($resql === false) { 
            throw new TakeposApiException('INTERNAL_ERROR', 'Failed to validate API token.', 500); 
        } 
 
        $row = $db->fetch_object($resql); 
        if ($row === null or $row === false) { 
            throw new TakeposApiException('AUTH_FAILED', 'Unauthorized', 401); 
        } 
        if (empty($row->active)) { 
            throw new TakeposApiException('TOKEN_DISABLED', 'Token is disabled.', 401); 
        } 
        if (TakeposApiService::isTokenExpiredValue(isset($row->date_expiration) ? $row->date_expiration : null)) {
            throw new TakeposApiException('TOKEN_EXPIRED', 'Token expired', 401);
        }
 
        $entity = (int) $row->entity; 
        $scopes = TakeposApiService::normalizeScopes((string) $row->scope_csv); 
        if (in_array('*', $scopes, true) === false and in_array($requiredScope, $scopes, true) === false) { 
            throw new TakeposApiException('FORBIDDEN', 'Insufficient token scope.', 403); 
        } 
 
        if (TakeposUserAccess::moduleEnabledForEntityStrict($db, $entity, 'takepos') === false) { 
            throw new TakeposApiException('FORBIDDEN', 'TakePOS module is disabled for this entity.', 403); 
        } 
        if ($requiredFeature !== '' and TakeposUserAccess::featureEnabledForEntityStrict($db, $entity, $requiredFeature) === false) { 
            throw new TakeposApiException('FORBIDDEN', 'Required API feature is disabled for this entity.', 403); 
        } 
 
        $apiUser = takeposApiLoadContextUser($db, (int) $row->fk_created_by); 
        if ($apiUser === null) { 
            $apiUser = takeposApiBuildSyntheticUser($entity, (string) $row->token_label); 
        } 
        if (!empty($apiUser->id) && !TakeposApiService::userCanUseScope($db, $apiUser, $requiredScope)) {
            throw new TakeposApiException('FORBIDDEN', 'User permission does not allow this API scope.', 403);
        }
        takeposApiApplyUserContext($apiUser, $entity); 
 
        $updateSql = 'UPDATE ' . TakeposApiService::tableApiToken() . ' SET date_last_use = ' . chr(39) . $db->escape(dol_print_date(dol_now(), 'dayhourlog')) . chr(39) . ' WHERE rowid = ' . ((int) $row->rowid) . ' AND entity = ' . $entity; 
        $db->query($updateSql); 
 
        return array( 
            'entity' => $entity, 
            'user' => $apiUser, 
            'token' => array( 
                'id' => (int) $row->rowid, 
                'label' => (string) $row->token_label, 
                'active' => (int) $row->active, 
                'user_id' => (int) $row->fk_created_by, 
                'scopes' => $scopes 
            ), 
            'permissions' => array( 
                'read' => (in_array('*', $scopes, true) or in_array('read', $scopes, true)), 
                'write' => (in_array('*', $scopes, true) or in_array('write', $scopes, true)) 
            ), 
            'scopes' => $scopes 
        ); 
    } 
}

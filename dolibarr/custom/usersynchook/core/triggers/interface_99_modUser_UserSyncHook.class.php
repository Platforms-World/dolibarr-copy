<?php
require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';

define('KAFO_LOG_PATH', DOL_DOCUMENT_ROOT . '/../logs/');

class InterfaceUserSyncHook extends DolibarrTriggers
{
    public $description = 'Sync new users to Laravel';

    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        @mkdir(KAFO_LOG_PATH, 0755, true);

        file_put_contents(
            KAFO_LOG_PATH . 'all_actions_debug.log',
            date('Y-m-d H:i:s') . ' ACTION=' . $action . ' | CLASS=' . get_class($object) . "\n",
            FILE_APPEND
        );

        if ($action === 'USER_CREATE') {

            file_put_contents(
                KAFO_LOG_PATH . 'usersync_debug.log',
                date('Y-m-d H:i:s') . ' OBJECT DUMP: ' . json_encode((array) $object) . "\n\n",
                FILE_APPEND
            );

            $this->pushToLaravel($object);
        }

        return 0;
    }

    private function pushToLaravel($object)
    {
        $payload = json_encode([
            'new_user_id'      => (int) $object->id,
            'creator_id'       => (int) $object->user_creation_id,
            'login'            => $object->login ?? '',
            'firstname'        => $object->firstname ?? '',
            'lastname'         => $object->lastname ?? '',
            'email'            => !empty($object->email)
                ? $object->email
                : ($object->login ?? 'user' . $object->id) . '@dolibarr.local',
            'admin'            => (int) ($object->admin ?? 0),
            'office_phone'     => $object->office_phone ?? '',
            'address'          => $object->address ?? '',
            'town'             => $object->town ?? '',
            'dolibarr_api_key' => $object->api_key ?? '',
        ]);

        $ch = curl_init('http://127.0.0.1:8000/api/webhook/user');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Webhook-Secret: 2083806',
            ],
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        file_put_contents(
            KAFO_LOG_PATH . 'usersync_debug.log',
            date('Y-m-d H:i:s') . ' HTTP: ' . $httpCode . ' | Response: ' . $response . ' | Error: ' . $curlError . "\n",
            FILE_APPEND
        );
    }
}
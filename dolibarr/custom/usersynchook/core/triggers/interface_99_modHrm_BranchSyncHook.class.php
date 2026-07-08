<?php
require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';

class InterfaceBranchSyncHook extends DolibarrTriggers
{
    public $description = 'Sync new establishments to Laravel as branches';

    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        // log all actions so we can find the right one
        file_put_contents(
            'C:/xampp/tmp/branchsync_debug.log',
            date('Y-m-d H:i:s') . ' ACTION: ' . $action . ' class: ' . get_class($object) . "\n",
            FILE_APPEND
        );

        if (in_array($action, ['ESTABLISHMENT_CREATE', 'COMPANY_CREATE'])) {
            $this->pushToLaravel($object, $user);
        }

        return 0;
    }

    private function pushToLaravel($object, $user)
    {
        $payload = json_encode([
            'dolibarr_id'    => (int) $object->id,
            'creator_id'     => (int) $user->id,
            'name_ar'           => $object->label      ?? $object->name ?? '',
            'name_en'           => $object->label      ?? $object->name ?? '',
            'branch_code'    => $object->ref        ?? null,
            'branch_type'    => $object->type       ?? null,
            'address'        => $object->address    ?? null,
            'lat'            => $object->latitude   ?? null,
            'lan'            => $object->longitude  ?? null,
            'manager_name'   => $object->manager    ?? null,
            'manager_mobile' => $object->phone      ?? null,
            'notes'          => $object->note       ?? null,
            'status'         => 'active',
            'country_id'     => $object->fk_country ?? null,
            'city_id'        => null,
            'region_id'      => null,
        ]);

        file_put_contents(
            'C:/xampp/tmp/branchsync_debug.log',
            date('Y-m-d H:i:s') . ' PAYLOAD: ' . $payload . "\n",
            FILE_APPEND
        );

        $ch = curl_init('http://127.0.0.1:8000/api/webhook/branch');
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
            'C:/xampp/tmp/branchsync_debug.log',
            date('Y-m-d H:i:s') . ' HTTP: ' . $httpCode . ' | Response: ' . $response . ' | Error: ' . $curlError . "\n\n",
            FILE_APPEND
        );
    }
}
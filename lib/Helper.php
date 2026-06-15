<?php

namespace ContaboModule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

class Helper
{
    public static function getContaboInstanceId($serviceId)
    {
        $customFieldId = Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->where('fieldname', 'like', 'instanceId%')
            ->value('id');

        $val = Capsule::table('tblcustomfieldsvalues')
            ->where('relid', (int)$serviceId)
            ->where('fieldid', (int)$customFieldId)
            ->value('value');

        if (empty($val)) {
            throw new \Exception("No matching Contabo target Instance ID found inside local WHMCS record maps.");
        }
        return preg_replace('/[^a-zA-Z0-9.-]/', '', $val);
    }

    public static function updateServiceData($serviceId, $instanceId, $password)
    {
        Capsule::table('tblhosting')->where('id', (int)$serviceId)->update(['password' => encrypt($password)]);

        $packageId = Capsule::table('tblhosting')->where('id', (int)$serviceId)->value('packageid');
        $customFieldId = Capsule::table('tblcustomfields')
            ->where('relid', (int)$packageId)
            ->where('fieldname', 'like', 'instanceId%')
            ->value('id');

        if ($customFieldId) {
            Capsule::table('tblcustomfieldsvalues')->updateOrInsert(
                ['relid' => (int)$serviceId, 'fieldid' => (int)$customFieldId],
                ['value' => preg_replace('/[^a-zA-Z0-9.-]/', '', $instanceId)]
            );
        }
    }

    public static function getCachedImages($api)
    {
        $cacheTable = 'tbladdonmodules'; // Standard fallback table for storing small module variables safely
        $currentTime = time();

        $cachedData = Capsule::table($cacheTable)
            ->where('module', 'contabo_images_cache')
            ->where('setting', 'payload')
            ->first();

        $cachedTime = Capsule::table($cacheTable)
            ->where('module', 'contabo_images_cache')
            ->where('setting', 'timestamp')
            ->value('value');

        if ($cachedData && $cachedTime && ($currentTime - (int)$cachedTime < 86400)) {
            return json_decode($cachedData->value, true);
        }

        try {
            $response = $api->request('GET', "/v1/compute/images");
            $images = $response['data'] ?? [];

            if (!empty($images)) {
                Capsule::table($cacheTable)->updateOrInsert(
                    ['module' => 'contabo_images_cache', 'setting' => 'payload'],
                    ['value' => json_encode($images)]
                );
                Capsule::table($cacheTable)->updateOrInsert(
                    ['module' => 'contabo_images_cache', 'setting' => 'timestamp'],
                    ['value' => $currentTime]
                );
            }
            return $images;
        } catch (\Exception $e) {
            return $cachedData ? json_decode($cachedData->value, true) : [];
        }
    }

    public static function generateStrongPassword($length = 14)
    {
        return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@$^*'), 0, $length);
    }

    public static function sendCustomEmail($userId, $subject, $body)
    {
        localAPI('SendMessage', [
            'userid'  => (int)$userId,
            'customsubject' => $subject,
            'custommessage' => htmlspecialchars($body, ENT_QUOTES, 'UTF-8')
        ]);
    }
}

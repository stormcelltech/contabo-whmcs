<?php

namespace WHMCS\Module\Server\Contabo;

use Illuminate\Database\Capsule\Manager as Capsule;

class ContaboHelper
{
    /**
     * Format bytes to human readable size
     */
    public static function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Get service details from database
     */
    public static function getService($serviceId)
    {
        return Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->first();
    }

    /**
     * Update service custom field
     */
   /**
     * Update service custom field matching the exact product relationship mapping
     */
    public static function setServiceCustomField($serviceId, $fieldName, $value)
    {
        $serviceId = (int)$serviceId;

        // 1. Find the product ID (package ID) associated with this client service
        $packageId = Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->value('packageid');

        if (!$packageId) {
            return;
        }

        // 2. Find the Custom Field matching the name assigned to this specific product package
        $field = Capsule::table('tblcustomfields')
            ->where('relid', (int)$packageId)
            ->where('fieldname', 'like', $fieldName . '%')
            ->first();

        // 3. If it doesn't exist yet, try a fallback search for a global field configuration
        if (!$field) {
            $field = Capsule::table('tblcustomfields')
                ->where('fieldname', 'like', $fieldName . '%')
                ->first();
        }

        // 4. Update or Insert the value seamlessly into the database values table
        if ($field) {
            Capsule::table('tblcustomfieldsvalues')
                ->updateOrInsert(
                    ['fieldid' => $field->id, 'relid' => $serviceId],
                    ['value' => (string)$value]
                );
        }
    }

    /**
     * Get service custom field value
     */
    public static function getServiceCustomField($serviceId, $fieldName)
    {
        $field = Capsule::table('tblcustomfields')
            ->where('relid', 0)
            ->where('fieldname', $fieldName)
            ->first();

        if ($field) {
            $value = Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $field->id)
                ->where('relid', $serviceId)
                ->first();
            
            return $value ? $value->value : null;
        }

        return null;
    }

    /**
     * Check if instance type is VPS
     */
    public static function isVps($instance)
    {
        return isset($instance['productType']) && $instance['productType'] === 'vps';
    }

    /**
     * Check if instance type is VDS
     */
    public static function isVds($instance)
    {
        return isset($instance['productType']) && $instance['productType'] === 'vds';
    }

    /**
     * Get instance status label with CSS classes
     */
    public static function getStatusLabel($status)
    {
        $statusMap = [
            'active'       => ['label-success', 'Active'],
            'pending'      => ['label-info', 'Pending'],
            'stopped'      => ['label-warning', 'Stopped'],
            'suspended'    => ['label-danger', 'Suspended'],
            'terminated'   => ['label-danger', 'Terminated'],
            'error'        => ['label-danger', 'Error'],
            'unknown'      => ['label-default', 'Unknown'],
        ];

        $key = strtolower($status ?? 'unknown');
        $info = $statusMap[$key] ?? $statusMap['unknown'];

        return '<span class="label ' . $info[0] . '">' . $info[1] . '</span>';
    }

    /**
     * Convert region code to full name
     */
    public static function getRegionName($code)
    {
        $regions = [
            'EU'         => 'European Union',
            'US-central' => 'US Central',
            'US-east'    => 'US East',
            'US-west'    => 'US West',
            'SIN'        => 'Singapore',
            'UK'         => 'United Kingdom',
            'AUS'        => 'Australia',
            'JPN'        => 'Japan',
            'IND'        => 'India',
        ];

        return $regions[$code] ?? $code;
    }

    /**
     * Log module activity
     */
    public static function log($serviceId, $action, $message, $type = 'info')
    {
        $service = self::getService($serviceId);

        if ($service) {
            $logMessage = "Contabo {$action}: {$message}";
            
            Capsule::table('tblactivitylog')->insert([
                'userid'      => 0,
                'firstname'   => 'System',
                'lastname'    => 'Contabo',
                'email'       => '',
                'clientid'    => $service->userid,
                'action'      => 'Contabo ' . $action,
                'description' => $message,
                'ip'          => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'date'        => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Check if snapshot is restored
     */
    public static function snapshotRestore($snapshot)
    {
        return isset($snapshot['status']) && $snapshot['status'] === 'completed';
    }

    /**
     * Format datetime to readable format
     */
    public static function formatDateTime($dateString)
    {
        $date = new \DateTime($dateString);
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Get time ago in human readable format
     */
    public static function timeAgo($dateString)
    {
        $date = new \DateTime($dateString);
        $now = new \DateTime();
        $diff = $now->diff($date);

        if ($diff->days > 0) {
            return $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
        } elseif ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        } elseif ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        } else {
            return 'Just now';
        }
    }

    /**
     * Validate IPv4 address
     */
    public static function isValidIpv4($ip)
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /**
     * Validate IPv6 address
     */
    public static function isValidIpv6($ip)
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }
}
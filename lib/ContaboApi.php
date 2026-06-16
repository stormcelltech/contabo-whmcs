<?php

namespace Contabo\Api;

class ContaboApi
{
    private $clientId;
    private $clientSecret;
    private $apiUser;
    private $apiPassword;
    private $accessToken;
    private $tokenExpiry;
    private $baseUrl = 'https://api.contabo.com/v1';
    private $authUrl = 'https://auth.contabo.com/auth/realms/contabo/protocol/openid-connect/token';

    public function __construct($clientId, $clientSecret, $apiUser, $apiPassword)
    {
        $this->clientId     = trim($clientId);
        $this->clientSecret = trim($clientSecret);
        $this->apiUser      = trim($apiUser);
        $this->apiPassword  = trim($apiPassword);
    }

    private function getAccessToken()
    {
        if ($this->accessToken && $this->tokenExpiry > time() + 30) {
            return $this->accessToken;
        }

        $ch = curl_init($this->authUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'username'      => $this->apiUser,
                'password'      => $this->apiPassword,
                'grant_type'    => 'password',
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT    => 30,
        ]);

        $raw = curl_exec($ch);
        $response = json_decode($raw, true);
        curl_close($ch);

        if (empty($response['access_token'])) {
            throw new \Exception('Contabo: Gateway Authentication Blocked. Please check global setting keys inside Module Settings panels.');
        }

        $this->accessToken = $response['access_token'];
        $this->tokenExpiry = time() + ($response['expires_in'] ?? 3600);

        return $this->accessToken;
    }

    private function requestId()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

private function request($method, $path, $body = null)
{
    $token = $this->getAccessToken();
    $url   = $this->baseUrl . $path;
    $method = strtoupper($method);

    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'x-request-id: ' . $this->requestId(),
    ];

    $ch = curl_init($url);
    
    // Ensure native constants are used without quotes
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
    ]);

    // Force empty payload protection block for write actions
    if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
        if ($body === null || (is_array($body) && empty($body))) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
    }

    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        throw new \Exception('Contabo API cURL error: ' . $err);
    }

    $data = json_decode($raw, true);

    if ($code >= 400) {
        $msg = $data['message'] ?? $data['error'] ?? $raw;
        if (is_array($msg)) { $msg = json_encode($msg); }
        throw new \Exception("Contabo API error {$code}: {$msg}");
    }

    return $data;
}

    public function getInstances($page = 1, $size = 100) { return $this->request('GET', "/compute/instances?page={$page}&size={$size}"); }
    public function getInstance($instanceId) { return $this->request('GET', "/compute/instances/{$instanceId}"); }
    public function createInstance($params) { return $this->request('POST', '/compute/instances', $params); }
    public function updateInstance($instanceId, $displayName) { return $this->request('PATCH', "/compute/instances/{$instanceId}", ['displayName' => $displayName]); }
    public function reinstallInstance($instanceId, $imageId, $extraParams = []) { return $this->request('PUT', "/compute/instances/{$instanceId}", array_merge(['imageId' => $imageId], $extraParams)); }
    public function cancelInstance($instanceId, $cancelDate = null) { $body = []; if ($cancelDate) { $body['cancelDate'] = $cancelDate; } return $this->request('POST', "/compute/instances/{$instanceId}/cancel", $body); }
    public function startInstance($instanceId) { return $this->request('POST', "/compute/instances/{$instanceId}/actions/start"); }
    public function stopInstance($instanceId) { return $this->request('POST', "/compute/instances/{$instanceId}/actions/stop"); }
    public function shutdownInstance($instanceId) { return $this->request('POST', "/compute/instances/{$instanceId}/actions/shutdown"); }
    public function restartInstance($instanceId) { return $this->request('POST', "/compute/instances/{$instanceId}/actions/restart"); }
    public function getSnapshots($instanceId, $page = 1, $size = 100) { return $this->request('GET', "/compute/instances/{$instanceId}/snapshots?page={$page}&size={$size}"); }
    public function createSnapshot($instanceId, $name, $description = '') { return $this->request('POST', "/compute/instances/{$instanceId}/snapshots", ['name' => $name, 'description' => $description]); }
    public function deleteSnapshot($instanceId, $snapshotId) { return $this->request('DELETE', "/compute/instances/{$instanceId}/snapshots/{$snapshotId}"); }
    public function restoreSnapshot($instanceId, $snapshotId) { return $this->request('POST', "/compute/instances/{$instanceId}/snapshots/{$snapshotId}/rollback"); }
    public function getImages($page = 1, $size = 100) { return $this->request('GET', "/compute/images?page={$page}&size={$size}"); }
    public function getInstanceAudits($instanceId = null, $page = 1, $size = 50) { $q = "?page={$page}&size={$size}"; if ($instanceId) { $q .= "&instanceId={$instanceId}"; } return $this->request('GET', "/compute/instances/audits{$q}"); }
    public function getActionAudits($instanceId = null, $page = 1, $size = 50) { $q = "?page={$page}&size={$size}"; if ($instanceId) { $q .= "&instanceId={$instanceId}"; } return $this->request('GET', "/compute/instances/actions/audits{$q}"); }
}
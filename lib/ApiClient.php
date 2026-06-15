<?php

namespace ContaboModule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

class ApiClient
{
    private $clientId;
    private $clientSecret;
    private $username;
    private $password;
    private $baseUrl = 'https://api.contabo.com/v1';
    private $tokenUrl = 'https://auth.contabo.com/auth/realms/contabo/protocol/openid-connect/token';

    public function __construct(array $params)
    {
        $this->clientId     = $params['configoption1'];
        $this->clientSecret = $params['configoption2'];
        $this->username     = $params['configoption3'];
        $this->password     = $params['configoption4'];
    }

    private function getAccessToken()
    {
        // Isolation storage map configuration
        $cacheKey = 'ctb_tok_' . hash('sha256', $this->clientId . $this->username);

        if (isset($_SESSION[$cacheKey]) && $_SESSION[$cacheKey]['expires'] > time()) {
            return $_SESSION[$cacheKey]['token'];
        }

        $postFields = [
            'grant_type'    => 'password',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'username'      => $this->username,
            'password'      => $this->password
        ];

        $ch = curl_init($this->tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $data = json_decode($response, true);
        curl_close($ch);

        if (!isset($data['access_token'])) {
            logActivity("Contabo Authentication Failure Encountered.");
            throw new \Exception("Authentication credentials refused by API Provider.");
        }

        $_SESSION[$cacheKey] = [
            'token'   => $data['access_token'],
            'expires' => time() + ((int)$data['expires_in'] - 60) // Safe buffer padding reduction
        ];

        return $data['access_token'];
    }

    public function request($method, $endpoint, $payload = null)
    {
        $token = $this->getAccessToken();
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $headers = [
            "Authorization: Bearer {$token}",
            "Content-Type: application/json",
            "x-request-id: " . hash('md5', uniqid(microtime(), true))
        ];

        if ($payload) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);
        if ($httpCode >= 400) {
            $msg = $decoded['error']['message'] ?? "API Backend Error Exception (HTTP State Code: {$httpCode})";
            throw new \Exception($msg);
        }

        return $decoded;
    }
}

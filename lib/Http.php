<?php

declare(strict_types=1);

namespace Contabo\Whmcs;

final class HttpRequest
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly array $headers,
        public readonly ?string $body = null,
    ) {
    }
}

final class HttpResponse
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}

interface HttpTransport
{
    public function send(HttpRequest $request): HttpResponse;
}

final class CurlTransport implements HttpTransport
{
    public function __construct(
        private readonly int $connectTimeoutSeconds = 10,
        private readonly int $timeoutSeconds = 30,
    ) {
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $method = strtoupper($request->method);
        if ($method === '') {
            throw new \InvalidArgumentException('HTTP method cannot be empty.');
        }
        $handle = curl_init($request->url);
        if ($handle === false) {
            throw new ContaboException('Unable to initialize the HTTP client.', 'transport_init', true, false);
        }

        $responseHeaders = [];
        $headers = [];
        foreach ($request->headers as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return $length;
            },
        ]);
        if ($request->body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $request->body);
        }

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $errno = curl_errno($handle);
        curl_close($handle);

        if ($body === false || $errno !== 0) {
            throw new ContaboException(
                'The Contabo API could not be reached. Request outcome is unknown.',
                'transport_failure',
                true,
                true,
            );
        }

        return new HttpResponse($status, $responseHeaders, (string) $body);
    }
}

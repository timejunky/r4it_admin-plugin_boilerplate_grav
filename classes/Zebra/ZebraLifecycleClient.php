<?php

/**
r4it_admin-plugin_boilerplate_grav
@category Grav_Plugin
@author Nejat P. Eryigit <https://www.ready-4-it.com>
@copyright 2026 Nejat P. Eryigit
@license https://opensource.org/licenses/MIT MIT License
@link https://www.ready-4-it.com/r4it_admin_plugin_boilerplate
*/

declare(strict_types=1);

namespace Grav\Plugin\R4itAdminPluginBoilerplate\Zebra;

/**
 * Fail-open HTTP client for Streaming Zebra lifecycle + update-check.
 *
 * Lifecycle: POST /api/v2/lifecycle/event  (install | uninstall | update)
 * Telemetry: POST /api/update-check
 *
 * Never sends shop_url or other PII. Never throws to the caller.
 */
final class ZebraLifecycleClient
{
    public const LIFECYCLE_PATH = '/api/v2/lifecycle/event';
    public const UPDATE_CHECK_PATH = '/api/update-check';

    /** @var callable(string,string,array<string,string>,string,int):array{ok:bool,status:int} */
    private $transport;

    /**
     * @param callable(string,string,array<string,string>,string,int):array{ok:bool,status:int}|null $transport
     */
    public function __construct(
        private readonly string $apiBaseUrl,
        ?callable $transport = null,
        private readonly int $timeoutSeconds = 2
    ) {
        $this->transport = $transport ?? [$this, 'defaultTransport'];
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function sendLifecycle(array $payload): bool
    {
        return $this->postJson(self::LIFECYCLE_PATH, $payload);
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function sendUpdateCheck(array $payload): bool
    {
        return $this->postJson(self::UPDATE_CHECK_PATH, $payload);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function postJson(string $path, array $payload): bool
    {
        $base = rtrim($this->apiBaseUrl, '/');
        if ($base === '') {
            return false;
        }

        $url = $base . $path;
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!is_string($body) || $body === '') {
            return false;
        }

        try {
            $result = ($this->transport)(
                'POST',
                $url,
                [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                $body,
                $this->timeoutSeconds
            );
        } catch (\Throwable $e) {
            return false;
        }

        $status = (int)($result['status'] ?? 0);

        return (bool)($result['ok'] ?? false) && $status >= 200 && $status < 300;
    }

    /**
     * @param array<string,string> $headers
     * @return array{ok:bool,status:int}
     */
    private function defaultTransport(string $method, string $url, array $headers, string $body, int $timeout): array
    {
        if (function_exists('curl_init')) {
            return $this->curlTransport($method, $url, $headers, $body, $timeout);
        }

        return $this->streamTransport($method, $url, $headers, $body, $timeout);
    }

    /**
     * @param array<string,string> $headers
     * @return array{ok:bool,status:int}
     */
    private function curlTransport(string $method, string $url, array $headers, string $body, int $timeout): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0];
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
        ]);

        curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno !== 0) {
            return ['ok' => false, 'status' => $status];
        }

        return ['ok' => $status >= 200 && $status < 300, 'status' => $status];
    }

    /**
     * @param array<string,string> $headers
     * @return array{ok:bool,status:int}
     */
    private function streamTransport(string $method, string $url, array $headers, string $body, int $timeout): array
    {
        $headerBlob = '';
        foreach ($headers as $name => $value) {
            $headerBlob .= $name . ': ' . $value . "\r\n";
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => $headerBlob,
                'content' => $body,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents($url, false, $ctx);
        $status = 0;
        if (isset($http_response_header[0]) && is_string($http_response_header[0])) {
            if (preg_match('/\s(\d{3})\s/', $http_response_header[0], $m) === 1) {
                $status = (int)$m[1];
            }
        }

        if ($result === false && $status === 0) {
            return ['ok' => false, 'status' => 0];
        }

        return ['ok' => $status >= 200 && $status < 300, 'status' => $status];
    }
}

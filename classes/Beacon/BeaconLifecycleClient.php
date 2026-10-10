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

namespace Grav\Plugin\R4itAdminPluginBoilerplate\Beacon;

/**
 * Fail-open HTTP client for Alveos Beacon tracking (Free & Open Source).
 *
 * Endpoint: POST https://login.alveos.eu/api/v1/track
 * Never contacts Streaming Zebra. Never sends PII.
 */
final class BeaconLifecycleClient
{
    public const DEFAULT_TRACK_URL = 'https://login.alveos.eu/api/v1/track';
    public const DEV_TRACK_URL = 'https://login.dev.alveos.loc/api/v1/track';

    /** @var callable(string,string,array<string,string>,string,int):array{ok:bool,status:int} */
    private $transport;

    /**
     * @param callable(string,string,array<string,string>,string,int):array{ok:bool,status:int}|null $transport
     */
    public function __construct(
        private readonly string $trackUrl = self::DEFAULT_TRACK_URL,
        private readonly string $token = '',
        ?callable $transport = null,
        private readonly int $timeoutSeconds = 2
    ) {
        $this->transport = $transport ?? [$this, 'defaultTransport'];
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function sendBeacon(array $payload): bool
    {
        $url = trim($this->trackUrl);
        if ($url === '') {
            return false;
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!is_string($body) || $body === '') {
            return false;
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'Grav-AdminPluginBoilerplate/' . ($payload['version'] ?? '1.0'),
        ];

        $token = trim($this->token);
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        try {
            $result = ($this->transport)(
                'POST',
                $url,
                $headers,
                $body,
                $this->timeoutSeconds
            );
        } catch (\Throwable $e) {
            return false;
        }

        $status = (int)($result['status'] ?? 0);

        return (bool)($result['ok'] ?? false) && ($status === 200 || $status === 202);
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
            CURLOPT_SSL_VERIFYPEER => !str_contains($url, '.loc'),
            CURLOPT_SSL_VERIFYHOST => str_contains($url, '.loc') ? 0 : 2,
        ]);

        curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno !== 0) {
            return ['ok' => false, 'status' => $status];
        }

        return ['ok' => ($status === 200 || $status === 202), 'status' => $status];
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
            'ssl' => [
                'verify_peer' => !str_contains($url, '.loc'),
                'verify_peer_name' => !str_contains($url, '.loc'),
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

        return ['ok' => ($status === 200 || $status === 202), 'status' => $status];
    }
}

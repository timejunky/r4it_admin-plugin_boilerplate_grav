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
 * Local install/update detection + Alveos Beacon telemetry.
 *
 * install = first boot (no stored version) -> POST /api/v1/track (event: install)
 * update  = stored version differs from current -> POST /api/v1/track (event: update)
 *
 * Fail-open: network or HTTP errors never block Grav.
 */
final class BeaconLifecycleTracker
{
    public const RETRY_COOLDOWN_SECONDS = 3600;

    /**
     * @param array{
     *   enabled?:bool,
     *   track_url?:string,
     *   app_id?:string,
     *   token?:string,
     *   client_type?:string
     * } $config
     */
    public function __construct(
        private readonly BeaconLifecycleClient $client,
        private readonly string $stateFile,
        private readonly array $config,
        private readonly int $now = 0,
        private readonly string $legacyStateFile = ''
    ) {
    }

    private function getNow(): int
    {
        return $this->now > 0 ? $this->now : time();
    }

    /**
     * @return array<string,mixed>
     */
    public function status(): array
    {
        $state = $this->readState();
        $reason = 'ok';
        if (!$this->isEnabled()) {
            $reason = 'disabled';
        }

        return [
            'enabled' => $this->isEnabled(),
            'configured' => true,
            'reason' => $reason,
            'app_id' => $this->appId(),
            'instance_id' => is_string($state['instance_id'] ?? null) ? (string)$state['instance_id'] : '',
            'reported_version' => is_string($state['reported_version'] ?? null) ? (string)$state['reported_version'] : '',
            'install_sent_at' => is_string($state['install_sent_at'] ?? null) ? (string)$state['install_sent_at'] : '',
            'last_update_at' => is_string($state['last_update_at'] ?? null) ? (string)$state['last_update_at'] : '',
            'last_error' => is_string($state['last_error'] ?? null) ? (string)$state['last_error'] : '',
            // Legacy zebra compatibility keys for templates
            'module_name' => $this->appId(),
            'last_update_check_at' => 0,
        ];
    }

    public function isEnabled(): bool
    {
        return (bool)($this->config['enabled'] ?? true);
    }

    public function appId(): string
    {
        $id = trim((string)($this->config['app_id'] ?? 'alv_prd_boilerplate'));
        return $id !== '' ? $id : 'alv_prd_boilerplate';
    }

    public function clientType(): string
    {
        $ct = trim((string)($this->config['client_type'] ?? 'grav'));
        return $ct !== '' ? $ct : 'grav';
    }

    public function sync(string $currentVersion): array
    {
        $status = $this->status();
        if (!$this->isEnabled()) {
            return $status;
        }

        if (!$this->isSemver($currentVersion)) {
            $status['reason'] = 'invalid_version';
            return $status;
        }

        if ($this->isCoolingDown()) {
            $status['reason'] = 'cooldown';
            return $status;
        }

        $state = $this->ensureInstanceId($this->readState());
        $reported = is_string($state['reported_version'] ?? null) ? (string)$state['reported_version'] : '';

        if ($reported === '') {
            $this->attemptInstall($currentVersion, $state);
        } elseif ($reported !== $currentVersion) {
            $this->attemptUpdate($reported, $currentVersion, $state);
        }

        return $this->status();
    }

    /**
     * @param array<string,mixed> $state
     */
    private function attemptInstall(string $currentVersion, array $state): void
    {
        $payload = $this->beaconPayload('install', $currentVersion, $state);
        $ok = $this->client->sendBeacon($payload);

        if ($ok) {
            $state['reported_version'] = $currentVersion;
            $state['install_sent_at'] = gmdate('Y-m-d\TH:i:s\Z', $this->getNow());
            $state['last_error'] = '';
            $this->writeState($state);
            return;
        }

        $state['last_error'] = 'install_failed';
        $state['last_failed_attempt_at'] = $this->getNow();
        $this->writeState($state);
    }

    /**
     * @param array<string,mixed> $state
     */
    private function attemptUpdate(string $fromVersion, string $currentVersion, array $state): void
    {
        $payload = $this->beaconPayload('update', $currentVersion, $state);
        $ok = $this->client->sendBeacon($payload);

        if ($ok) {
            $state['reported_version'] = $currentVersion;
            $state['last_update_at'] = gmdate('Y-m-d\TH:i:s\Z', $this->getNow());
            $state['last_error'] = '';
            $this->writeState($state);
            return;
        }

        $state['last_error'] = 'update_failed';
        $state['last_failed_attempt_at'] = $this->getNow();
        $this->writeState($state);
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private function beaconPayload(string $event, string $version, array $state): array
    {
        return [
            'app_id' => $this->appId(),
            'instance_id' => (string)($state['instance_id'] ?? ''),
            'event' => $event,
            'version' => $version,
            'client_type' => $this->clientType(),
            'platform' => 'php',
            'timestamp' => $this->getNow(),
        ];
    }

    private function isCoolingDown(): bool
    {
        $state = $this->readState();
        $lastFail = (int)($state['last_failed_attempt_at'] ?? 0);
        if ($lastFail <= 0) {
            return false;
        }

        return ($this->getNow() - $lastFail) < self::RETRY_COOLDOWN_SECONDS;
    }

    /**
     * @return array<string,mixed>
     */
    public function readState(): array
    {
        if (is_file($this->stateFile)) {
            $raw = @file_get_contents($this->stateFile);
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        // Migrate from legacy zebra state if it exists to preserve install identity
        if ($this->legacyStateFile !== '' && is_file($this->legacyStateFile)) {
            $raw = @file_get_contents($this->legacyStateFile);
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $migrated = [
                        'instance_id' => $this->normalizeToUuid((string)($decoded['install_id'] ?? '')),
                        'reported_version' => (string)($decoded['reported_version'] ?? ''),
                        'install_sent_at' => (string)($decoded['install_sent_at'] ?? ''),
                        'last_update_at' => (string)($decoded['last_update_at'] ?? ''),
                        'last_error' => '',
                    ];
                    $this->writeState($migrated);
                    return $migrated;
                }
            }
        }

        return [];
    }

    /**
     * @param array<string,mixed> $state
     */
    public function writeState(array $state): void
    {
        $dir = dirname($this->stateFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $encoded = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (is_string($encoded)) {
            @file_put_contents($this->stateFile, $encoded . PHP_EOL);
        }
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private function ensureInstanceId(array $state): array
    {
        $existing = is_string($state['instance_id'] ?? null) ? trim((string)$state['instance_id']) : '';
        if ($existing === '') {
            $state['instance_id'] = $this->generateUuidV4();
            $this->writeState($state);
        }

        return $state;
    }

    private function normalizeToUuid(string $raw): string
    {
        $hex = strtolower(trim($raw));
        if (preg_match('/^[0-9a-f]{32}$/', $hex) === 1) {
            return substr($hex, 0, 8) . '-'
                . substr($hex, 8, 4) . '-'
                . substr($hex, 12, 4) . '-'
                . substr($hex, 16, 4) . '-'
                . substr($hex, 20, 12);
        }
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $hex) === 1) {
            return $hex;
        }
        return $this->generateUuidV4();
    }

    private function generateUuidV4(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff)
        );
    }

    private function isSemver(string $v): bool
    {
        return (bool)preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:-[0-9A-Za-z.-]+)?$/', trim($v));
    }
}

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
 * Local install/update detection + Zebra pings.
 *
 * install  = first successful boot with a module_name (no stored version)
 * update   = stored version differs from current blueprint version
 * update_check = throttled entitlement ping (license key only; not a download KPI)
 *
 * Count-only modules ping with a local opaque install_id when license_key is empty.
 * Fail-open: unknown module or HTTP errors never block Grav.
 */
final class ZebraLifecycleTracker
{
    public const RETRY_COOLDOWN_SECONDS = 3600;

    /**
     * @param array{
     *   enabled?:bool,
     *   api_base_url?:string,
     *   module_name?:string,
     *   company_key?:string,
     *   license_key?:string,
     *   update_check_interval_hours?:int
     * } $config
     */
    public function __construct(
        private readonly ZebraLifecycleClient $client,
        private readonly string $stateFile,
        private readonly array $config,
        private readonly int $now
    ) {
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
        } elseif ($this->moduleName() === '') {
            $reason = 'no_module';
        }

        return [
            'enabled' => $this->isEnabled(),
            'configured' => $this->moduleName() !== '',
            'reason' => $reason,
            'module_name' => $this->moduleName(),
            'reported_version' => is_string($state['reported_version'] ?? null) ? (string)$state['reported_version'] : '',
            'install_sent_at' => is_string($state['install_sent_at'] ?? null) ? (string)$state['install_sent_at'] : '',
            'last_update_at' => is_string($state['last_update_at'] ?? null) ? (string)$state['last_update_at'] : '',
            'last_update_check_at' => (int)($state['last_update_check_at'] ?? 0),
            'last_error' => is_string($state['last_error'] ?? null) ? (string)$state['last_error'] : '',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function sync(string $currentVersion): array
    {
        $status = $this->status();
        if (!$this->isEnabled()) {
            return $status;
        }
        if ($this->moduleName() === '') {
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

        $state = $this->ensureInstallId($this->readState());
        $reported = is_string($state['reported_version'] ?? null) ? (string)$state['reported_version'] : '';

        if ($reported === '') {
            $this->attemptInstall($currentVersion, $state);
        } elseif ($reported !== $currentVersion) {
            $this->attemptUpdate($reported, $currentVersion, $state);
        }

        $after = $this->readState();
        if (trim((string)($after['last_error'] ?? '')) === '') {
            $this->attemptUpdateCheck($currentVersion, $after);
        }

        return $this->status();
    }

    /**
     * @param array<string,mixed> $state
     */
    private function attemptInstall(string $currentVersion, array $state): void
    {
        $ok = $this->client->sendLifecycle($this->lifecyclePayload('install', $currentVersion, null));
        $state['last_attempt_at'] = $this->now;
        if ($ok) {
            $state['reported_version'] = $currentVersion;
            $state['install_sent_at'] = $this->isoNow();
            $state['last_error'] = '';
        } else {
            $state['last_error'] = 'lifecycle_install_failed';
        }
        $this->writeState($state);
    }

    /**
     * @param array<string,mixed> $state
     */
    private function attemptUpdate(string $previousVersion, string $currentVersion, array $state): void
    {
        $ok = $this->client->sendLifecycle($this->lifecyclePayload('update', $currentVersion, $previousVersion));
        $state['last_attempt_at'] = $this->now;
        if ($ok) {
            $state['reported_version'] = $currentVersion;
            $state['last_update_at'] = $this->isoNow();
            $state['last_error'] = '';
        } else {
            $state['last_error'] = 'lifecycle_update_failed';
        }
        $this->writeState($state);
    }

    /**
     * @param array<string,mixed> $state
     */
    private function attemptUpdateCheck(string $currentVersion, array $state): void
    {
        if ($this->licenseKey() === '') {
            return;
        }

        $interval = max(1, (int)($this->config['update_check_interval_hours'] ?? 24)) * 3600;
        $lastCheck = (int)($state['last_update_check_at'] ?? 0);
        if ($lastCheck > 0 && ($this->now - $lastCheck) < $interval) {
            return;
        }

        $ok = $this->client->sendUpdateCheck([
            'license_key' => $this->licenseKey(),
            'install_id' => $this->readInstallId(),
            'module_name' => $this->moduleName(),
            'current_version' => $currentVersion,
            'company_key' => $this->companyKey(),
        ]);
        $state['last_attempt_at'] = $this->now;
        if ($ok) {
            $state['last_update_check_at'] = $this->now;
            if (($state['last_error'] ?? '') === 'update_check_failed') {
                $state['last_error'] = '';
            }
        } else {
            $state['last_error'] = 'update_check_failed';
        }
        $this->writeState($state);
    }

    /**
     * @return array<string,string>
     */
    private function lifecyclePayload(string $event, string $currentVersion, ?string $previousVersion): array
    {
        $payload = [
            'install_id' => $this->readInstallId(),
            'module_name' => $this->moduleName(),
            'event' => $event,
            'current_version' => $currentVersion,
            'company_key' => $this->companyKey(),
        ];
        $license = $this->licenseKey();
        if ($license !== '') {
            $payload['license_key'] = $license;
        }
        if ($previousVersion !== null && $previousVersion !== '') {
            $payload['previous_version'] = $previousVersion;
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private function ensureInstallId(array $state): array
    {
        $clean = $this->sanitizeInstallId((string)($state['install_id'] ?? ''));
        if ($clean !== '') {
            $state['install_id'] = $clean;
            return $state;
        }

        $state['install_id'] = bin2hex(random_bytes(16));
        $this->writeState($state);

        return $state;
    }

    private function readInstallId(): string
    {
        $state = $this->readState();
        $clean = $this->sanitizeInstallId((string)($state['install_id'] ?? ''));
        if ($clean !== '') {
            return $clean;
        }

        return $this->sanitizeInstallId((string)($this->ensureInstallId($state)['install_id'] ?? ''));
    }

    private function sanitizeInstallId(string $raw): string
    {
        $clean = strtolower(trim((string)preg_replace('/[^a-f0-9]/', '', $raw)));
        $len = strlen($clean);
        if ($len !== 32 && $len !== 64) {
            return '';
        }

        return $clean;
    }

    private function isEnabled(): bool
    {
        return (bool)($this->config['enabled'] ?? true);
    }

    private function licenseKey(): string
    {
        return trim((string)($this->config['license_key'] ?? ''));
    }

    private function moduleName(): string
    {
        $raw = strtolower(trim((string)($this->config['module_name'] ?? '')));

        return (string)preg_replace('/[^a-z0-9_\-]/', '', $raw);
    }

    private function companyKey(): string
    {
        $raw = strtolower(trim((string)($this->config['company_key'] ?? 'ready-4-it')));
        $clean = (string)preg_replace('/[^a-z0-9_\-]/', '', $raw);

        return $clean !== '' ? $clean : 'ready-4-it';
    }

    private function isSemver(string $version): bool
    {
        return (bool)preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.\-]+)?$/', $version);
    }

    private function isCoolingDown(): bool
    {
        $state = $this->readState();
        $lastAttempt = (int)($state['last_attempt_at'] ?? 0);
        $lastError = trim((string)($state['last_error'] ?? ''));
        if ($lastAttempt <= 0 || $lastError === '') {
            return false;
        }

        return ($this->now - $lastAttempt) < self::RETRY_COOLDOWN_SECONDS;
    }

    /**
     * @return array<string,mixed>
     */
    private function readState(): array
    {
        if ($this->stateFile === '' || !is_file($this->stateFile)) {
            return [];
        }

        try {
            $raw = file_get_contents($this->stateFile);
            if (!is_string($raw) || $raw === '') {
                return [];
            }
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<string,mixed> $state
     */
    private function writeState(array $state): void
    {
        if ($this->stateFile === '') {
            return;
        }

        $dir = dirname($this->stateFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $json = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($json)) {
            return;
        }

        @file_put_contents($this->stateFile, $json, LOCK_EX);
    }

    private function isoNow(): string
    {
        return gmdate('c', $this->now);
    }
}

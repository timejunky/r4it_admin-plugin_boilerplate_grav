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

namespace Tests\Unit;

use Grav\Plugin\R4itAdminPluginBoilerplate\Zebra\ZebraLifecycleClient;
use Grav\Plugin\R4itAdminPluginBoilerplate\Zebra\ZebraLifecycleTracker;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/classes/Zebra/ZebraLifecycleClient.php';
require_once dirname(__DIR__, 2) . '/classes/Zebra/ZebraLifecycleTracker.php';

final class ZebraLifecycleTrackerTest extends TestCase
{
    private string $stateFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateFile = sys_get_temp_dir() . '/r4it-zebra-lifecycle-' . uniqid('', true) . '.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->stateFile)) {
            @unlink($this->stateFile);
        }
        parent::tearDown();
    }

    public function testCountsWithoutLicenseUsingInstallId(): void
    {
        $calls = [];
        $tracker = $this->tracker($calls, ['license_key' => '']);

        $status = $tracker->sync('0.2.5');

        $this->assertCount(1, $calls);
        $this->assertSame('/api/v2/lifecycle/event', $this->path($calls[0]['url']));
        $this->assertSame('install', $calls[0]['payload']['event']);
        $this->assertArrayNotHasKey('license_key', $calls[0]['payload']);
        $this->assertArrayNotHasKey('shop_url', $calls[0]['payload']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', (string)$calls[0]['payload']['install_id']);
        $this->assertSame('ok', $status['reason']);
        $this->assertSame('0.2.5', $status['reported_version']);
    }

    public function testFirstSyncSendsInstallThenUpdateCheck(): void
    {
        $calls = [];
        $tracker = $this->tracker($calls);

        $status = $tracker->sync('0.2.5');

        $this->assertCount(2, $calls);
        $this->assertSame('/api/v2/lifecycle/event', $this->path($calls[0]['url']));
        $this->assertSame('install', $calls[0]['payload']['event']);
        $this->assertSame('0.2.5', $calls[0]['payload']['current_version']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', (string)$calls[0]['payload']['install_id']);
        $this->assertArrayNotHasKey('shop_url', $calls[0]['payload']);
        $this->assertSame('/api/update-check', $this->path($calls[1]['url']));
        $this->assertSame('0.2.5', $status['reported_version']);
        $this->assertNotSame('', $status['install_sent_at']);
    }

    public function testVersionBumpSendsUpdateWithPreviousVersion(): void
    {
        $calls = [];
        $first = $this->tracker($calls);
        $first->sync('0.2.4');
        $installId = (string)$calls[0]['payload']['install_id'];
        $calls = [];

        $later = $this->tracker($calls, [], 1_700_000_000 + 10);
        $status = $later->sync('0.2.5');

        $this->assertSame('update', $calls[0]['payload']['event']);
        $this->assertSame('0.2.4', $calls[0]['payload']['previous_version']);
        $this->assertSame('0.2.5', $calls[0]['payload']['current_version']);
        $this->assertSame($installId, $calls[0]['payload']['install_id']);
        $this->assertSame('0.2.5', $status['reported_version']);
        $this->assertNotSame('', $status['last_update_at']);
    }

    public function testFailedInstallDoesNotMarkReportedVersion(): void
    {
        $calls = [];
        $tracker = $this->tracker($calls, [], 1_700_000_000, false);

        $status = $tracker->sync('0.2.5');

        $this->assertSame('', $status['reported_version']);
        $this->assertSame('lifecycle_install_failed', $status['last_error']);
        $this->assertCount(1, $calls);
    }

    public function testCooldownSkipsRetryAfterFailure(): void
    {
        $calls = [];
        $failing = $this->tracker($calls, [], 1_700_000_000, false);
        $failing->sync('0.2.5');
        $calls = [];

        $retry = $this->tracker($calls, [], 1_700_000_000 + 60, false);
        $status = $retry->sync('0.2.5');

        $this->assertSame('cooldown', $status['reason']);
        $this->assertSame([], $calls);
    }

    public function testSameVersionDoesNotResendInstall(): void
    {
        $calls = [];
        $first = $this->tracker($calls);
        $first->sync('0.2.5');
        $calls = [];

        $again = $this->tracker($calls, [], 1_700_000_000 + 10);
        $again->sync('0.2.5');

        $this->assertSame([], $calls);
    }

    /**
     * @param list<array{url:string,payload:array<string,mixed>}> $calls
     * @param array<string,mixed> $configOverride
     */
    private function tracker(
        array &$calls,
        array $configOverride = [],
        int $now = 1_700_000_000,
        bool $ok = true
    ): ZebraLifecycleTracker {
        $transport = static function (
            string $method,
            string $url,
            array $headers,
            string $body,
            int $timeout
        ) use (&$calls, $ok): array {
            $payload = json_decode($body, true);
            $calls[] = [
                'url' => $url,
                'payload' => is_array($payload) ? $payload : [],
            ];

            return ['ok' => $ok, 'status' => $ok ? 200 : 500];
        };

        $client = new ZebraLifecycleClient('http://api.dev.streamingzebra.loc', $transport);
        $config = array_merge([
            'enabled' => true,
            'module_name' => 'r4it_admin_plugin_boilerplate',
            'company_key' => 'ready-4-it',
            'license_key' => 'TEST-LICENSE-KEY-1234567890',
            'update_check_interval_hours' => 24,
        ], $configOverride);

        return new ZebraLifecycleTracker($client, $this->stateFile, $config, $now);
    }

    private function path(string $url): string
    {
        return (string)(parse_url($url, PHP_URL_PATH) ?? '');
    }
}

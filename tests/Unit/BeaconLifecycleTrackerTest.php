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

use Grav\Plugin\R4itAdminPluginBoilerplate\Beacon\BeaconLifecycleClient;
use Grav\Plugin\R4itAdminPluginBoilerplate\Beacon\BeaconLifecycleTracker;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/classes/Beacon/BeaconLifecycleClient.php';
require_once dirname(__DIR__, 2) . '/classes/Beacon/BeaconLifecycleTracker.php';

final class BeaconLifecycleTrackerTest extends TestCase
{
    private string $stateFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateFile = sys_get_temp_dir() . '/r4it-beacon-lifecycle-' . uniqid('', true) . '.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->stateFile)) {
            @unlink($this->stateFile);
        }
        parent::tearDown();
    }

    public function testCountsInstallOnFirstBoot(): void
    {
        $calls = [];
        $tracker = $this->tracker($calls);

        $status = $tracker->sync('0.3.0');

        $this->assertCount(1, $calls);
        $this->assertSame('https://login.alveos.eu/api/v1/track', $calls[0]['url']);
        $this->assertSame('install', $calls[0]['payload']['event']);
        $this->assertSame('alv_prd_boilerplate', $calls[0]['payload']['app_id']);
        $this->assertSame('grav', $calls[0]['payload']['client_type']);
        $this->assertSame('php', $calls[0]['payload']['platform']);
        $this->assertSame('0.3.0', $calls[0]['payload']['version']);
        $this->assertTrue($status['enabled']);
        $this->assertSame('0.3.0', $status['reported_version']);
    }

    public function testCountsUpdateOnVersionBump(): void
    {
        $calls = [];
        $tracker = $this->tracker($calls);

        $tracker->sync('0.3.0');
        $this->assertCount(1, $calls);

        // Same version -> no additional ping
        $tracker->sync('0.3.0');
        $this->assertCount(1, $calls);

        // Bump version -> sends update
        $status = $tracker->sync('0.4.0');
        $this->assertCount(2, $calls);
        $this->assertSame('update', $calls[1]['payload']['event']);
        $this->assertSame('0.4.0', $calls[1]['payload']['version']);
        $this->assertSame('0.4.0', $status['reported_version']);
    }

    public function testDisabledDoesNotSendBeacon(): void
    {
        $calls = [];
        $tracker = $this->tracker($calls, ['enabled' => false]);

        $status = $tracker->sync('0.3.0');

        $this->assertCount(0, $calls);
        $this->assertFalse($status['enabled']);
        $this->assertSame('disabled', $status['reason']);
    }

    /**
     * @param list<array{url:string,payload:array<string,mixed>}> $calls
     * @param array<string,mixed> $configOverride
     */
    private function tracker(array &$calls, array $configOverride = []): BeaconLifecycleTracker
    {
        $transport = static function (string $method, string $url, array $headers, string $body) use (&$calls): array {
            $calls[] = [
                'url' => $url,
                'payload' => json_decode($body, true) ?? [],
            ];
            return ['ok' => true, 'status' => 202];
        };

        $client = new BeaconLifecycleClient('https://login.alveos.eu/api/v1/track', '', $transport);

        return new BeaconLifecycleTracker(
            $client,
            $this->stateFile,
            array_merge([
                'enabled' => true,
                'app_id' => 'alv_prd_boilerplate',
                'client_type' => 'grav',
            ], $configOverride),
            1760000000
        );
    }
}

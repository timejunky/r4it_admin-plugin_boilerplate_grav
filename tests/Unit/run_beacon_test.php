<?php

declare(strict_types=1);

require_once __DIR__ . '/../../classes/Beacon/BeaconLifecycleClient.php';
require_once __DIR__ . '/../../classes/Beacon/BeaconLifecycleTracker.php';

use Grav\Plugin\R4itAdminPluginBoilerplate\Beacon\BeaconLifecycleClient;
use Grav\Plugin\R4itAdminPluginBoilerplate\Beacon\BeaconLifecycleTracker;

function assertSame($expected, $actual, string $msg): void {
    if ($expected !== $actual) {
        throw new RuntimeException("FAIL: $msg: expected " . var_export($expected, true) . ", got " . var_export($actual, true));
    }
    echo "OK: $msg\n";
}

function assertTrue($condition, string $msg): void {
    if (!$condition) {
        throw new RuntimeException("FAIL: $msg: expected true");
    }
    echo "OK: $msg\n";
}

$stateFile = sys_get_temp_dir() . '/test-beacon-' . uniqid('', true) . '.json';

try {
    $calls = [];
    $transport = static function (string $method, string $url, array $headers, string $body) use (&$calls): array {
        $calls[] = [
            'url' => $url,
            'headers' => $headers,
            'payload' => json_decode($body, true) ?? [],
        ];
        return ['ok' => true, 'status' => 202];
    };

    $client = new BeaconLifecycleClient('https://login.alveos.eu/api/v1/track', '', $transport);
    $tracker = new BeaconLifecycleTracker(
        $client,
        $stateFile,
        ['enabled' => true, 'app_id' => 'alv_prd_boilerplate', 'client_type' => 'grav'],
        1760000000
    );

    // 1. Install
    $status = $tracker->sync('0.3.0');
    assertSame(1, count($calls), 'install triggered exactly 1 call');
    assertSame('https://login.alveos.eu/api/v1/track', $calls[0]['url'], 'correct track URL');
    assertSame('install', $calls[0]['payload']['event'], 'event is install');
    assertSame('alv_prd_boilerplate', $calls[0]['payload']['app_id'], 'app_id is boilerplate');
    assertSame('0.3.0', $calls[0]['payload']['version'], 'version matches');
    assertTrue($status['enabled'], 'tracker is enabled');
    assertSame('0.3.0', $status['reported_version'], 'reported_version updated');

    // 2. Same version -> no ping
    $tracker->sync('0.3.0');
    assertSame(1, count($calls), 'same version does not re-ping');

    // 3. Update version
    $statusUpdate = $tracker->sync('0.4.0');
    assertSame(2, count($calls), 'version bump triggered update call');
    assertSame('update', $calls[1]['payload']['event'], 'event is update');
    assertSame('0.4.0', $calls[1]['payload']['version'], 'version is 0.4.0');
    assertSame('0.4.0', $statusUpdate['reported_version'], 'reported_version updated to 0.4.0');

    echo "ALL BEACON TRACKER TESTS PASSED!\n";
} finally {
    if (is_file($stateFile)) {
        @unlink($stateFile);
    }
}

<?php

/**
r4it_admin-plugin_boilerplate_grav
@category Grav_Plugin
@author Nejat P. Eryigit <https://www.ready-4-it.com>
@copyright 2026 Nejat P. Eryigit
@license https://opensource.org/licenses/MIT MIT License
@link https://github.com/timejunky/r4it_admin-plugin_boilerplate_grav
*/

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

final class PackageMetadataTest extends TestCase
{
    public function testBlueprintMetadataSupportsGravReleaseFlow(): void
    {
        $blueprintsPath = dirname(__DIR__, 2) . '/blueprints.yaml';
        $composerPath = dirname(__DIR__, 2) . '/composer.json';
        $blueprintsContent = (string)file_get_contents($blueprintsPath);

        $blueprints = $this->parseBlueprints($blueprintsPath);
        $composer = json_decode((string)file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('r4it_admin_plugin_boilerplate', $blueprints['slug']);
        $this->assertSame('0.2.2', $blueprints['version']);
        $this->assertSame('MIT', $blueprints['license']);
        $this->assertSame('https://github.com/timejunky/r4it_admin-plugin_boilerplate_grav', $blueprints['homepage']);
        $this->assertSame('timejunky/grav-plugin-r4it-admin-plugin-boilerplate', $composer['name']);
        $this->assertSame('R4IT Admin Plugin Boilerplate', $blueprints['name']);
        
        // Verify composer/runtime requirements remain installable in CI.
        $this->assertSame('>=8.1', $composer['require']['php']);

        // Verify Grav/Admin compatibility constraints from blueprints metadata.
        $this->assertStringContainsString("{ name: grav, version: '>=1.7.0' }", $blueprintsContent);
        $this->assertStringContainsString("{ name: admin, version: '>=1.10.0' }", $blueprintsContent);
    }

    private function parseBlueprints(string $filePath): array
    {
        $content = (string)file_get_contents($filePath);

        $data = [];
        foreach (['name', 'slug', 'type', 'version', 'description', 'icon', 'homepage', 'bugs', 'docs', 'license'] as $key) {
            if (preg_match('/^' . preg_quote($key, '/') . ':\s*(.+)$/m', $content, $matches) === 1) {
                $data[$key] = trim($matches[1], " \t\n\r\0\x0B\"'");
            }
        }

        return $data;
    }
}
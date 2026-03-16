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

final class LanguageFilesTest extends TestCase
{
    private string $languagesDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->languagesDir = dirname(__DIR__, 2) . '/languages';
    }

    public function testLocaleFilesExist(): void
    {
        foreach (['en', 'de', 'fr', 'pt', 'tr', 'lb'] as $locale) {
            $path = $this->languagesDir . '/' . $locale . '.yaml';
            $this->assertFileExists($path, "Missing locale file: {$locale}.yaml");
        }
    }

    public function testLocaleFilesHaveSameKeysAsEnglish(): void
    {
        $englishKeys = $this->extractTopLevelKeys($this->languagesDir . '/en.yaml');
        $this->assertNotEmpty($englishKeys, 'English locale keys could not be detected.');

        foreach (['de', 'fr', 'pt', 'tr', 'lb'] as $locale) {
            $localeKeys = $this->extractTopLevelKeys($this->languagesDir . '/' . $locale . '.yaml');
            sort($localeKeys);
            $expected = $englishKeys;
            sort($expected);

            $this->assertSame(
                $expected,
                $localeKeys,
                "Locale {$locale}.yaml does not match key set from en.yaml"
            );
        }
    }

    /**
     * Extracts first-level keys under PLUGIN_R4IT_ADMIN_PLUGIN_BOILERPLATE.
     */
    private function extractTopLevelKeys(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $this->assertIsString($content, "Unable to read locale file: {$filePath}");

        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];
        $insideSection = false;
        $keys = [];

        foreach ($lines as $line) {
            if (preg_match('/^PLUGIN_R4IT_ADMIN_PLUGIN_BOILERPLATE:\s*$/', $line)) {
                $insideSection = true;
                continue;
            }

            if (!$insideSection) {
                continue;
            }

            if (preg_match('/^[A-Z0-9_]+:\s*$/', $line)) {
                break;
            }

            if (preg_match('/^\s{2}([A-Z0-9_]+):\s*/', $line, $matches) === 1) {
                $keys[] = $matches[1];
            }
        }

        return array_values(array_unique($keys));
    }
}

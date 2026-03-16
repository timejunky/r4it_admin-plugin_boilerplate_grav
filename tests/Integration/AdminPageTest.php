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

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\TestCase;

final class AdminPageTest extends TestCase
{
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $baseUrl = getenv('BASE_URL');
        if (!$baseUrl) {
            $this->markTestSkipped('BASE_URL environment variable is not set.');
        }

        $this->client = new Client([
            'base_uri' => $baseUrl,
            'timeout' => 5.0,
            // Do not follow redirects automatically, so we can test for them.
            RequestOptions::ALLOW_REDIRECTS => false,
            // Do not throw exceptions on 4xx/5xx responses.
            RequestOptions::HTTP_ERRORS => false,
            // Ignore self-signed certificate issues in local dev.
            RequestOptions::VERIFY => false,
        ]);
    }

    /**
     * @dataProvider adminUrlProvider
     */
    public function testAdminToolUrlIsRedirectedToLogin(string $url, string $lang): void
    {
        // 1. Make a request to the plugin's admin page.
        $response = $this->client->get($url);

        // 2. Assert that we get a redirect (3xx status code).
        // A 404 would mean the route is not recognized at all.
        // A 200 would mean it's not protected by auth.
        // A redirect means the route exists and is correctly protected.
        $statusCode = $response->getStatusCode();
        $this->assertTrue(
            $statusCode >= 300 && $statusCode < 400,
            "Expected a redirect (3xx) for the {$lang} admin URL, but got {$statusCode}."
        );

        // 3. Assert that we are being redirected to the admin login page.
        $locationHeader = $response->getHeaderLine('Location');
        $this->assertStringContainsString(
            '/admin/login',
            $locationHeader,
            "Expected to be redirected to the login page for the {$lang} admin URL."
        );
    }

    public static function adminUrlProvider(): array
    {
        $segment = 'r4it-admin-plugin-boilerplate';

        return [
            'English URL' => ['/admin/' . $segment, 'English'],
            'German URL' => ['/de/admin/' . $segment, 'German'],
        ];
    }
}

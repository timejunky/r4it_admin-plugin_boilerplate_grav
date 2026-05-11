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

namespace Grav\Plugin\R4itAdminPluginBoilerplate\Admin;

use Grav\Common\Grav;
use Grav\Plugin\R4itAdminPluginBoilerplatePlugin;

class AdminPluginBoilerplateAdminController
{
    protected $grav;
    protected $plugin;

    public function __construct(Grav $grav, R4itAdminPluginBoilerplatePlugin $plugin)
    {
        $this->grav = $grav;
        $this->plugin = $plugin;
    }

    public function handleRequest(): array
    {
        $uri = $this->grav['uri'] ?? null;
        $activeTab = 'info';
        if ($uri) {
            $param = null;

            // Prefer route params: /admin/r4it-admin-plugin-boilerplate/tab:settings
            if (method_exists($uri, 'param')) {
                $param = $uri->param('tab');
            }

            // Fallback: query string: /admin/r4it-admin-plugin-boilerplate?tab=settings
            if (!$param && method_exists($uri, 'query')) {
                $param = $uri->query('tab');
            }

            if (is_string($param) && in_array($param, ['info', 'settings', 'tools'], true)) {
                $activeTab = $param;
            }
        }

        $base = '';
        try {
            $base = (string)($this->grav['base_url_relative'] ?? '');
        } catch (\Throwable $e) {
            $base = '';
        }

        return [
            'plugin_name' => $this->plugin->name,
            'admin_route' => rtrim($base, '/') . '/admin/' . $this->plugin->getAdminToolRoute(),
            'active_tab' => $activeTab,
            'tabs' => [
                'info'     => 'PLUGIN_R4IT_ADMIN_PLUGIN_BOILERPLATE.TAB_INFO',
                'settings' => 'PLUGIN_R4IT_ADMIN_PLUGIN_BOILERPLATE.TAB_SETTINGS',
                'tools'    => 'PLUGIN_R4IT_ADMIN_PLUGIN_BOILERPLATE.TAB_TOOLS',
            ],
            // Add other data for your template here
        ];
    }
}

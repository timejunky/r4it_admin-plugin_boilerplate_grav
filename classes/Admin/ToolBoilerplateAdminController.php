<?php

declare(strict_types=1);

namespace Grav\Plugin\R4itToolBoilerplate\Admin;

use Grav\Common\Grav;
use Grav\Plugin\R4itToolBoilerplatePlugin;

class ToolBoilerplateAdminController
{
    protected $grav;
    protected $plugin;

    public function __construct(Grav $grav, R4itToolBoilerplatePlugin $plugin)
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

            // Prefer route params: /admin/r4it-tool/tab:settings
            if (method_exists($uri, 'param')) {
                $param = $uri->param('tab');
            }

            // Fallback: query string: /admin/r4it-tool?tab=settings
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
            'admin_route' => rtrim($base, '/') . '/admin/r4it-tool',
            'active_tab' => $activeTab,
            'tabs' => [
                'info' => 'Info',
                'settings' => 'Settings',
                'tools' => 'Tools',
            ],
            // Add other data for your template here
        ];
    }
}

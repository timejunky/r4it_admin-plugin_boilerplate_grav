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

namespace Grav\Plugin;

use Grav\Common\Plugin;

class R4itAdminPluginBoilerplatePlugin extends Plugin
{
    public const REPO_URL = 'https://www.ready-4-it.com/r4it_admin_plugin_boilerplate';

    protected function getLogoUrl(): string
    {
        return (string)$this->config->get(
            'plugins.r4it_admin_plugin_boilerplate.logo_url',
            '/user/plugins/r4it_admin_plugin_boilerplate/admin/assets/logo.svg'
        );
    }

    public function getAdminToolRoute(): string
    {
        return (string)$this->config->get(
            'plugins.r4it_admin_plugin_boilerplate.admin_tool_route',
            'r4it-admin-plugin-boilerplate'
        );
    }

    private function getPluginVersion(): string
    {
        try {
            $version = $this->blueprints()->get('version');
            if (is_string($version) && $version !== '') {
                return $version;
            }
        } catch (\Throwable $e) {
            // fall through to fallback
        }

        return 'unknown';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
        ];
    }

    public function onPluginsInitialized(): void
    {
        // Merge plugin translations early (best-effort).
        try {
            $lang = $this->grav['language'] ?? null;
            if ($lang && method_exists($lang, 'mergeLanguageDir')) {
                $lang->mergeLanguageDir(__DIR__ . '/languages');
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $uri = $this->grav['uri'] ?? null;
        $path = '';
        try {
            if ($uri && method_exists($uri, 'path')) {
                $path = trim((string)$uri->path(), '/');
            }
        } catch (\Throwable $e) {
            $path = '';
        }

        // isAdmin() can misdetect language-prefixed admin URLs like /de/admin/...
        // Detect the current request path as a fallback and only enable the
        // interceptor for actual admin requests.
        $this->syncZebraLifecycle();

        $isAdminLikeRequest = $this->isAdmin() || (bool)preg_match('#^(?:[a-z]{2}(?:-[a-z]{2})?/)?admin(?:/|$)#i', $path);

        if ($isAdminLikeRequest) {
            $this->enable([
                'onAdminMenu' => ['onAdminMenu', 0],
                // Admin collects its Twig paths via the onAdminTwigTemplatePaths event.
                'onAdminTwigTemplatePaths' => ['onAdminTwigTemplatePaths', 0],
                // Register Twig loader paths/namespaces.
                'onTwigLoader' => ['onTwigLoader', 0],
                // Inject tool-page variables only after Admin has populated Twig vars/assets.
                // AdminPlugin::onTwigSiteVariables runs at priority 1000.
                'onTwigSiteVariables' => ['onTwigSiteVariables', 1100],
            ]);
        }
    }

    public function onTwigLoader(): void
    {
        try {
            $twig = $this->grav['twig'] ?? null;
            if (!$twig || !method_exists($twig, 'twig')) {
                return;
            }

            $templatesDir = __DIR__ . '/admin/templates';
            if (!is_dir($templatesDir)) {
                return;
            }

            $loader = $twig->twig()->getLoader();
            if (!$loader) {
                return;
            }

            $addPaths = static function ($fsLoader) use ($templatesDir): void {
                if ($fsLoader && method_exists($fsLoader, 'addPath')) {
                    // Default path.
                    $fsLoader->addPath($templatesDir);
                    // Namespace path used by @r4it_admin_plugin_boilerplate/...
                    $fsLoader->addPath($templatesDir, 'r4it_admin_plugin_boilerplate');
                }
            };

            if ($loader instanceof \Twig\Loader\ChainLoader) {
                foreach ($loader->getLoaders() as $subLoader) {
                    if ($subLoader instanceof \Twig\Loader\FilesystemLoader) {
                        $addPaths($subLoader);
                    }
                }
                return;
            }

            if ($loader instanceof \Twig\Loader\FilesystemLoader) {
                $addPaths($loader);
                return;
            }
        } catch (\Throwable $e) {
            // ignore loader errors; admin page will fail loudly if templates cannot be found
        }
    }

    public function onTwigSiteVariables(): void
    {
        $uri = $this->grav['uri'] ?? null;
        if (!$uri || !method_exists($uri, 'path')) {
            return;
        }

        $path = trim((string)$uri->path(), '/');
        $twig = $this->grav['twig'] ?? null;
        $location = null;
        try {
            if ($twig && isset($twig->twig_vars['location'])) {
                $location = $twig->twig_vars['location'];
            }
        } catch (\Throwable $e) {
            $location = null;
        }

        // Prefer Admin's own parsed location (template segment) when available.
        // Fallback to path matching for language-prefixed URLs.
        $toolRoute = $this->getAdminToolRoute();
        $isToolRoute = (
            $location === $toolRoute
            || $path === 'admin/' . $toolRoute
            || (bool)preg_match('#^[a-z]{2}(?:-[a-z]{2})?/admin/' . preg_quote($toolRoute, '#') . '$#i', $path)
        );

        if (!$isToolRoute) {
            return;
        }

        // Provide variables for the admin page template (rendered by the normal Admin pipeline).
        require_once __DIR__ . '/classes/Admin/AdminPluginBoilerplateAdminController.php';
        $controller = new \Grav\Plugin\R4itAdminPluginBoilerplate\Admin\AdminPluginBoilerplateAdminController($this->grav, $this);
        $data = $controller->handleRequest();

        if (is_array($data)) {
            $data['r4it_admin_plugin_boilerplate_repo_url'] = self::REPO_URL;
            $data['r4it_admin_plugin_boilerplate_logo_url'] = $this->getLogoUrl();
            $data['r4it_admin_plugin_boilerplate_version'] = $this->getPluginVersion();
            $data['r4it_admin_plugin_boilerplate_zebra'] = $this->zebraLifecycleStatus();
        }

        if ($twig && isset($twig->twig_vars) && is_array($twig->twig_vars) && is_array($data)) {
            $twig->twig_vars = array_merge($twig->twig_vars, $data);
        }
    }

    public function onAdminMenu(): void
    {
        try {
            $twig = $this->grav['twig'] ?? null;
            if (!$twig || !isset($twig->plugins_hooked_nav) || !is_array($twig->plugins_hooked_nav)) {
                return;
            }

            $label = 'r4it Admin Plugin Boilerplate';
            try {
                $language = $this->grav['language'] ?? null;
                $translated = null;
                if ($language && method_exists($language, 'translate')) {
                    $translated = $language->translate('PLUGIN_R4IT_ADMIN_PLUGIN_BOILERPLATE.ADMIN_MENU_LABEL');
                }
                if (is_string($translated) && trim($translated) !== '') {
                    $label = $translated;
                }
            } catch (\Throwable $e) {
                // ignore
            }

            // IMPORTANT: Use a dedicated admin tool route to avoid the "Plugins" sidebar
            // being marked active at the same time (which happens under /admin/plugins/...).
            $toolRoute = $this->getAdminToolRoute();
            $twig->plugins_hooked_nav[$label] = [
                'route' => '/' . $toolRoute,
                'icon'  => 'fa-flask',
                'class' => str_replace(['_', ' '], '-', strtolower($toolRoute)),
            ];
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function syncZebraLifecycle(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        try {
            $this->zebraTracker()->sync($this->getPluginVersion());
        } catch (\Throwable $e) {
            // fail-open: counting must never block Grav
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function zebraLifecycleStatus(): array
    {
        try {
            return $this->zebraTracker()->status();
        } catch (\Throwable $e) {
            return [
                'enabled' => false,
                'configured' => false,
                'reason' => 'unavailable',
                'module_name' => '',
                'reported_version' => '',
                'install_sent_at' => '',
                'last_update_at' => '',
                'last_update_check_at' => 0,
                'last_error' => '',
            ];
        }
    }

    private function zebraTracker(): \Grav\Plugin\R4itAdminPluginBoilerplate\Zebra\ZebraLifecycleTracker
    {
        require_once __DIR__ . '/classes/Zebra/ZebraLifecycleClient.php';
        require_once __DIR__ . '/classes/Zebra/ZebraLifecycleTracker.php';

        $client = new \Grav\Plugin\R4itAdminPluginBoilerplate\Zebra\ZebraLifecycleClient(
            $this->zebraApiBaseUrl()
        );

        return new \Grav\Plugin\R4itAdminPluginBoilerplate\Zebra\ZebraLifecycleTracker(
            $client,
            $this->zebraStateFile(),
            $this->zebraConfig(),
            time()
        );
    }

    /**
     * @return array{
     *   enabled:bool,
     *   api_base_url:string,
     *   module_name:string,
     *   company_key:string,
     *   license_key:string,
     *   update_check_interval_hours:int
     * }
     */
    private function zebraConfig(): array
    {
        $cfg = $this->config->get('plugins.r4it_admin_plugin_boilerplate.zebra', []);
        if (!is_array($cfg)) {
            $cfg = [];
        }

        return [
            'enabled' => (bool)($cfg['enabled'] ?? true),
            'api_base_url' => $this->zebraApiBaseUrl(),
            'module_name' => trim((string)($cfg['module_name'] ?? 'r4it_admin_plugin_boilerplate')),
            'company_key' => trim((string)($cfg['company_key'] ?? 'ready-4-it')),
            'license_key' => trim((string)$this->config->get('plugins.r4it_admin_plugin_boilerplate.license_key', '')),
            'update_check_interval_hours' => (int)($cfg['update_check_interval_hours'] ?? 24),
        ];
    }

    private function zebraApiBaseUrl(): string
    {
        $base = rtrim(trim((string)$this->config->get(
            'plugins.r4it_admin_plugin_boilerplate.zebra.api_base_url',
            ''
        )), '/');
        if ($base !== '') {
            return $base;
        }

        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        if (str_contains($host, 'dev.ready-4-it') || str_contains($host, '.loc')) {
            return 'http://api.dev.streamingzebra.loc';
        }

        return 'https://api.streamingzebra.com';
    }

    private function zebraStateFile(): string
    {
        try {
            $locator = $this->grav['locator'] ?? null;
            if ($locator && method_exists($locator, 'findResource')) {
                $dataDir = $locator->findResource('user://data', true, true);
                if (is_string($dataDir) && $dataDir !== '') {
                    return rtrim($dataDir, '/\\') . DIRECTORY_SEPARATOR
                        . 'r4it_admin_plugin_boilerplate' . DIRECTORY_SEPARATOR
                        . 'zebra-lifecycle.json';
                }
            }
        } catch (\Throwable $e) {
            // fall through
        }

        $root = defined('GRAV_ROOT') ? (string)GRAV_ROOT : dirname(__DIR__, 3);

        return $root . '/user/data/r4it_admin_plugin_boilerplate/zebra-lifecycle.json';
    }

    public function onAdminTwigTemplatePaths($event): void
    {
        if (!isset($event['paths'])) {
            return;
        }

        $paths = $event['paths'];
        if (!is_array($paths)) {
            return;
        }

        // Ensure templates are found even if other plugins add paths later.
        array_unshift($paths, __DIR__ . '/admin/templates');
        $event['paths'] = $paths;
    }
}


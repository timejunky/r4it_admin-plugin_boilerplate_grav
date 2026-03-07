<?php

declare(strict_types=1);

namespace Grav\Plugin;

use Grav\Common\Plugin;

class R4itToolBoilerplatePlugin extends Plugin
{
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
                    // Namespace path used by @r4it_tool_boilerplate/...
                    $fsLoader->addPath($templatesDir, 'r4it_tool_boilerplate');
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
        $isToolRoute = (
            $location === 'r4it-tool'
            || $path === 'admin/r4it-tool'
            || (bool)preg_match('#^[a-z]{2}(?:-[a-z]{2})?/admin/r4it-tool$#i', $path)
        );

        if (!$isToolRoute) {
            return;
        }

        // Provide variables for the admin page template (rendered by the normal Admin pipeline).
        require_once __DIR__ . '/classes/Admin/ToolBoilerplateAdminController.php';
        $controller = new \Grav\Plugin\R4itToolBoilerplate\Admin\ToolBoilerplateAdminController($this->grav, $this);
        $data = $controller->handleRequest();

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

            $label = 'r4it Tool (Boilerplate)';
            try {
                $language = $this->grav['language'] ?? null;
                $translated = null;
                if ($language && method_exists($language, 'translate')) {
                    $translated = $language->translate('PLUGIN_R4IT_TOOL_BOILERPLATE.ADMIN_MENU_LABEL');
                }
                if (is_string($translated) && trim($translated) !== '') {
                    $label = $translated;
                }
            } catch (\Throwable $e) {
                // ignore
            }

            // IMPORTANT: Use a dedicated admin tool route to avoid the "Plugins" sidebar
            // being marked active at the same time (which happens under /admin/plugins/...).
            $twig->plugins_hooked_nav[$label] = [
                'route' => '/r4it-tool',
                'icon' => 'fa-wrench',
                'class' => 'r4it-tool-boilerplate',
            ];
        } catch (\Throwable $e) {
            // ignore
        }
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

<!--
r4it_admin-plugin_boilerplate_grav
@category Grav_Plugin
@author Nejat P. Eryigit <https://www.ready-4-it.com>
@copyright 2026 Nejat P. Eryigit
@license https://opensource.org/licenses/MIT MIT License
@link https://github.com/timejunky/r4it_admin-plugin_boilerplate_grav
-->

# Architecture

This document explains why the plugin is structured the way it is and how to extend it safely.

## Pattern: GrayGate

The plugin uses a GrayGate-style split:

- Gateway plugin class: `r4it_admin_plugin_boilerplate.php`
- Request/data controller: `classes/Admin/AdminPluginBoilerplateAdminController.php`
- Presentation: Twig templates in `admin/templates/`

Why this pattern:

- Keeps request detection and Grav event wiring in one place.
- Keeps business/request logic out of the plugin bootstrap file.
- Keeps Twig templates focused on rendering, not routing logic.
- Makes future feature growth easier (new tabs/tools can be added with less risk).

## Request Flow

```mermaid
flowchart TD
    A[Admin click: sidebar item] --> B[/admin/r4it-admin-plugin-boilerplate]
    B --> C[onPluginsInitialized]
    C --> D[Admin-aware events enabled]
    D --> E[onTwigSiteVariables route check]
    E --> F[AdminPluginBoilerplateAdminController::handleRequest]
    F --> G[Twig vars merged]
    G --> H[Template renders tabs/content]
```

Runtime sequence (simplified):

1. Admin menu entry is registered in `onAdminMenu()`.
2. Request enters the normal Grav Admin pipeline.
3. `onTwigSiteVariables()` recognizes the tool route.
4. Controller resolves active tab and returns data.
5. Data is merged into Twig variables.
6. `r4it-admin-plugin-boilerplate.html.twig` renders the page.

## Routing Logic: Language-Prefix Safe

Problem in Grav setups with multilingual URLs:

- URLs like `/de/admin/...` may not always be recognized by a plain `isAdmin()` check.

Solution used in this plugin:

- Defensive admin detection in `onPluginsInitialized()` by combining:
  - `isAdmin()`
  - path regex fallback that allows optional language prefix
- Tool-route detection in `onTwigSiteVariables()` by combining:
  - Admin `location` value when available
  - explicit path checks with and without language prefix

This dual check prevents false negatives on multilingual admin URLs.

## Extension Points

Use these extension points when building your own tool from this boilerplate:

- Add/adjust tabs:
  - Controller: `handleRequest()` tab whitelist and tab data
  - Template: `admin/templates/r4it-admin-plugin-boilerplate.html.twig`
- Add plugin-side variables:
  - `onTwigSiteVariables()` before Twig merge
- Add custom routes/actions:
  - Extend controller methods and pass action state to Twig
- Add menu behavior:
  - `onAdminMenu()` (label, icon, route)
- Add styles:
  - `admin/assets/admin-plugin-boilerplate.css`

## Design Principles

- Keep gateway/event code stable.
- Move request logic into controller classes.
- Keep templates declarative and predictable.
- Prefer explicit route checks over implicit assumptions.
- Fail safely in admin bootstrapping (guard/try-catch around optional services).

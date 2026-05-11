<!--
r4it_admin-plugin_boilerplate_grav
@category Grav_Plugin
@author Nejat P. Eryigit <https://www.ready-4-it.com>
@copyright 2026 Nejat P. Eryigit
@license https://opensource.org/licenses/MIT MIT License
@link https://github.com/timejunky/r4it_admin-plugin_boilerplate_grav
-->

# r4it Admin Plugin Boilerplate (Grav)

![r4it Admin Plugin Boilerplate Logo](admin/assets/logo.svg)

![Version](https://img.shields.io/badge/version-0.2.2-blue) ![License](https://img.shields.io/badge/license-MIT-green) ![PHP](https://img.shields.io/badge/PHP-%3E%3D8.1-8892BF) ![Grav](https://img.shields.io/badge/Grav-%3E%3D1.7.0-orange)

This is a minimal **Method 2 (GrayGate-style)** admin tool-page boilerplate.

- Adds an Admin sidebar entry via `onAdminMenu()`.
- Provides a robust, language-prefix-safe Admin tool route via `onTwigSiteVariables()`.
- Renders a tabbed Twig UI using the normal Admin pipeline.

Default route:
- `/admin/r4it-admin-plugin-boilerplate` (also works with language prefix like `/de/admin/r4it-admin-plugin-boilerplate`)

## Grav Package Readiness

This repository is prepared so Grav can read the package metadata consistently:

- `blueprints.yaml` contains the plugin name, slug, version, homepage, bugs link, docs link, license, keywords, and author metadata.
- `CHANGELOG.md` uses versioned release sections so the release history can be matched to Git tags.
- `composer.json` uses the Grav plugin package naming convention.

To publish an update through the regular Grav update flow, keep these three values in sync for every release:

1. `blueprints.yaml` version
2. `CHANGELOG.md` release heading
3. Git tag and GitHub release name

Example release flow:

```bash
git tag -a v0.2.1 -m "Release v0.2.1"
git push origin v0.2.1
```

Then create a GitHub Release with the same `v0.2.1` tag and submit or update the package entry in the Grav package list if needed.

## Support (MIT Community Edition)

- For support, please use Grav community channels and this repository's public GitHub issues/discussions.
- This MIT/community edition does not include private 1:1 support.

## Internationalization (i18n)

Supported admin locales:

- en
- de
- fr
- pt
- tr
- lb

Locale files are stored separately in the languages directory:

- languages/en.yaml
- languages/de.yaml
- languages/fr.yaml
- languages/pt.yaml
- languages/tr.yaml
- languages/lb.yaml

How to add a new locale:

1. Copy languages/en.yaml to languages/<locale>.yaml.
2. Keep the same key structure under PLUGIN_R4IT_ADMIN_PLUGIN_BOILERPLATE.
3. Translate values only, not keys.
4. Purge Grav cache so changes are visible in Admin.

## Documentation and Resources

- [ARCHITECTURE.md](ARCHITECTURE.md) - Technical blueprint, GrayGate pattern, request flow, and extension points.
- [CONTRIBUTING.md](CONTRIBUTING.md) - How to report issues and contribute improvements.
- [CHANGELOG.md](CHANGELOG.md) - Project history in Keep a Changelog format.
- [SECURITY.md](SECURITY.md) - Private vulnerability disclosure process.

## Branding Asset

- Canonical logo for this product: `admin/assets/logo.svg`
- Use this logo in product-related pages and materials where branding is needed.

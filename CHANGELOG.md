<!--
r4it_admin-plugin_boilerplate_grav
@category Grav_Plugin
@author Nejat P. Eryigit <https://www.ready-4-it.com>
@copyright 2026 Nejat P. Eryigit
@license https://opensource.org/licenses/MIT MIT License
@link https://github.com/timejunky/r4it_admin-plugin_boilerplate_grav
-->

# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog,
and this project follows Semantic Versioning.

## [Unreleased]

## [0.2.1] - 2026-05-11

### Added

- Grav package metadata now includes license, bugs, docs, keywords, and author homepage fields.
- Composer package name aligned with the plugin repository naming convention.
- Release checklist documented for version/tag parity before submission to the Grav package list.

### Changed

- Admin sidebar icon changed from wrench to flask.
- Info tab now shows current plugin version and community support notice.
- Support/security/docs text aligned for MIT community support channels.

- `ARCHITECTURE.md` with GrayGate pattern rationale, request flow, routing strategy, and extension points.
- `CONTRIBUTING.md` with issue/PR and local validation guidance.
- `SECURITY.md` with private vulnerability disclosure process.
- Repository-level copyright/license headers for plugin files.

### Added

- Repository-local branding asset at `admin/assets/logo.svg` and logo usage in admin/README.
- New locale files for `fr`, `pt`, `tr`, and `lb`.
- Language key consistency integration test (`LanguageFilesTest`).

## [0.2.0] - 2026-05-10

### Added

- GrayGate-style admin tool-page baseline.
- Controller-based request handling for admin tab routing.
- Multilingual/language-prefix-aware admin route matching.
- Tabbed admin Twig view (`info`, `settings`, `tools`).

## [0.1.1] - 2026-05-09

### Changed

- Improved admin route stability for real-world Grav admin contexts.
- Documentation and structure refinements.

## [0.1.0] - 2026-05-08

### Added

- Initial boilerplate release.

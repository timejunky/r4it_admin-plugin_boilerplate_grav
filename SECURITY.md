<!--
r4it_admin-plugin_boilerplate_grav
@category Grav_Plugin
@author Nejat P. Eryigit <https://www.ready-4-it.com>
@copyright 2026 Nejat P. Eryigit
@license https://opensource.org/licenses/MIT MIT License
@link https://github.com/timejunky/r4it_admin-plugin_boilerplate_grav
-->

# Security Policy

## Supported Versions

Security fixes are provided for the latest maintained version on `main`.

## Reporting a Vulnerability

For the MIT/community edition, general support belongs to the Grav community and public GitHub discussions/issues.

Please do not open public 



























GitHub issues for suspected security vulnerabilities.

Private disclosure channel for security-only reports:

- GitHub Security Advisory (preferred): https://github.com/timejunky/r4it_admin-plugin_boilerplate_grav/security/advisories

Please include:

- Affected version/commit
- Reproduction steps or proof-of-concept
- Impact assessment
- Suggested mitigation (if known)

## Response Process

- Initial acknowledgement target: within 3 business days
- Triage and severity assessment
- Coordinated fix and release notes
- Public disclosure after a fix is available (when appropriate)

## Scope Notes

This repository is a Grav Admin boilerplate. Risks can come from:

- route handling in admin context
- Twig rendering data flow
- added custom tool actions from downstream adopters

Downstream projects extending this boilerplate are responsible for validating their own custom business logic and input handling.

## Support Policy (MIT Edition)

- Community support: Grav community forums and GitHub issues/discussions.
- No private 1:1 support is included with this MIT/community edition.

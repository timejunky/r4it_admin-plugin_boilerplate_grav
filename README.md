# r4it Tool Boilerplate (Grav)

This is a minimal **Method 2 (GrayGate-style)** admin tool-page boilerplate.

- Adds an Admin sidebar entry via `onAdminMenu()`.
- Intercepts the tool route via `onPageInitialized`.
- Renders Twig and terminates with `echo` + `exit` to avoid 404/virtual-page issues.

Default route:
- Canonical: `/admin/plugins/r4it_tool_boilerplate` (also works with language prefix like `/de/admin/plugins/r4it_tool_boilerplate`)
- Compatibility redirect: `/admin/r4it-tool` → `/admin/plugins/r4it_tool_boilerplate`

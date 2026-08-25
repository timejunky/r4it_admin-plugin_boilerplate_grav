<!--
r4it_admin-plugin_boilerplate_grav
@category Grav_Plugin
@author Nejat P. Eryigit <https://www.ready-4-it.com>
@copyright 2026 Nejat P. Eryigit
@license https://opensource.org/licenses/MIT MIT License
@link https://www.ready-4-it.com/r4it_admin_plugin_boilerplate
-->

# Renaming Guide: Grav Admin Plugin Boilerplate

This guide explains how to rename the `r4it_admin_plugin_boilerplate` scaffold to create your own custom Grav admin plugin.

---

## 1. Naming Conventions Matrix

When renaming the plugin, four distinct casing conventions are used across the codebase:

| Variant | Pattern Example | Description & Usage |
| :--- | :--- | :--- |
| **Folder & File Slug** (`snake_case`) | `r4it_my_custom_tool` | Directory name, main `.php`/`.yaml` files, internal keys. |
| **Route & Twig Slug** (`kebab-case`) | `r4it-my-custom-tool` | Admin URL route (`/admin/r4it-my-custom-tool`), Twig template files, CSS filenames. |
| **PHP Class / Namespace** (`PascalCase`) | `R4itMyCustomTool` | PHP Class names (`R4itMyCustomTool`, `R4itMyCustomToolAdminController`). |
| **Language Translation Key** (`UPPERCASE`) | `PLUGIN_R4IT_MY_CUSTOM_TOOL` | Prefix for language dictionary entries in `languages/*.yaml`. |

---

## 2. Automated Renaming (Recommended)

Automated scripts are available in the `scripts/` directory.

### Using PowerShell (Windows)

```powershell
.\scripts\rename-plugin.ps1 -NewName "r4it_my_custom_tool"
```

### Using Bash (Linux / macOS)

```bash
chmod +x ./scripts/rename-plugin.sh
./scripts/rename-plugin.sh r4it_my_custom_tool
```

---

## 3. Manual Renaming Checklist

If you prefer to rename manually, follow these 7 steps:

### Step 1: Directory & Core Files
1. Rename the plugin folder:
   - `r4it_admin_plugin_boilerplate/` ➔ `r4it_my_custom_tool/`
2. Rename core configuration & entry files:
   - `r4it_admin_plugin_boilerplate.php` ➔ `r4it_my_custom_tool.php`
   - `r4it_admin_plugin_boilerplate.yaml` ➔ `r4it_my_custom_tool.yaml`

### Step 2: Main PHP File (`r4it_my_custom_tool.php`)
- Update class declaration:
  - `class R4itAdminPluginBoilerplate extends Plugin` ➔ `class R4itMyCustomTool extends Plugin`
- Update administrative menu route in `onAdminMenu()`:
  - `'route' => 'r4it-my-custom-tool'`
- Update Twig route detection in `onTwigSiteVariables()`:
  - Change `'r4it-admin-plugin-boilerplate'` checks to `'r4it-my-custom-tool'`.

### Step 3: Controller & Namespace (`classes/Admin/`)
- Rename controller file:
  - `classes/Admin/AdminPluginBoilerplateAdminController.php` ➔ `classes/Admin/MyCustomToolAdminController.php`
- Update class & namespace references inside the file.

### Step 4: Admin Templates, Pages & Assets
- Rename Twig template:
  - `admin/templates/r4it-admin-plugin-boilerplate.html.twig` ➔ `admin/templates/r4it-my-custom-tool.html.twig`
- Rename admin page file:
  - `admin/pages/r4it-admin-plugin-boilerplate.md` ➔ `admin/pages/r4it-my-custom-tool.md`
- Rename CSS asset file:
  - `admin/assets/admin-plugin-boilerplate.css` ➔ `admin/assets/my-custom-tool.css`

### Step 5: Internationalization (`languages/*.yaml`)
- In `languages/en.yaml`, `languages/de.yaml`, etc., update the root translation key:
  - `PLUGIN_R4IT_ADMIN_PLUGIN_BOILERPLATE:` ➔ `PLUGIN_R4IT_MY_CUSTOM_TOOL:`

### Step 6: Metadata (`blueprints.yaml` & `composer.json`)
- In `blueprints.yaml`:
  - Update `name:` to your product title.
- In `composer.json`:
  - Update `"name"` and autoload PSR-4 namespace mapping.

### Step 7: Clear Grav Cache
Run the Grav CLI cache clearer or purge via Admin UI:
```bash
bin/grav clear-cache
```


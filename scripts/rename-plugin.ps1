<#
r4it_admin-plugin_boilerplate_grav
@category Grav_Plugin
@author Nejat P. Eryigit <https://www.ready-4-it.com>
@copyright 2026 Nejat P. Eryigit
@license https://opensource.org/licenses/MIT MIT License
@link https://www.ready-4-it.com/r4it_admin_plugin_boilerplate

.SYNOPSIS
    Automated Renaming Script for Grav Admin Plugin Boilerplate (PowerShell)
.DESCRIPTION
    Renames r4it_admin_plugin_boilerplate files, class names, namespaces, Twig templates,
    and translation keys to your custom plugin name.
.EXAMPLE
    .\scripts\rename-plugin.ps1 -NewName "r4it_my_custom_plugin"
#>

[CmdletBinding()]
param (
    [Parameter(Mandatory = $true, HelpMessage = "New plugin name in snake_case (e.g. r4it_my_plugin)")]
    [string]$NewName
)

$ErrorActionPreference = "Stop"

# Determine root plugin directory
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$PluginDir = Resolve-Path (Join-Path $ScriptDir "..") | Select-Object -ExpandProperty Path

Set-Location $PluginDir

Write-Host "=====================================================" -ForegroundColor Cyan
Write-Host " Grav Plugin Boilerplate Renamer (PowerShell)        " -ForegroundColor Cyan
Write-Host " Target Directory: $PluginDir                        " -ForegroundColor Cyan
Write-Host "=====================================================" -ForegroundColor Cyan

# Ensure clean input (strip whitespace/slashes)
$NewSnake = $NewName.Trim().ToLower() -replace '[^\w_]', ''

# Check format
if ($NewSnake -notmatch '^[a-z0-9_]+$') {
    Write-Error "Invalid plugin name '$NewName'. Must contain only alphanumeric characters and underscores."
}

# Generate casing variants
# 1. snake_case: r4it_my_plugin
# 2. kebab-case: r4it-my-plugin
$NewKebab = $NewSnake -replace '_', '-'

# 3. UPPERCASE: PLUGIN_R4IT_MY_PLUGIN
$NewUpper = ("PLUGIN_" + $NewSnake).ToUpper()

# 4. PascalCase: R4itMyPlugin
$Parts = $NewSnake -split '_'
$PascalParts = foreach ($p in $Parts) {
    if ($p.Length -gt 0) {
        $p.Substring(0, 1).ToUpper() + $p.Substring(1)
    }
}
$NewPascal = $PascalParts -join ''

Write-Host "New Casing Variants:" -ForegroundColor Yellow
Write-Host "  snake_case:  $NewSnake"
Write-Host "  kebab-case:  $NewKebab"
Write-Host "  PascalCase:  $NewPascal"
Write-Host "  UPPERCASE:   $NewUpper"
Write-Host ""

# Define replacements
$OldSnake = "r4it_admin_plugin_boilerplate"
$OldKebab = "r4it-admin-plugin-boilerplate"
$OldPascal = "R4itAdminPluginBoilerplate"
$OldUpper = "PLUGIN_R4IT_ADMIN_PLUGIN_BOILERPLATE"

# Step 1: Replace text in all text files
$Extensions = "*.php", "*.yaml", "*.yml", "*.md", "*.twig", "*.css", "*.json", "*.dist"
$FilesToProcess = Get-ChildItem -Path $PluginDir -Recurse -Include $Extensions | Where-Object {
    $_.FullName -notmatch '\\\.git\\' -and $_.FullName -notmatch '\\vendor\\'
}

Write-Host "[1/3] Replacing text occurrences across files..." -ForegroundColor Green
foreach ($file in $FilesToProcess) {
    $content = Get-Content -Path $file.FullName -Raw -Encoding UTF8
    if ([string]::IsNullOrEmpty($content)) { continue }

    $updated = $content `
        -replace [regex]::Escape($OldUpper), $NewUpper `
        -replace [regex]::Escape($OldPascal), $NewPascal `
        -replace [regex]::Escape($OldSnake), $NewSnake `
        -replace [regex]::Escape($OldKebab), $NewKebab

    if ($updated -ne $content) {
        Set-Content -Path $file.FullName -Value $updated -Encoding UTF8
        Write-Host "  Updated: $($file.FullName.Replace($PluginDir, ''))" -ForegroundColor Gray
    }
}

# Step 2: Rename files
Write-Host "[2/3] Renaming files..." -ForegroundColor Green

# Core PHP file
$OldPhp = Join-Path $PluginDir "r4it_admin_plugin_boilerplate.php"
if (Test-Path $OldPhp) {
    Rename-Item -Path $OldPhp -NewName "$NewSnake.php"
    Write-Host "  Renamed: r4it_admin_plugin_boilerplate.php -> $NewSnake.php" -ForegroundColor Gray
}

# Core YAML config
$OldYaml = Join-Path $PluginDir "r4it_admin_plugin_boilerplate.yaml"
if (Test-Path $OldYaml) {
    Rename-Item -Path $OldYaml -NewName "$NewSnake.yaml"
    Write-Host "  Renamed: r4it_admin_plugin_boilerplate.yaml -> $NewSnake.yaml" -ForegroundColor Gray
}

# Twig template
$OldTwig = Join-Path $PluginDir "admin\templates\r4it-admin-plugin-boilerplate.html.twig"
if (Test-Path $OldTwig) {
    Rename-Item -Path $OldTwig -NewName "$NewKebab.html.twig"
    Write-Host "  Renamed: admin/templates/r4it-admin-plugin-boilerplate.html.twig -> $NewKebab.html.twig" -ForegroundColor Gray
}

# Admin Page MD
$OldMd = Join-Path $PluginDir "admin\pages\r4it-admin-plugin-boilerplate.md"
if (Test-Path $OldMd) {
    Rename-Item -Path $OldMd -NewName "$NewKebab.md"
    Write-Host "  Renamed: admin/pages/r4it-admin-plugin-boilerplate.md -> $NewKebab.md" -ForegroundColor Gray
}

# CSS file
$OldCss = Join-Path $PluginDir "admin\assets\admin-plugin-boilerplate.css"
if (Test-Path $OldCss) {
    $NewCssName = $NewKebab -replace '^r4it-', ''
    $NewCssName = "$NewCssName.css"
    Rename-Item -Path $OldCss -NewName $NewCssName
    Write-Host "  Renamed: admin/assets/admin-plugin-boilerplate.css -> $NewCssName" -ForegroundColor Gray
}

# Controller class
$OldController = Join-Path $PluginDir "classes\Admin\AdminPluginBoilerplateAdminController.php"
if (Test-Path $OldController) {
    $NewControllerName = ($NewPascal -replace '^R4it', '') + "AdminController.php"
    Rename-Item -Path $OldController -NewName $NewControllerName
    Write-Host "  Renamed: classes/Admin/AdminPluginBoilerplateAdminController.php -> $NewControllerName" -ForegroundColor Gray
}

# Step 3: Rename parent directory if run from within
Write-Host "[3/3] Renaming completed!" -ForegroundColor Green
Write-Host ""
Write-Host "IMPORTANT NEXT STEPS:" -ForegroundColor Yellow
Write-Host "1. Rename parent directory '$OldSnake' to '$NewSnake' if desired."
Write-Host "2. Run 'grav clear-cache' to apply changes."
Write-Host "Done!" -ForegroundColor Cyan


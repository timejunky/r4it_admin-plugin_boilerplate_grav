#!/usr/bin/env bash
#
# r4it_admin-plugin_boilerplate_grav
# @category Grav_Plugin
# @author Nejat P. Eryigit <https://www.ready-4-it.com>
# @copyright 2026 Nejat P. Eryigit
# @license https://opensource.org/licenses/MIT MIT License
# @link https://www.ready-4-it.com/r4it_admin_plugin_boilerplate
#
# Automated Renaming Script for Grav Admin Plugin Boilerplate (Bash)
#
# Usage:
#   chmod +x ./scripts/rename-plugin.sh
#   ./scripts/rename-plugin.sh r4it_my_custom_plugin
#

set -e

NEW_NAME="$1"

if [ -z "$NEW_NAME" ]; then
    echo "Error: Missing new plugin name argument."
    echo "Usage: ./scripts/rename-plugin.sh r4it_my_custom_plugin"
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

cd "$PLUGIN_DIR"

echo "====================================================="
echo " Grav Plugin Boilerplate Renamer (Bash)              "
echo " Target Directory: $PLUGIN_DIR                        "
echo "====================================================="

NEW_SNAKE=$(echo "$NEW_NAME" | tr '[:upper:]' '[:lower:]' | tr -cd 'a-z0-9_')

if [[ ! "$NEW_SNAKE" =~ ^[a-z0-9_]+$ ]]; then
    echo "Error: Invalid plugin name '$NEW_NAME'."
    exit 1
fi

NEW_KEBAB=$(echo "$NEW_SNAKE" | tr '_' '-')
NEW_UPPER="PLUGIN_$(echo "$NEW_SNAKE" | tr '[:lower:]' '[:upper:]')"

# Convert snake_case to PascalCase
NEW_PASCAL=""
IFS='_' read -ra PARTS <<< "$NEW_SNAKE"
for part in "${PARTS[@]}"; do
    if [ -n "$part" ]; then
        FIRST=$(echo "${part:0:1}" | tr '[:lower:]' '[:upper:]')
        REST="${part:1}"
        NEW_PASCAL="${NEW_PASCAL}${FIRST}${REST}"
    fi
done

OLD_SNAKE="r4it_admin_plugin_boilerplate"
OLD_KEBAB="r4it-admin-plugin-boilerplate"
OLD_PASCAL="R4itAdminPluginBoilerplate"
OLD_UPPER="PLUGIN_R4IT_ADMIN_PLUGIN_BOILERPLATE"

echo "New Casing Variants:"
echo "  snake_case:  $NEW_SNAKE"
echo "  kebab-case:  $NEW_KEBAB"
echo "  PascalCase:  $NEW_PASCAL"
echo "  UPPERCASE:   $NEW_UPPER"
echo ""

echo "[1/3] Replacing text occurrences across files..."
find "$PLUGIN_DIR" -type f \( -name "*.php" -o -name "*.yaml" -o -name "*.yml" -o -name "*.md" -o -name "*.twig" -o -name "*.css" -o -name "*.json" -o -name "*.dist" \) \
    ! -path "*/.git/*" ! -path "*/vendor/*" | while read -r file; do
    if [ -f "$file" ]; then
        sed -i "s/$OLD_UPPER/$NEW_UPPER/g" "$file" 2>/dev/null || true
        sed -i "s/$OLD_PASCAL/$NEW_PASCAL/g" "$file" 2>/dev/null || true
        sed -i "s/$OLD_SNAKE/$NEW_SNAKE/g" "$file" 2>/dev/null || true
        sed -i "s/$OLD_KEBAB/$NEW_KEBAB/g" "$file" 2>/dev/null || true
    fi
done

echo "[2/3] Renaming files..."

if [ -f "$PLUGIN_DIR/r4it_admin_plugin_boilerplate.php" ]; then
    mv "$PLUGIN_DIR/r4it_admin_plugin_boilerplate.php" "$PLUGIN_DIR/$NEW_SNAKE.php"
fi

if [ -f "$PLUGIN_DIR/r4it_admin_plugin_boilerplate.yaml" ]; then
    mv "$PLUGIN_DIR/r4it_admin_plugin_boilerplate.yaml" "$PLUGIN_DIR/$NEW_SNAKE.yaml"
fi

if [ -f "$PLUGIN_DIR/admin/templates/r4it-admin-plugin-boilerplate.html.twig" ]; then
    mv "$PLUGIN_DIR/admin/templates/r4it-admin-plugin-boilerplate.html.twig" "$PLUGIN_DIR/admin/templates/$NEW_KEBAB.html.twig"
fi

if [ -f "$PLUGIN_DIR/admin/pages/r4it-admin-plugin-boilerplate.md" ]; then
    mv "$PLUGIN_DIR/admin/pages/r4it-admin-plugin-boilerplate.md" "$PLUGIN_DIR/admin/pages/$NEW_KEBAB.md"
fi

CSS_SHORT="${NEW_KEBAB#r4it-}"
if [ -f "$PLUGIN_DIR/admin/assets/admin-plugin-boilerplate.css" ]; then
    mv "$PLUGIN_DIR/admin/assets/admin-plugin-boilerplate.css" "$PLUGIN_DIR/admin/assets/$CSS_SHORT.css"
fi

CONTROLLER_SHORT="${NEW_PASCAL#R4it}"
if [ -f "$PLUGIN_DIR/classes/Admin/AdminPluginBoilerplateAdminController.php" ]; then
    mv "$PLUGIN_DIR/classes/Admin/AdminPluginBoilerplateAdminController.php" "$PLUGIN_DIR/classes/Admin/${CONTROLLER_SHORT}AdminController.php"
fi

echo "[3/3] Renaming completed!"
echo ""
echo "IMPORTANT NEXT STEPS:"
echo "1. Rename parent directory '$OLD_SNAKE' to '$NEW_SNAKE' if desired."
echo "2. Run 'grav clear-cache' to apply changes."
echo "Done!"


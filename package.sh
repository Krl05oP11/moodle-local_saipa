#!/usr/bin/env bash
# package.sh — Build a clean release ZIP for local_saipa.
#
# Usage:
#   ./package.sh          # reads version from version.php
#   ./package.sh 0.5.1    # override version label
#
# Output: ../local_saipa-<version>.zip  (one level up from the plugin dir)

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_NAME="local_saipa"

# Read version from version.php if not passed as argument.
if [[ -n "${1:-}" ]]; then
    VERSION="$1"
else
    VERSION=$(grep -oP "\\\$plugin->release\s*=\s*'\K[^']+" "$PLUGIN_DIR/version.php")
fi

OUTPUT_DIR="$(dirname "$PLUGIN_DIR")"
ZIP_NAME="${PLUGIN_NAME}-${VERSION}.zip"
TEMP_DIR=$(mktemp -d)

echo "==> Packaging $PLUGIN_NAME v$VERSION"

# Copy plugin to a temp directory with the correct folder name.
cp -a "$PLUGIN_DIR" "$TEMP_DIR/$PLUGIN_NAME"

# Remove files that must NOT be in the release ZIP.
cd "$TEMP_DIR/$PLUGIN_NAME"
rm -rf \
    .git .github .gitignore \
    node_modules vendor \
    tests/behat tests/*.php \
    package.sh \
    phpcs.xml.dist \
    pix/screenshots

# Remove markdown files except README.md and CHANGES.md.
find . -maxdepth 1 -name '*.md' ! -name 'README.md' ! -name 'CHANGES.md' -delete

# Remove any OS junk files.
find "$TEMP_DIR/$PLUGIN_NAME" -name '.DS_Store' -o -name 'Thumbs.db' | xargs rm -f 2>/dev/null || true

# Build the ZIP.
cd "$TEMP_DIR"
zip -r -q "$OUTPUT_DIR/$ZIP_NAME" "$PLUGIN_NAME"

# Cleanup.
rm -rf "$TEMP_DIR"

echo "==> Created: $OUTPUT_DIR/$ZIP_NAME"
echo "    Size: $(du -h "$OUTPUT_DIR/$ZIP_NAME" | cut -f1)"

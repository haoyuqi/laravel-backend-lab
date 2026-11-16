#!/bin/sh
# Create .gz and .br siblings for static assets that are generated at image
# build time (Filament publishes its runtime assets during the build, so they
# cannot be compressed by the Node stage).
#
# Usage: precompress.sh <dir> [<dir> ...]
set -eu

find "$@" -type f \( -name '*.js' -o -name '*.css' -o -name '*.svg' \) | while IFS= read -r file; do
    gzip -9 -k "$file"
    brotli -q 11 -k -f "$file"
done

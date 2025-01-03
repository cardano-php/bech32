#!/bin/bash

set -e

COVERAGE_DIR="coverage"

find "$COVERAGE_DIR" -type f -name "*.html" -exec sed -i \
    -e 's|_css/|css/|g' \
    -e 's|_js/|js/|g' \
    -e 's|_icons/|icons/|g' \
    -e 's|/home/runner/work/bech32/bech32|.|g' {} \;

echo "Links in PHPUnit HTML files updated successfully."

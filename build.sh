#!/usr/bin/env bash
# Generates a clean distribution zip that respects .gitattributes export-ignore rules.
# Usage: bash build.sh
# Output: competitor-price-stock-monitor-<version>.zip (in the repo root)

set -euo pipefail

SLUG="competitor-price-stock-monitor"
VERSION=$(grep "^Stable tag:" readme.txt | awk '{print $3}')

if [ -z "$VERSION" ]; then
  echo "Error: could not read Stable tag from readme.txt" >&2
  exit 1
fi

OUTPUT="${SLUG}-${VERSION}.zip"

git archive HEAD \
  --format=zip \
  --prefix="${SLUG}/" \
  --output="${OUTPUT}"

echo "Built ${OUTPUT}"
echo "Verify contents: unzip -l ${OUTPUT} | grep vendor && echo 'FAIL: vendor leaked' || echo 'OK: vendor excluded'"

#!/usr/bin/env bash
#
# Runs the integration tests against the EspoCRM instance in `site`.
#
# Prerequisites (once): `npm run all` (or `npm run prepare-test` + installation),
# `(cd site; grunt test)` and `site/tests/integration/config.php` with test database credentials.
#
# The test instance is created from `site/build/EspoCRM-*`. This script copies the current
# extension sources there, so `grunt test` does not need to be re-run after every change.

set -euo pipefail

cd "$(dirname "$0")/.."

npm run sync

BUILD_DIR=$(ls -d site/build/EspoCRM-* | sort -V | tail -n 1)

rm -rf "$BUILD_DIR/custom/Espo/Modules/PublicHolidays" "$BUILD_DIR/client/custom/modules/public-holidays"
mkdir -p "$BUILD_DIR/custom/Espo/Modules" "$BUILD_DIR/client/custom/modules"
cp -r site/custom/Espo/Modules/PublicHolidays "$BUILD_DIR/custom/Espo/Modules/"
cp -r site/client/custom/modules/public-holidays "$BUILD_DIR/client/custom/modules/"

cd site

php vendor/bin/phpunit tests/integration/Espo/Modules/PublicHolidays "$@"

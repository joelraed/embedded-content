#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
export TEST_LOGIN_URL
auth_uri=http://web
TEST_LOGIN_URL=$(docker compose exec -T web vendor/bin/drush uli --name="${BROWSER_TEST_ACCOUNT:-admin}" --uri="$auth_uri" --no-browser)
docker compose run --rm -T --pull never browser node node_modules/playwright/cli.js test "$@"

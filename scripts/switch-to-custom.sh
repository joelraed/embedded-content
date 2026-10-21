#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p backups
backup_name="baseline-$(date +%Y%m%d-%H%M%S).sql.gz"
docker compose exec -T web vendor/bin/drush sql:dump --gzip --result-file=/tmp/embedded-baseline.sql >/dev/null
docker compose cp web:/tmp/embedded-baseline.sql.gz "backups/$backup_name"
docker compose exec -T web vendor/bin/drush php:script /var/www/scripts/disable-baseline.php
docker compose exec -T web vendor/bin/drush pm:uninstall embedded_content_examples embedded_content -y
docker compose exec -T web vendor/bin/drush en inside_embedded_content inside_embedded_content_examples -y
docker compose exec -T web vendor/bin/drush php:script /var/www/scripts/custom.php
docker compose exec -T web vendor/bin/drush cr

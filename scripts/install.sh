#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
if [ ! -f .env ]; then echo 'Create .env from .env.example with random passwords first.'; exit 1; fi
docker compose up -d --build
docker compose exec -T web composer install --no-interaction
if [ ! -f drupal/web/sites/default/settings.php ]; then cp scripts/settings.php drupal/web/sites/default/settings.php; fi
if docker compose exec -T web vendor/bin/drush status --field=bootstrap | grep -q Successful; then
  echo 'Drupal already installed; leaving database unchanged.'
else
  docker compose exec -T web vendor/bin/drush site:install standard --site-name='Inside Embedded Content' --account-name=admin -y
fi
if docker compose exec -T web vendor/bin/drush php:eval 'exit(\Drupal::moduleHandler()->moduleExists("inside_embedded_content") ? 0 : 1);'; then
  echo 'Custom implementation already enabled. Leaving baseline modules disabled.'
  exit 0
fi
docker compose exec -T web vendor/bin/drush en embedded_content embedded_content_examples -y
docker compose exec -T web vendor/bin/drush php:script /var/www/html/../scripts/baseline.php

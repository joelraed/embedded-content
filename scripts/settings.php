<?php
/** Local Docker settings, copied by scripts/install.sh. No embedded credentials. */
$databases['default']['default'] = [
  'database' => getenv('DB_NAME') ?: 'embedded_content',
  'username' => getenv('DB_USER') ?: 'drupal',
  'password' => getenv('DB_PASSWORD'),
  'host' => getenv('DB_HOST') ?: 'db',
  'port' => '3306',
  'driver' => 'mysql',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
];
$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT');
$settings['config_sync_directory'] = '/var/www/config/sync';
$settings['trusted_host_patterns'] = ['^localhost$', '^127\\.0\\.0\\.1$', '^web$'];
$settings['file_private_path'] = '/var/www/html/private';

<?php

// phpcs:ignoreFile

/**
 * @file
 * Stjernekig — Drupal site settings.
 *
 * This file is mounted into web/sites/default/settings.php by docker-compose.
 * Secrets come from the .env file at the repo root.
 */

$databases = [];

$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT') ?: 'change-me-stjernekig-dev-only';

$settings['update_free_access'] = FALSE;

$settings['file_scan_ignore_directories'] = [
  'node_modules',
  'bower_components',
];

$settings['entity_update_batch_size'] = 50;
$settings['entity_update_backup'] = TRUE;

$settings['migrate_node_migrate_type_classic'] = FALSE;

$databases['default']['default'] = [
  'driver' => 'mysql',
  'database' => getenv('MYSQL_DATABASE'),
  'username' => getenv('MYSQL_USER'),
  'password' => getenv('MYSQL_PASSWORD'),
  'host' => getenv('MYSQL_HOST'),
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'prefix' => '',
  'init_commands' => [
    'isolation_level' => "SET SESSION transaction_isolation='READ-COMMITTED'",
  ],
];

$settings['file_temp_path'] = '/var/www/temp-files';
$settings['file_private_path'] = '/var/www/private-files';

$settings['config_sync_directory'] = '../config/sync';

// Trust the Dory proxy host header so Drupal generates correct absolute URLs.
$settings['trusted_host_patterns'] = [
  '^stjernekig\.docker$',
  '^localhost$',
];

if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}

// Larger memory + no timeout for CLI (drush) commands.
if (PHP_SAPI === 'cli') {
  ini_set('memory_limit', '2500M');
  ini_set('max_execution_time', 0);
}

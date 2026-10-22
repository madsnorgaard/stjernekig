<?php

// phpcs:ignoreFile

/**
 * @file
 * Drupal settings for Stjernekig. Everything secret comes from the environment.
 *
 * Copied into web/sites/default/settings.php by the Dockerfile and bind-mounted
 * over it by docker-compose so edits apply without a rebuild.
 */

$databases['default']['default'] = [
  'database' => getenv('DB_NAME'),
  'username' => getenv('DB_USER'),
  'password' => getenv('DB_PASS'),
  'host' => getenv('DB_HOST') ?: 'stjernekig_db',
  'port' => getenv('DB_PORT') ?: '3306',
  'prefix' => '',
  'driver' => 'mysql',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
  'init_commands' => [
    'isolation_level' => "SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED",
  ],
];

$settings['hash_salt'] = getenv('HASH_SALT') ?: '';
$settings['update_free_access'] = FALSE;
$settings['config_sync_directory'] = '../config/sync';
$settings['file_private_path'] = '/opt/drupal/private';
$settings['file_temp_path'] = '/tmp';
$settings['file_scan_ignore_directories'] = ['node_modules', 'bower_components'];
$settings['entity_update_batch_size'] = 50;
$settings['entity_update_backup'] = TRUE;
$settings['migrate_node_migrate_type_classic'] = FALSE;
$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';

// Hostnames Drupal may be addressed as, comma separated regexes.
foreach (array_filter(array_map('trim', explode(',', (string) getenv('TRUSTED_HOST_PATTERNS')))) as $pattern) {
  $settings['trusted_host_patterns'][] = $pattern;
}
if (getenv('DOMAIN_NAME')) {
  $settings['trusted_host_patterns'][] = '^' . preg_quote((string) getenv('DOMAIN_NAME'), '/') . '$';
}

// Behind Traefik: honour X-Forwarded-* so URLs come out https.
$settings['reverse_proxy'] = TRUE;
$settings['reverse_proxy_addresses'] = [$_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'];
$settings['reverse_proxy_trusted_headers'] = \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_FOR
  | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_HOST
  | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PORT
  | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PROTO;

// The source can be overridden per environment without touching config.
if (getenv('APOD_SOURCE_BASE_URL')) {
  $config['stjernekig.settings']['source']['base_url'] = getenv('APOD_SOURCE_BASE_URL');
}

if (getenv('DRUPAL_ENV') === 'local') {
  $settings['trusted_host_patterns'][] = '^localhost$';
  $settings['trusted_host_patterns'][] = '^127\.0\.0\.1$';
  $settings['trusted_host_patterns'][] = '^stjernekig\.localhost$';
  $config['system.logging']['error_level'] = 'verbose';
  $config['system.performance']['css']['preprocess'] = FALSE;
  $config['system.performance']['js']['preprocess'] = FALSE;
  $settings['cache']['bins']['render'] = 'cache.backend.null';
  $settings['cache']['bins']['page'] = 'cache.backend.null';
  $settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
  $settings['container_yamls'][] = DRUPAL_ROOT . '/sites/development.services.yml';
  $settings['skip_permissions_hardening'] = TRUE;
}

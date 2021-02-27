<?php

// @codingStandardsIgnoreFile

use Drupal\Core\Installer\InstallerKernel;

$settings['config_sync_directory'] = '../config';
$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT');
$settings['deployment_identifier'] = getenv('DEPLOYMENT_IDENTIFIER') ?: \Drupal::VERSION;
$settings['class_loader_auto_detect'] = FALSE;
$settings['file_chmod_directory'] = 0775;
$settings['file_chmod_file'] = 0664;
$settings['file_private_path'] = '../private';

$settings['file_scan_ignore_directories'] = [
  'node_modules',
  'bower_components',
  'vendor',
];

$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';
$settings['entity_update_batch_size'] = 50;
$settings['entity_update_backup'] = TRUE;

if (isset($_ENV['REDIS_HOST']) && extension_loaded('redis')) {
  if (empty(getenv('DDEV_PHP_VERSION')) && !InstallerKernel::installationAttempted()) {
    // Set Redis as the default backend for any cache bin not otherwise specified.
    $settings['cache']['default'] = 'cache.backend.redis';
    $settings['redis.connection']['host'] = $_ENV['REDIS_HOST'];
    $settings['redis.connection']['port'] = $_ENV['REDIS_PORT'];
    // Apply changes to the container configuration to better leverage Redis.
    // This includes using Redis for the lock and flood control systems, as well
    // as the cache tag checksum. Alternatively, copy the contents of that file
    // to your project-specific services.yml file, modify as appropriate, and
    // remove this line.
    $settings['container_yamls'][] = 'modules/contrib/redis/example.services.yml';
    // Allow the services to work before the Redis module itself is enabled.
    $settings['container_yamls'][] = 'modules/contrib/redis/redis.services.yml';
    // Manually add the classloader path, this is required for the container cache bin definition below
    // and allows to use it without the redis module being enabled.
    $class_loader->addPsr4('Drupal\\redis\\', 'modules/contrib/redis/src');
    // Use redis for container cache.
    // The container cache is used to load the container definition itself, and
    // thus any configuration stored in the container itself is not available
    // yet. These lines force the container cache to use Redis rather than the
    // default SQL cache.
    $settings['bootstrap_container_definition'] = [
      'parameters' => [],
      'services' => [
        'redis.factory' => [
          'class' => 'Drupal\redis\ClientFactory',
        ],
        'cache.backend.redis' => [
          'class' => 'Drupal\redis\Cache\CacheBackendFactory',
          'arguments' => ['@redis.factory', '@cache_tags_provider.container', '@serialization.phpserialize'],
        ],
        'cache.container' => [
          'class' => '\Drupal\redis\Cache\PhpRedis',
          'factory' => ['@cache.backend.redis', 'get'],
          'arguments' => ['container'],
        ],
        'cache_tags_provider.container' => [
          'class' => 'Drupal\redis\Cache\RedisCacheTagsChecksum',
          'arguments' => ['@redis.factory'],
        ],
        'serialization.phpserialize' => [
          'class' => 'Drupal\Component\Serialization\PhpSerialize',
        ],
      ],
    ];
    // Set a fixed prefix so that all requests share the same prefix, even if
    // on different domains.
    $settings['cache_prefix'] = 'prefix_';
  }
}

if (!empty($_ENV['DRUPAL_DATABASE_HOST'])) {
  $databases['default']['default'] = [
    'driver' => 'mysql',
    'database' => $_ENV['DRUPAL_DATABASE_NAME'],
    'username' => $_ENV['DRUPAL_DATABASE_USERNAME'],
    'password' => $_ENV['DRUPAL_DATABASE_PASSWORD'],
    'host' => $_ENV['DRUPAL_DATABASE_HOST'],
    'port' => $_ENV['DRUPAL_DATABASE_PORT'],
  ];
}

// Reverse proxy on App Platform.
// Stolen from trusted_reverse_proxy. We should get this in core...
if (isset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR'])) {
  $settings['reverse_proxy'] = TRUE;

  // First hop is assumed to be a reverse proxy in its own right.
  $proxies = [$_SERVER['REMOTE_ADDR']];
  // We may be further behind another reverse proxy (e.g., Traefik, Varnish)
  // Commas may or may not be followed by a space.
  // @see https://tools.ietf.org/html/rfc7239#section-7.1
  $forwardedFor = explode(
    ',',
    str_replace(', ', ',', $_SERVER['HTTP_X_FORWARDED_FOR'])
  );
  if (count($forwardedFor) > 1) {
    // The first value will be the actual client IP.
    array_shift($forwardedFor);
    array_unshift($proxies, ...$forwardedFor);
  }

  $settings['reverse_proxy_addresses'] = $proxies;
}

// Automatic inclusion of DDEV settings.
if (file_exists($app_root . '/' . $site_path . '/settings.ddev.php')) {
  include $app_root . '/' . $site_path . '/settings.ddev.php';
}

// Automatic inclusion of Lando settings.
if (file_exists($app_root . '/' . $site_path . '/settings.lando.php')) {
  include $app_root . '/' . $site_path . '/settings.lando.php';
}

// Automatic inclusion of local settings.
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
   include $app_root . '/' . $site_path . '/settings.local.php';
}

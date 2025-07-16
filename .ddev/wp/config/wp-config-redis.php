<?php
/** #ddev-generated
 * 
 * Configures redis for use with WordPress
 */

!defined('WP_REDIS_CONFIG')
&&
define('WP_REDIS_CONFIG', [
    'token' => str_repeat(',',60),
    'host' => 'redis',
    'username' => 'redis',
    'password' => 'redis',
    'port' => 6379,
    'database' => 0, // change for each site
    'timeout' => 0.5,
    'read_timeout' => 0.5,
    'retry_interval' => 10,
    'retries' => 3,
    'backoff' => 'smart',
    'compression' => 'zstd', // `zstd` compresses smaller, `lz4` compresses faster
    'serializer' => 'igbinary',
    'async_flush' => true,
    'split_alloptions' => true,
    'prefetch' => true,
    'strict' => true,
    'debug' => false,
    'save_commands' => false,
    'prefetch' => true,
]);
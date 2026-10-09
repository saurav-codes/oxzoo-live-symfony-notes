<?php

use App\Kernel;

// Read APP_ENV and APP_DEBUG from the process environment whatever the
// SAPI puts in $_SERVER, and never fall back to dev on a server.
$_SERVER['APP_ENV'] ??= getenv('APP_ENV') ?: 'prod';
$_SERVER['APP_DEBUG'] ??= getenv('APP_DEBUG') ?: ('prod' === $_SERVER['APP_ENV'] ? '0' : '1');

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};

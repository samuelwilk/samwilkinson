<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Ensure test environment is set before loading .env files
// PHPUnit sets $_SERVER['APP_ENV'] = 'test', but we need $_ENV too
if (isset($_SERVER['APP_ENV'])) {
    $_ENV['APP_ENV'] = $_SERVER['APP_ENV'];
}

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

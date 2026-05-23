<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Support alternate env file (e.g. ZENCO_ENV_FILE=.env.saas for SaaS dev server)
$envFile = $_SERVER['ZENCO_ENV_FILE'] ?? getenv('ZENCO_ENV_FILE') ?: '.env';
Dotenv::createImmutable(dirname(__DIR__), $envFile)->load();

/** @var \Slim\App $app */
$app = require __DIR__ . '/../src/bootstrap/app.php';

$app->run();

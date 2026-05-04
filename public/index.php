<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

Dotenv::createImmutable(dirname(__DIR__))->load();

/** @var \Slim\App $app */
$app = require __DIR__ . '/../src/bootstrap/app.php';

$app->run();

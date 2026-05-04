<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;

$baseDir = dirname(__DIR__);

if (file_exists($baseDir . '/.env.testing')) {
    Dotenv::createImmutable($baseDir, '.env.testing')->load();
} else {
    Dotenv::createImmutable($baseDir)->load();
}

error_reporting(E_ALL);

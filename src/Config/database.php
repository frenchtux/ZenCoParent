<?php
declare(strict_types=1);

return [
    'connection' => $_ENV['DB_CONNECTION'] ?? 'pgsql',
    'host'       => $_ENV['DB_HOST']       ?? 'postgres',
    'port'       => (int) ($_ENV['DB_PORT'] ?? 5432),
    'database'   => $_ENV['DB_DATABASE']   ?? 'zencoparent',
    'username'   => $_ENV['DB_USERNAME']   ?? '',
    'password'   => $_ENV['DB_PASSWORD']   ?? '',
    'file'       => $_ENV['DB_FILE']       ?? 'storage/database.sqlite',
];

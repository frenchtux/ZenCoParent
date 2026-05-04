<?php
declare(strict_types=1);

return [
    'host'     => $_ENV['REDIS_HOST']     ?? 'redis',
    'port'     => (int) ($_ENV['REDIS_PORT'] ?? 6379),
    'password' => $_ENV['REDIS_PASSWORD'] !== 'null' ? ($_ENV['REDIS_PASSWORD'] ?? null) : null,
    'database' => (int) ($_ENV['REDIS_DB']  ?? 0),
];

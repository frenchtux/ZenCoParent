<?php
declare(strict_types=1);

return [
    'name'    => $_ENV['APP_NAME']  ?? 'ZenCoParent',
    'env'     => $_ENV['APP_ENV']   ?? 'production',
    'mode'    => $_ENV['APP_MODE']  ?? 'saas',
    'debug'   => filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
    'secret'  => $_ENV['APP_SECRET'] ?? '',
    'url'     => $_ENV['APP_URL']   ?? 'http://localhost',
];

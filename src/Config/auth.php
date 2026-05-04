<?php
declare(strict_types=1);

return [
    'jwt_secret'       => $_ENV['JWT_SECRET']         ?? '',
    'jwt_expiry'       => (int) ($_ENV['JWT_EXPIRY']  ?? 3600),
    'refresh_expiry'   => (int) ($_ENV['JWT_REFRESH_EXPIRY'] ?? 2592000),
    'csrf_secret'      => $_ENV['CSRF_SECRET']        ?? '',
    'google' => [
        'client_id'     => $_ENV['GOOGLE_CLIENT_ID']      ?? '',
        'client_secret' => $_ENV['GOOGLE_CLIENT_SECRET']  ?? '',
        'redirect_uri'  => $_ENV['GOOGLE_REDIRECT_URI']   ?? '',
    ],
    'rate_limit' => [
        'requests' => (int) ($_ENV['RATE_LIMIT_REQUESTS'] ?? 60),
        'window'   => (int) ($_ENV['RATE_LIMIT_WINDOW']   ?? 60),
    ],
];

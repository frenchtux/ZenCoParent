<?php
/**
 * PHP built-in server router.
 * Serves static files directly; routes everything else through index.php.
 * Usage: php -S localhost:8080 -t public/ public/router.php
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve existing static files directly (HTML, CSS, JS, images…)
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Redirect bare / to the frontend login page
if ($uri === '/') {
    header('Location: /frontend/index.html');
    exit;
}

// Everything else → Slim API
require __DIR__ . '/index.php';

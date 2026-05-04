<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Slim\Middleware\ErrorMiddleware;

$containerBuilder = new ContainerBuilder();

// Cache DI in production
$appConfig = require __DIR__ . '/../Config/app.php';
if ($appConfig['env'] === 'production') {
    $containerBuilder->enableCompilation(__DIR__ . '/../../storage/cache/di');
}

$dependencies = require __DIR__ . '/dependencies.php';
$dependencies($containerBuilder);

$container = $containerBuilder->build();
AppFactory::setContainer($container);
$app = AppFactory::create();

// Add routing
$app->addRoutingMiddleware();

// Add body parsing
$app->addBodyParsingMiddleware();

// Load routes
(require __DIR__ . '/../Api/Routes/api.php')($app);

// Error middleware (last = outermost)
$errorMiddleware = $app->addErrorMiddleware(
    displayErrorDetails: $appConfig['debug'],
    logErrors: true,
    logErrorDetails: $appConfig['debug'],
);

// Custom error handler returning JSON
$errorMiddleware->setDefaultErrorHandler(
    new \ZenCoParent\Api\Middleware\JsonErrorHandler($app->getCallableResolver(), $app->getResponseFactory())
);

return $app;

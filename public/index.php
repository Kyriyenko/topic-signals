<?php

declare(strict_types=1);

use DI\Bridge\Slim\Bridge;

$container = require dirname(__DIR__) . '/bootstrap/app.php';

$app = Bridge::create($container);

$app->get('/health', function ($request, $response) {
    $response->getBody()->write(json_encode(['status' => 'ok']));

    return $response->withHeader('Content-Type', 'application/json');
});

// Application routes are registered here as they are implemented (step 3 onward).

$app->run();

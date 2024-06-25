<?php

use Bramus\Router\Router;
use App\Infrastructure\Controllers\ImageController;

require __DIR__ . '/vendor/autoload.php';

$router = new Router();

// Define routes
$router->post('/upload', function() {
    $psr17Factory = new Nyholm\Psr7\Factory\Psr17Factory();
    $creator = new Nyholm\Psr7Server\ServerRequestCreator(
        $psr17Factory,
        $psr17Factory,
        $psr17Factory,
        $psr17Factory
    );

    $request = $creator->fromGlobals();
    $controller = new ImageController();
    $response = $controller->upload($request);

    http_response_code($response->getStatusCode());
    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header(sprintf('%s: %s', $name, $value), false);
        }
    }
    echo $response->getBody();
});

// Run the router
$router->run();

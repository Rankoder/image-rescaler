<?php

use Bramus\Router\Router;
use App\Infrastructure\Controllers\HealthController;
use App\Infrastructure\Controllers\ImageController;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;

require __DIR__ . '/vendor/autoload.php';

$router = new Router();

$emit = function (ResponseInterface $response): void {
    http_response_code($response->getStatusCode());
    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header(sprintf('%s: %s', $name, $value), false);
        }
    }
    echo $response->getBody();
};

// Define routes
$router->get('/health', function () use ($emit) {
    $psr17Factory = new Psr17Factory();

    $emit((new HealthController($psr17Factory, $psr17Factory))->check());
});

$router->post('/upload', function () use ($emit) {
    $psr17Factory = new Psr17Factory();
    $creator = new Nyholm\Psr7Server\ServerRequestCreator(
        $psr17Factory,
        $psr17Factory,
        $psr17Factory,
        $psr17Factory,
    );

    $request = $creator->fromGlobals();
    $controller = new ImageController();

    $emit($controller->upload($request));
});

// Run the router
$router->run();

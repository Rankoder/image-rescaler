<?php

declare(strict_types=1);

use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Container;
use Bramus\Router\Router;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;

require __DIR__ . '/vendor/autoload.php';

$container = new Container(AppConfig::fromEnvironment(getenv(), __DIR__));
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

$router->get('/health', function () use ($container, $emit) {
    $emit($container->healthController()->check());
});

$router->post('/upload', function () use ($container, $emit) {
    $factory = $container->httpFactory();
    $request = (new ServerRequestCreator($factory, $factory, $factory, $factory))->fromGlobals();

    $emit($container->imageController()->upload($request));
});

$notFound = function () use ($container, $emit) {
    $emit($container->notFoundController()->respond());
};

// Registered last, so it only catches what no route above matched. It also gives every common
// method at least one route: bramus/router reads an undefined array key for a method without any.
$router->all('/.*', $notFound);
$router->set404($notFound);

$router->run();

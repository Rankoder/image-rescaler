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

$router->get('/images', function () use ($container, $emit) {
    $emit($container->imagesController()->list());
});

$router->post('/images', function () use ($container, $emit) {
    $factory = $container->httpFactory();
    $request = (new ServerRequestCreator($factory, $factory, $factory, $factory))->fromGlobals();

    $emit($container->imagesController()->add($request));
});

$router->get('/files/([^/]+)', function (string $name) use ($container, $emit) {
    $emit($container->fileController()->show($name));
});

$router->set404(function () use ($container, $emit) {
    $emit($container->notFoundController()->respond());
});

$allowedMethods = ['GET', 'POST', 'HEAD'];
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (
    !in_array($method, $allowedMethods, true)
    || ($method === 'POST' && (
        !in_array($router->getRequestMethod(), $allowedMethods, true)
        || in_array($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? '', ['PUT', 'DELETE', 'PATCH'], true)
    ))
) {
    $emit($container->jsonResponses()->methodNotAllowed());

    return;
}

$router->run();

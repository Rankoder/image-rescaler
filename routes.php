<?php

declare(strict_types=1);

use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Container;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;

require __DIR__ . '/vendor/autoload.php';

$container = new Container(AppConfig::fromEnvironment(getenv(), __DIR__));
$factory = $container->httpFactory();
$request = (new ServerRequestCreator($factory, $factory, $factory, $factory))->fromGlobals();

$emit = function (ResponseInterface $response): void {
    http_response_code($response->getStatusCode());
    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header(sprintf('%s: %s', $name, $value), false);
        }
    }
    echo $response->getBody();
};

$emit($container->httpKernel()->handle($request));

<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Controllers\ImageController;
use Nyholm\Psr7Server\ServerRequestCreator;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * Creates a ServerRequest from global variables and returns it.
 *
 * @return \Psr\Http\Message\ServerRequestInterface
 */
function createServerRequest(): \Psr\Http\Message\ServerRequestInterface
{
    $psr17Factory = new Psr17Factory();
    $creator = new ServerRequestCreator(
        $psr17Factory,
        $psr17Factory,
        $psr17Factory,
        $psr17Factory
    );

    return $creator->fromGlobals();
}

/**
 * Sends the HTTP response headers and body.
 *
 * @param \Psr\Http\Message\ResponseInterface $response
 * @return void
 */
function sendResponse(\Psr\Http\Message\ResponseInterface $response): void
{
    http_response_code($response->getStatusCode());
    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header(sprintf('%s: %s', $name, $value), false);
        }
    }
    echo $response->getBody();
}

// Create a ServerRequest from globals
$request = createServerRequest();

// Instantiate the ImageController and process the request
$controller = new ImageController();
$response = $controller->upload($request);

// Send the HTTP response
sendResponse($response);

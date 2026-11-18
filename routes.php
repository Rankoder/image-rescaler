<?php

declare(strict_types=1);

use App\Application\Services\OutputFilename;
use App\Application\Services\ResizeAndStoreImage;
use App\Application\Services\StorageServiceFactory;
use App\Application\Validation\ResizeRequestValidator;
use App\Infrastructure\Image\InterventionImageResizer;
use App\Infrastructure\Image\LocalImageInspector;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Bramus\Router\Router;
use Dotenv\Dotenv;
use App\Infrastructure\Controllers\HealthController;
use App\Infrastructure\Controllers\ImageController;
use App\Infrastructure\Controllers\NotFoundController;
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

    // As before this change, an upload fails without a .env file
    Dotenv::createImmutable(__DIR__)->load();

    $request = $creator->fromGlobals();
    $controller = new ImageController(
        new ResizeAndStoreImage(
            new ResizeRequestValidator(new LocalImageInspector()),
            new OutputFilename(),
            new InterventionImageResizer(new ImageManager(new GdDriver()), sys_get_temp_dir()),
            new StorageServiceFactory(),
        ),
        $psr17Factory,
        $psr17Factory,
        ini_parse_quantity((string) ini_get('post_max_size')),
    );

    $emit($controller->upload($request));
});

$notFound = function () use ($emit) {
    $psr17Factory = new Psr17Factory();

    $emit((new NotFoundController($psr17Factory, $psr17Factory))->respond());
};

// Registered last, so it only catches what no route above matched. It also gives every common
// method at least one route: bramus/router reads an undefined array key for a method without any.
$router->all('/.*', $notFound);
$router->set404($notFound);

// Run the router
$router->run();

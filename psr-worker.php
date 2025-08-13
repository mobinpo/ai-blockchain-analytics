<?php

declare(strict_types=1);

use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\Http\PSR7Worker;
use Nyholm\Psr7\Factory\Psr17Factory;

// Include Laravel's bootstrap
require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';

// Create PSR-7 factory
$factory = new Psr17Factory();

// Create PSR-7 worker
$worker = new PSR7Worker(
    Worker::create(),
    $factory,
    $factory,
    $factory
);

// Create Laravel kernel
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

while ($request = $worker->waitRequest()) {
    try {
        // Convert PSR-7 request to Laravel request
        $laravelRequest = \Illuminate\Http\Request::createFromBase(
            \Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory::createRequest($request)
        );

        // Handle the request through Laravel
        $response = $kernel->handle($laravelRequest);

        // Convert Laravel response to PSR-7 response
        $psrResponse = \Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory::createResponse(
            $response,
            $factory,
            $factory,
            $factory
        );

        // Send the response
        $worker->respond($psrResponse);

        // Terminate the request
        $kernel->terminate($laravelRequest, $response);

    } catch (\Throwable $e) {
        // Log the error
        error_log('RoadRunner Worker Error: ' . $e->getMessage());
        
        // Send error response
        $worker->getWorker()->error((string) $e);
    }
}

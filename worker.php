<?php

use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\Http\PSR7Worker;
use Nyholm\Psr7\Factory\Psr17Factory;

ini_set('display_errors', 'stderr');
require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';

$psr17Factory = new Psr17Factory();

$worker = Worker::create();
$psrWorker = new PSR7Worker($worker, $psr17Factory, $psr17Factory, $psr17Factory);

while ($req = $psrWorker->waitRequest()) {
    try {
        // Create Laravel request from PSR-7
        $request = Illuminate\Http\Request::createFromBase(
            Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory::createRequest($req)
        );

        // Handle the request through Laravel
        $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
        $response = $kernel->handle($request);

        // Convert Laravel response to PSR-7
        $psrResponse = Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory::createResponse(
            $response,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory
        );

        $psrWorker->respond($psrResponse);

        // Terminate the request
        $kernel->terminate($request, $response);

    } catch (\Throwable $e) {
        $psrWorker->getWorker()->error((string)$e);
    }
}
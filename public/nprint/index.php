<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use App\Controllers\PrinterController;
use App\Controllers\TemplateController;
use App\Controllers\HelloController;


require 'vendor/autoload.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

$app->add(function (Request $request, $handler) {
    $origin = $request->getHeaderLine('Origin') ?: '*';
    $response = $handler->handle($request);
    if ($request->getMethod() === 'OPTIONS') {
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Content-Length', '0')
            ->withStatus(204);
        return $response;
    }
    return $response
        ->withHeader('Access-Control-Allow-Origin', $origin)
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Credentials', 'true');
});

$app->setBasePath('/nprint');

$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// Define app routes
$app->get('/hello/{name}', function (Request $request, Response $response, $args) {
    $name = $args['name'];
    $response->getBody()->write("Hello, $name");
    return $response;
});


$app->get('/hello', [HelloController::class, 'index']);

// Rutas de impresoras
$app->group('/printers', function ($group) {
    $group->get('', [PrinterController::class, 'list']);
    $group->post('/print-test/{id}', [PrinterController::class, 'printTemplateTest']);
    $group->post('/print', [PrinterController::class, 'print']);
});

// Rutas de templates
$app->group('/templates', function ($group) {
    $group->get('', [TemplateController::class, 'getAll']);
    $group->get('/{id}', [TemplateController::class, 'getById']);
    $group->post('/create', [TemplateController::class, 'create']);
    $group->post('/update/{id}', [TemplateController::class, 'update']);
    $group->put('/update/{id}', [TemplateController::class, 'update']);
    $group->put('/{id}', [TemplateController::class, 'update']);
    $group->delete('/{id}', [TemplateController::class, 'delete']);
});

$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response;
});


// Run app
$app->run();
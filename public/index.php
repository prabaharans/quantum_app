<?php

/**
 * Front Controller — single entry point for the QuantumPHP Studio MVC application.
 *
 * All requests are funnelled through this file via Apache's mod_rewrite (.htaccess).
 * It bootstraps the autoloader, sets global headers, wires up the Router, and
 * dispatches the request to the appropriate Controller action.
 */

declare(strict_types=1);

// ─── Bootstrap ────────────────────────────────────────────────────────────────

require_once dirname(__DIR__) . '/vendor/autoload.php';

use QuantumApp\Controller\ApiController;
use QuantumApp\Controller\HomeController;
use QuantumApp\Model\CircuitRepository;
use QuantumApp\Router\Router;

// ─── Global HTTP Headers ──────────────────────────────────────────────────────

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle CORS pre-flight early
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── Service Wiring ───────────────────────────────────────────────────────────

$storageFile   = dirname(__DIR__) . '/data/circuits.json';
$repository    = new CircuitRepository($storageFile);
$apiController = new ApiController($repository);
$homeController = new HomeController();

// ─── Router ───────────────────────────────────────────────────────────────────

$router = new Router();

// UI route (renders dashboard HTML view)
$router->get('/', [$homeController, 'index']);
$router->get('/index.php', [$homeController, 'index']);

// API routes — set JSON content-type for all /api/* responses
$router->get('/api/presets',         function () use ($apiController) {
    header('Content-Type: application/json');
    $apiController->presets();
});

$router->get('/api/circuits',        function () use ($apiController) {
    header('Content-Type: application/json');
    $apiController->listCircuits();
});

$router->get('/api/circuits/load',   function () use ($apiController) {
    header('Content-Type: application/json');
    $apiController->loadCircuit();
});

$router->post('/api/simulate',       function () use ($apiController) {
    header('Content-Type: application/json');
    $apiController->simulate();
});

$router->post('/api/circuits/save',  function () use ($apiController) {
    header('Content-Type: application/json');
    $apiController->saveCircuit();
});

$router->post('/api/circuits/delete', function () use ($apiController) {
    header('Content-Type: application/json');
    $apiController->deleteCircuit();
});

// ─── Dispatch ─────────────────────────────────────────────────────────────────

try {
    $dispatched = $router->dispatch();

    if (!$dispatched) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Route not found.']);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}

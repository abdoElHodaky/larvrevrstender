<?php

/**
 * Shared Service Entry Point
 * 
 * This service provides cross-service infrastructure and shared procedures
 * for the Laravel microservices architecture.
 */

use Illuminate\Http\Request;
use Illuminate\Http\Response;

// Bootstrap the application
require_once __DIR__ . '/../vendor/autoload.php';

// Create a simple application instance
$app = new \Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

// Basic service information endpoint
if ($_SERVER['REQUEST_URI'] === '/health' || $_SERVER['REQUEST_URI'] === '/') {
    header('Content-Type: application/json');
    echo json_encode([
        'service' => 'shared-service',
        'status' => 'healthy',
        'version' => '1.0.0',
        'timestamp' => date('c'),
        'environment' => $_ENV['ENVIRONMENT_COLOR'] ?? 'blue',
        'procedures' => [
            'cross-service',
            'workflow',
            'security',
            'bidding-lifecycle',
            'notification',
            'third-party-integration'
        ]
    ]);
    exit;
}

// API endpoint for procedure execution
if ($_SERVER['REQUEST_URI'] === '/api/procedures' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['procedure'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Procedure name is required']);
        exit;
    }
    
    // Mock procedure execution for now
    echo json_encode([
        'procedure' => $input['procedure'],
        'status' => 'executed',
        'result' => 'success',
        'timestamp' => date('c')
    ]);
    exit;
}

// Default response
header('Content-Type: application/json');
http_response_code(404);
echo json_encode([
    'error' => 'Endpoint not found',
    'available_endpoints' => [
        'GET /' => 'Service health check',
        'GET /health' => 'Service health check',
        'POST /api/procedures' => 'Execute shared procedures'
    ]
]);

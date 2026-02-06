<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Shared\Procedures\CrossServiceProcedure;
use Shared\Core\RestHandler;

/*
|--------------------------------------------------------------------------
| Cross-Service REST API Routes
|--------------------------------------------------------------------------
|
| These routes provide REST API access to cross-service procedures
| with standardized request/response formatting and error handling.
|
*/

// Initialize the cross-service procedure and REST handler
$crossService = new CrossServiceProcedure();
$restHandler = $crossService->getRestHandler();

// Health check routes (no authentication required)
Route::get('/health', function () use ($crossService) {
    $result = $crossService->healthCheck();
    return response()->json($result, $result['success'] ? 200 : 500);
});

Route::get('/info', function () use ($crossService) {
    $result = $crossService->getSystemInfo();
    return response()->json($result, $result['success'] ? 200 : 500);
});

// Procedure listing (no authentication required for discovery)
Route::get('/procedures', function () use ($crossService) {
    $result = $crossService->listProcedures();
    return response()->json($result, $result['success'] ? 200 : 500);
});

// Event management routes
Route::prefix('events')->group(function () use ($crossService) {
    Route::post('/publish', function (Request $request) use ($crossService) {
        $params = $request->all();
        $context = [
            'trace_id' => $request->header('X-Trace-ID'),
            'source_service' => $request->header('X-Source-Service', 'unknown'),
            'request_method' => $request->getMethod(),
            'request_uri' => $request->getRequestUri(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ];
        
        $result = $crossService->publishEvent($params, $context);
        return response()->json($result, $result['success'] ? 200 : 400);
    });

    Route::post('/batch', function (Request $request) use ($crossService) {
        $params = $request->all();
        $context = [
            'trace_id' => $request->header('X-Trace-ID'),
            'source_service' => $request->header('X-Source-Service', 'unknown'),
            'request_method' => $request->getMethod(),
            'request_uri' => $request->getRequestUri(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ];
        
        $result = $crossService->publishBatchEvents($params, $context);
        return response()->json($result, $result['success'] ? 200 : 400);
    });

    Route::post('/retry/{eventId}', function (Request $request, $eventId) use ($crossService) {
        $params = ['event_id' => $eventId];
        $context = [
            'trace_id' => $request->header('X-Trace-ID'),
            'source_service' => $request->header('X-Source-Service', 'unknown')
        ];
        
        $result = $crossService->retryEventPublication($params, $context);
        return response()->json($result, $result['success'] ? 200 : 400);
    });

    Route::get('/status/{eventId}', function (Request $request, $eventId) use ($crossService) {
        $params = ['event_id' => $eventId];
        $context = [
            'trace_id' => $request->header('X-Trace-ID'),
            'source_service' => $request->header('X-Source-Service', 'unknown')
        ];
        
        $result = $crossService->getEventStatus($params, $context);
        return response()->json($result, $result['success'] ? 200 : 404);
    });
});

// Cache management routes
Route::prefix('cache')->group(function () use ($crossService) {
    Route::post('/set', function (Request $request) use ($crossService) {
        $params = $request->all();
        $context = [
            'trace_id' => $request->header('X-Trace-ID'),
            'source_service' => $request->header('X-Source-Service', 'unknown')
        ];
        
        $result = $crossService->cacheSet($params, $context);
        return response()->json($result, $result['success'] ? 200 : 400);
    });

    Route::get('/get/{key}', function (Request $request, $key) use ($crossService) {
        $params = [
            'key' => $key,
            'driver' => $request->query('driver'),
            'default' => $request->query('default')
        ];
        $context = [
            'trace_id' => $request->header('X-Trace-ID'),
            'source_service' => $request->header('X-Source-Service', 'unknown')
        ];
        
        $result = $crossService->cacheGet($params, $context);
        return response()->json($result, $result['success'] ? 200 : 404);
    });

    Route::delete('/delete/{key}', function (Request $request, $key) use ($crossService) {
        $params = [
            'key' => $key,
            'driver' => $request->query('driver')
        ];
        $context = [
            'trace_id' => $request->header('X-Trace-ID'),
            'source_service' => $request->header('X-Source-Service', 'unknown')
        ];
        
        $result = $crossService->cacheDelete($params, $context);
        return response()->json($result, $result['success'] ? 200 : 404);
    });

    Route::get('/exists/{key}', function (Request $request, $key) use ($crossService) {
        $params = [
            'key' => $key,
            'driver' => $request->query('driver')
        ];
        $context = [
            'trace_id' => $request->header('X-Trace-ID'),
            'source_service' => $request->header('X-Source-Service', 'unknown')
        ];
        
        $result = $crossService->cacheExists($params, $context);
        return response()->json($result, 200);
    });

    Route::get('/stats', function (Request $request) use ($crossService) {
        $params = [
            'driver' => $request->query('driver')
        ];
        $context = [
            'trace_id' => $request->header('X-Trace-ID'),
            'source_service' => $request->header('X-Source-Service', 'unknown')
        ];
        
        $result = $crossService->cacheStats($params, $context);
        return response()->json($result, $result['success'] ? 200 : 500);
    });

    Route::post('/flush', function (Request $request) use ($crossService) {
        $params = $request->all();
        $context = [
            'trace_id' => $request->header('X-Trace-ID'),
            'source_service' => $request->header('X-Source-Service', 'unknown')
        ];
        
        $result = $crossService->cacheFlush($params, $context);
        return response()->json($result, $result['success'] ? 200 : 500);
    });
});

// Service registry routes
Route::prefix('services')->group(function () use ($crossService) {
    Route::post('/register', function (Request $request) use ($crossService) {
        $params = $request->all();
        $context = [
            'trace_id' => $request->header('X-Trace-ID'),
            'source_service' => $request->header('X-Source-Service', 'unknown')
        ];
        
        $result = $crossService->registerService($params, $context);
        return response()->json($result, $result['success'] ? 200 : 400);
    });

    Route::get('/registry', function (Request $request) use ($crossService) {
        $context = [
            'trace_id' => $request->header('X-Trace-ID'),
            'source_service' => $request->header('X-Source-Service', 'unknown')
        ];
        
        $result = $crossService->getServiceRegistry([], $context);
        return response()->json($result, $result['success'] ? 200 : 500);
    });
});

// Configuration management routes
Route::prefix('config')->group(function () use ($crossService) {
    Route::post('/update', function (Request $request) use ($crossService) {
        $params = $request->all();
        $context = [
            'trace_id' => $request->header('X-Trace-ID'),
            'source_service' => $request->header('X-Source-Service', 'unknown')
        ];
        
        $result = $crossService->updateConfiguration($params, $context);
        return response()->json($result, $result['success'] ? 200 : 400);
    });
});

// Validation routes
Route::prefix('validation')->group(function () use ($crossService) {
    Route::post('/validate', function (Request $request) use ($crossService) {
        $params = $request->all();
        $context = [
            'trace_id' => $request->header('X-Trace-ID'),
            'source_service' => $request->header('X-Source-Service', 'unknown')
        ];
        
        $result = $crossService->validateData($params, $context);
        return response()->json($result, $result['success'] ? 200 : 400);
    });

    Route::post('/api-request', function (Request $request) use ($crossService) {
        $params = $request->all();
        $context = [
            'trace_id' => $request->header('X-Trace-ID'),
            'source_service' => $request->header('X-Source-Service', 'unknown')
        ];
        
        $result = $crossService->validateApiRequest($params, $context);
        return response()->json($result, $result['success'] ? 200 : 400);
    });

    Route::post('/cross-fields', function (Request $request) use ($crossService) {
        $params = $request->all();
        $context = [
            'trace_id' => $request->header('X-Trace-ID'),
            'source_service' => $request->header('X-Source-Service', 'unknown')
        ];
        
        $result = $crossService->validateCrossFields($params, $context);
        return response()->json($result, $result['success'] ? 200 : 400);
    });

    Route::post('/sanitize', function (Request $request) use ($crossService) {
        $params = $request->all();
        $context = [
            'trace_id' => $request->header('X-Trace-ID'),
            'source_service' => $request->header('X-Source-Service', 'unknown')
        ];
        
        $result = $crossService->sanitizeData($params, $context);
        return response()->json($result, $result['success'] ? 200 : 400);
    });
});

// Security routes
Route::prefix('security')->group(function () use ($crossService) {
    Route::post('/authenticate', function (Request $request) use ($crossService) {
        $params = $request->all();
        $context = [
            'trace_id' => $request->header('X-Trace-ID'),
            'source_service' => $request->header('X-Source-Service', 'unknown'),
            'ip_address' => $request->ip()
        ];
        
        $result = $crossService->authenticateToken($params, $context);
        return response()->json($result, $result['success'] ? 200 : 401);
    });

    Route::post('/authorize', function (Request $request) use ($crossService) {
        $params = $request->all();
        $context = [
            'trace_id' => $request->header('X-Trace-ID'),
            'source_service' => $request->header('X-Source-Service', 'unknown'),
            'ip_address' => $request->ip()
        ];
        
        $result = $crossService->checkAuthorization($params, $context);
        return response()->json($result, $result['success'] ? 200 : 403);
    });

    Route::post('/rate-limit', function (Request $request) use ($crossService) {
        $params = $request->all();
        $context = [
            'trace_id' => $request->header('X-Trace-ID'),
            'source_service' => $request->header('X-Source-Service', 'unknown')
        ];
        
        $result = $crossService->applyRateLimit($params, $context);
        return response()->json($result, $result['success'] ? 200 : 429);
    });

    Route::post('/encrypt', function (Request $request) use ($crossService) {
        $params = $request->all();
        $context = [
            'trace_id' => $request->header('X-Trace-ID'),
            'source_service' => $request->header('X-Source-Service', 'unknown')
        ];
        
        $result = $crossService->encryptData($params, $context);
        return response()->json($result, $result['success'] ? 200 : 400);
    });

    Route::post('/decrypt', function (Request $request) use ($crossService) {
        $params = $request->all();
        $context = [
            'trace_id' => $request->header('X-Trace-ID'),
            'source_service' => $request->header('X-Source-Service', 'unknown')
        ];
        
        $result = $crossService->decryptData($params, $context);
        return response()->json($result, $result['success'] ? 200 : 400);
    });
});

// Generic procedure execution route
Route::post('/execute', function (Request $request) use ($crossService) {
    $params = $request->all();
    $context = [
        'trace_id' => $request->header('X-Trace-ID'),
        'source_service' => $request->header('X-Source-Service', 'unknown'),
        'request_method' => $request->getMethod(),
        'request_uri' => $request->getRequestUri(),
        'ip_address' => $request->ip(),
        'user_agent' => $request->userAgent()
    ];
    
    $result = $crossService->executeProcedure($params, $context);
    return response()->json($result, $result['success'] ? 200 : 400);
});

// Catch-all route for dynamic procedure execution
Route::any('/{procedure}/{method}', function (Request $request, $procedure, $method) use ($restHandler) {
    return $restHandler->handle($request);
})->where(['procedure' => '[a-zA-Z0-9_-]+', 'method' => '[a-zA-Z0-9_-]+']);

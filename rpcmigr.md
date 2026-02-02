🚀 Migration Plan: Guzzle to JSON-RPC (Laravel 12 + Octane)
This document serves as the official roadmap for transitioning service-to-service communication from REST-based Guzzle calls to a high-performance, structured JSON-RPC 2.0 architecture.

1. Architectural Overview
The system moves away from the overhead of repeated framework "bootstrapping" by utilizing persistent memory and a streamlined communication protocol.

Protocol: JSON-RPC 2.0 (via sajya/server & sajya/client).

Runtime: Laravel Octane (Swoole or FrankenPHP engine).

Security: Laravel Sanctum token-based authentication.

Observability: Distributed tracing via Correlation IDs.

2. Phase 1: Infrastructure & The "Base"
Goal: Establish the foundation for all future RPC services.

Environment Sync: Ensure all services run PHP 8.2+ and Laravel 12.

Base Procedure: Implement a global abstract class to standardize validation.

PHP
abstract class BaseProcedure extends \Sajya\Server\Procedure {
    protected function validate(array $data, array $rules) {
        $v = \Validator::make($data, $rules);
        if ($v->fails()) {
            throw new \Sajya\Server\Exceptions\RuntimeException('Invalid params', -32602, $v->errors()->toArray());
        }
    }
}
Sanctum Lockdown: Define the RPC route in api.php.

PHP
Route::rpc('/rpc', [UserProcedure::class])->middleware('auth:sanctum');
3. Phase 2: Implementation & Switch
Goal: Refactor existing Guzzle calls into Procedure-based logic.

Procedure Creation: Map existing REST controllers to Procedures.

Example: POST /api/users becomes User@create.

Client Singleton: Register the RPC Client in the Service Provider.

PHP
$this->app->singleton('InternalRpc', fn() => new \Sajya\Client\Client(
    Http::baseUrl(config('rpc.url'))->withToken(config('rpc.token'))
));
The Refactor: Replace Http::post() with $rpc->execute().

Pro-Tip: Always wrap in a try/catch or check $response->isError().

4. Phase 3: Performance Tuning (Octane)
Goal: Leverage memory persistence and concurrency.

Batching: Replace multiple sequential calls with a single JSON-RPC batch.

Concurrency: Use Octane's worker pool to fetch data in parallel within a procedure.

PHP
public function getDashboard(Request $request) {
    return \Laravel\Octane\Facades\Octane::concurrently([
        fn() => $this->getStats($request->user_id),
        fn() => $this->getNotifications($request->user_id),
    ]);
}
Warm-up: Add Procedure classes to your opcache preload list to ensure they are ready in memory.

5. Phase 4: Governance & Cleanup
Goal: Maintainability and long-term health.

Versioning: Use method namespacing (e.g., User@create_v2) for breaking changes.

Circuit Breaker: Implement a 2-second timeout on all RPC calls to prevent cascading failures.

Audit: Use php artisan rpc:list to track all registered endpoints.

Decommission: Delete old REST controllers and routes once traffic has migrated.

Summary of Gains
Metric	Guzzle (REST)	Sajya (RPC + Octane)
Framework Boot	Every Request	Once (Persistent Memory)
Network Overhead	Multiple Roundtrips	Single Batch Roundtrip
Error Handling	Generic HTTP Status	Specific JSON-RPC Error Codes
Type Safety	Loose JSON	Procedure Contracts
How to use this plan
Copy this Markdown into your project's docs/ folder.
Migration Plan: Guzzle HTTP to JSON-RPC (Laravel 12 + Octane)
This document outlines the strategic transformation from REST-based Guzzle calls to a high-performance, secure JSON-RPC architecture.

Phase 1: Infrastructure & Foundation
Goal: Prepare the Server and Client environments.

• Install Sajya: Deploy `sajya/server` on the provider and `sajya/client` on the consumer.

• Base Procedure: Implement an `abstract class BaseProcedure` to centralize validation and error handling.

• Octane Integration: Ensure Laravel Octane (Swoole/FrankenPHP) is active to keep the application in memory for sub-millisecond RPC execution.

• Security: Wrap the RPC route with Laravel Sanctum middleware for service-to-service authentication.

Phase 2: Service Shadowing
Goal: Verify data integrity without interrupting production.

• Dual-Run: Implement the RPC procedure but continue using the Guzzle response. Log any mismatches between the two.

• Contract Definition: Use DocBlocks and Shared Interfaces to define the "Source of Truth" for method names and parameters.

Phase 3: The Transformation (Switch)
Goal: Migrate logic from HTTP verbs to RPC methods.

• Singleton Registration: Register the RPC Client in `AppServiceProvider` with a persistent Bearer token and Correlation IDs for tracing.

• Refactor: Replace `Http::post()` with `$rpcClient->execute('Service@method')`.

• Error Mapping: Utilize custom RPC error codes (e.g., `-32001` for business logic failures) instead of generic HTTP statuses.

Phase 4: Resilience & Optimization
Goal: Maximize performance and reliability.

• Batching: Combine multiple independent requests into a single JSON-RPC batch payload to eliminate HTTP round-trip overhead.

• Circuit Breaker: Implement fallback logic if the RPC server is unreachable.

• Octane Concurrency: Inside heavy procedures, use `Octane::concurrently()` to fetch data in parallel.

Operational Checklist
• Versioning: Use method namespacing (e.g., `User@create_v2`) for breaking changes.

• Observability: Ensure `X-Correlation-ID` is passed through every header.

• Documentation: Use `php artisan rpc:list` (custom command) to audit available procedures.

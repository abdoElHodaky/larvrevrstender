<?php

declare(strict_types=1);

namespace Shared\RPC\Tests\Integration;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use PHPUnit\Framework\TestCase;
use Shared\RPC\Clients\AuthServiceClient;
use Shared\RPC\Clients\UserServiceClient;
use Shared\RPC\Enums\ServiceType;
use Shared\RPC\ValueObjects\RpcRequest;
use Shared\RPC\ValueObjects\RpcResponse;

/**
 * RPC Client Integration Tests - PHP 8.3 & Laravel 12
 * 
 * Comprehensive integration tests for the RPC ecosystem
 * with mock HTTP responses and real client behavior.
 */
class RpcClientIntegrationTest extends TestCase
{
    private HttpFactory $httpFactory;
    private AuthServiceClient $authClient;
    private UserServiceClient $userClient;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->httpFactory = $this->createMock(HttpFactory::class);
        $this->authClient = new AuthServiceClient($this->httpFactory, 'testing');
        $this->userClient = new UserServiceClient($this->httpFactory, 'testing');
    }

    public function test_auth_client_authentication_success(): void
    {
        // Mock successful authentication response
        $mockResponse = $this->createMockResponse(200, [
            'success' => true,
            'data' => [
                'token' => 'test-token-123',
                'user_id' => 1,
                'expires_at' => '2024-12-31T23:59:59Z',
            ],
        ]);

        $this->httpFactory
            ->expects($this->once())
            ->method('timeout')
            ->willReturnSelf();

        $this->httpFactory
            ->expects($this->once())
            ->method('withHeaders')
            ->willReturnSelf();

        $this->httpFactory
            ->expects($this->once())
            ->method('post')
            ->with(
                'http://auth-service:8000/auth/login',
                ['email' => 'test@example.com', 'password' => 'password123']
            )
            ->willReturn($mockResponse);

        // Execute authentication
        $response = $this->authClient->authenticate('test@example.com', 'password123');

        // Assertions
        $this->assertTrue($response->isSuccessful());
        $this->assertEquals('test-token-123', $response->getField('token'));
        $this->assertEquals(1, $response->getField('user_id'));
    }

    public function test_auth_client_authentication_failure(): void
    {
        // Mock failed authentication response
        $mockResponse = $this->createMockResponse(401, [
            'error' => 'Invalid credentials',
            'message' => 'The provided credentials are incorrect.',
        ]);

        $this->httpFactory
            ->expects($this->once())
            ->method('timeout')
            ->willReturnSelf();

        $this->httpFactory
            ->expects($this->once())
            ->method('withHeaders')
            ->willReturnSelf();

        $this->httpFactory
            ->expects($this->once())
            ->method('post')
            ->willReturn($mockResponse);

        // Execute authentication
        $response = $this->authClient->authenticate('test@example.com', 'wrong-password');

        // Assertions
        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isClientError());
        $this->assertEquals('Invalid credentials', $response->getErrorMessage());
    }

    public function test_user_client_get_profile_success(): void
    {
        // Mock successful user profile response
        $mockResponse = $this->createMockResponse(200, [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'created_at' => '2024-01-01T00:00:00Z',
        ]);

        $this->httpFactory
            ->expects($this->once())
            ->method('timeout')
            ->willReturnSelf();

        $this->httpFactory
            ->expects($this->once())
            ->method('withHeaders')
            ->willReturnSelf();

        $this->httpFactory
            ->expects($this->once())
            ->method('get')
            ->with('http://user-service:8001/users/1')
            ->willReturn($mockResponse);

        // Execute get user profile
        $response = $this->userClient->getUserProfile(1);

        // Assertions
        $this->assertTrue($response->isSuccessful());
        $this->assertEquals('John Doe', $response->getField('name'));
        $this->assertEquals('john@example.com', $response->getField('email'));
    }

    public function test_correlation_id_propagation(): void
    {
        $correlationId = 'test-correlation-123';
        
        // Mock response
        $mockResponse = $this->createMockResponse(200, ['success' => true]);

        $this->httpFactory
            ->expects($this->once())
            ->method('timeout')
            ->willReturnSelf();

        $this->httpFactory
            ->expects($this->once())
            ->method('withHeaders')
            ->with($this->callback(function ($headers) use ($correlationId) {
                return isset($headers['X-Correlation-ID']) && 
                       $headers['X-Correlation-ID'] === $correlationId;
            }))
            ->willReturnSelf();

        $this->httpFactory
            ->expects($this->once())
            ->method('get')
            ->willReturn($mockResponse);

        // Execute with correlation ID
        $clientWithCorrelation = $this->authClient->withCorrelationId($correlationId);
        $response = $clientWithCorrelation->healthCheck();

        // Assertions
        $this->assertTrue($response->isSuccessful());
    }

    public function test_retry_mechanism_on_server_error(): void
    {
        // Mock server error responses followed by success
        $errorResponse = $this->createMockResponse(503, ['error' => 'Service unavailable']);
        $successResponse = $this->createMockResponse(200, ['status' => 'healthy']);

        $this->httpFactory
            ->expects($this->exactly(2))
            ->method('timeout')
            ->willReturnSelf();

        $this->httpFactory
            ->expects($this->exactly(2))
            ->method('withHeaders')
            ->willReturnSelf();

        $this->httpFactory
            ->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls($errorResponse, $successResponse);

        // Execute health check (should retry and succeed)
        $response = $this->authClient->healthCheck();

        // Assertions
        $this->assertTrue($response->isSuccessful());
    }

    public function test_service_type_configuration(): void
    {
        // Test service type enum functionality
        $authServiceType = ServiceType::AUTH;
        $userServiceType = ServiceType::USER;

        $this->assertEquals('auth-service', $authServiceType->value);
        $this->assertEquals('user-service', $userServiceType->value);
        $this->assertEquals(8000, $authServiceType->getDefaultPort());
        $this->assertEquals(8001, $userServiceType->getDefaultPort());
        $this->assertEquals('Authentication Service', $authServiceType->getDisplayName());
        $this->assertEquals('User Management Service', $userServiceType->getDisplayName());
    }

    public function test_rpc_request_value_object(): void
    {
        // Test RPC request creation and immutability
        $request = RpcRequest::post('/test', ['key' => 'value'])
            ->withCorrelationId('test-123')
            ->withTimeout(60);

        $this->assertEquals('POST', $request->method);
        $this->assertEquals('/test', $request->endpoint);
        $this->assertEquals(['key' => 'value'], $request->data);
        $this->assertEquals('test-123', $request->correlationId);
        $this->assertEquals(60, $request->timeoutSeconds);

        // Test immutability
        $newRequest = $request->withTimeout(120);
        $this->assertEquals(60, $request->timeoutSeconds);
        $this->assertEquals(120, $newRequest->timeoutSeconds);
    }

    public function test_rpc_response_value_object(): void
    {
        // Test successful response
        $successResponse = RpcResponse::success(
            data: ['user_id' => 1, 'name' => 'John'],
            statusCode: 200,
            correlationId: 'test-123',
            responseTimeMs: 150.5
        );

        $this->assertTrue($successResponse->isSuccessful());
        $this->assertFalse($successResponse->isClientError());
        $this->assertFalse($successResponse->isServerError());
        $this->assertEquals(1, $successResponse->getField('user_id'));
        $this->assertEquals('John', $successResponse->getField('name'));

        // Test failure response
        $failureResponse = RpcResponse::failure(
            error: 'Not found',
            statusCode: 404,
            correlationId: 'test-123',
            responseTimeMs: 50.0
        );

        $this->assertFalse($failureResponse->isSuccessful());
        $this->assertTrue($failureResponse->isClientError());
        $this->assertFalse($failureResponse->shouldRetry());
        $this->assertEquals('Not found', $failureResponse->getErrorMessage());
    }

    /**
     * Create a mock HTTP response
     */
    private function createMockResponse(int $statusCode, array $data): Response
    {
        $response = $this->createMock(Response::class);
        
        $response->method('status')->willReturn($statusCode);
        $response->method('successful')->willReturn($statusCode >= 200 && $statusCode < 300);
        $response->method('json')->willReturn($data);
        $response->method('headers')->willReturn([]);

        return $response;
    }
}

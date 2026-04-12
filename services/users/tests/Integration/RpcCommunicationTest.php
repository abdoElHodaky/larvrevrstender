<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;
use Shared\RPC\Clients\PaymentServiceClient;
use Shared\RPC\Clients\VinOcrServiceClient;
use Shared\RPC\Clients\AnalyticsServiceClient;
use Shared\RPC\Enums\ServiceType;

/**
 * RPC Communication Integration Tests
 * 
 * Validates that RPC communication works correctly after architecture changes.
 * Tests service discovery, port configurations, and basic connectivity.
 */
class RpcCommunicationTest extends TestCase
{
    /**
     * Test that RPC clients can be instantiated correctly
     */
    public function test_rpc_clients_can_be_instantiated(): void
    {
        $paymentClient = app(PaymentServiceClient::class);
        $vinOcrClient = app(VinOcrServiceClient::class);
        $analyticsClient = app(AnalyticsServiceClient::class);

        $this->assertInstanceOf(PaymentServiceClient::class, $paymentClient);
        $this->assertInstanceOf(VinOcrServiceClient::class, $vinOcrClient);
        $this->assertInstanceOf(AnalyticsServiceClient::class, $analyticsClient);
    }

    /**
     * Test service type port configurations
     */
    public function test_service_ports_are_configured_correctly(): void
    {
        $this->assertEquals(8004, ServiceType::PAYMENT->getDefaultPort());
        $this->assertEquals(8007, ServiceType::ANALYTICS->getDefaultPort());
        $this->assertEquals(8008, ServiceType::VIN_OCR->getDefaultPort());
        $this->assertEquals(8001, ServiceType::USER->getDefaultPort());
    }

    /**
     * Test environment-aware URL generation
     */
    public function test_environment_aware_url_generation(): void
    {
        // Local environment
        $localUrl = ServiceType::PAYMENT->getBaseUrl('local');
        $this->assertEquals('http://localhost:8004', $localUrl);

        // Docker environment
        $dockerUrl = ServiceType::PAYMENT->getBaseUrl('docker');
        $this->assertEquals('http://payment-service:8004', $dockerUrl);

        // Kubernetes environment
        $k8sUrl = ServiceType::PAYMENT->getBaseUrl('kubernetes');
        $this->assertEquals('http://payment-service.default.svc.cluster.local:8004', $k8sUrl);
    }

    /**
     * Test service display names
     */
    public function test_service_display_names(): void
    {
        $this->assertEquals('Payment Processing Service', ServiceType::PAYMENT->getDisplayName());
        $this->assertEquals('Analytics Service', ServiceType::ANALYTICS->getDisplayName());
        $this->assertEquals('VIN OCR Service', ServiceType::VIN_OCR->getDisplayName());
    }

    /**
     * Test critical services identification
     */
    public function test_critical_services_identification(): void
    {
        $criticalServices = ServiceType::getCriticalServices();
        
        $this->assertContains(ServiceType::AUTH, $criticalServices);
        $this->assertContains(ServiceType::GATEWAY, $criticalServices);
        $this->assertContains(ServiceType::USER, $criticalServices);
    }

    /**
     * Test service authentication requirements
     */
    public function test_service_authentication_requirements(): void
    {
        $this->assertTrue(ServiceType::PAYMENT->requiresAuth());
        $this->assertTrue(ServiceType::VIN_OCR->requiresAuth());
        $this->assertTrue(ServiceType::ANALYTICS->requiresAuth());
        $this->assertFalse(ServiceType::GATEWAY->requiresAuth());
    }

    /**
     * Test health check endpoints
     */
    public function test_health_check_endpoints(): void
    {
        $this->assertEquals('/health', ServiceType::PAYMENT->getHealthEndpoint());
        $this->assertEquals('/info', ServiceType::PAYMENT->getInfoEndpoint());
    }
}

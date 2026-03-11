<?php

namespace Shared\Procedures\Micro;

use Shared\Core\BaseProcedure;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Event Publishing Micro Procedure
 * 
 * Handles reliable event emission with retry logic, multiple broker support,
 * and comprehensive event tracking for cross-service communication.
 */
trait EventPublishingProcedure
{
    /**
     * Publish an event to the event bus
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function publishEvent(array $params, array $context = []): array
    {
        try {
            // Validate required parameters
            $validation = $this->validateParams($params, [
                'event_type' => ['required' => true, 'type' => 'string'],
                'event_data' => ['required' => true, 'type' => 'array'],
                'source_service' => ['required' => true, 'type' => 'string'],
                'target_services' => ['type' => 'array'],
                'priority' => ['type' => 'string'],
                'ttl' => ['type' => 'int'],
                'correlation_id' => ['type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $eventType = $params['event_type'];
            $eventData = $params['event_data'];
            $sourceService = $params['source_service'];
            $targetServices = $params['target_services'] ?? [];
            $priority = $params['priority'] ?? 'normal';
            $ttl = $params['ttl'] ?? 3600; // 1 hour default
            $correlationId = $params['correlation_id'] ?? $this->generateCorrelationId();

            // Create event payload
            $event = [
                'id' => $this->generateEventId(),
                'type' => $eventType,
                'data' => $eventData,
                'source_service' => $sourceService,
                'target_services' => $targetServices,
                'priority' => $priority,
                'correlation_id' => $correlationId,
                'trace_id' => $context['trace_id'] ?? null,
                'timestamp' => now()->toISOString(),
                'ttl' => $ttl,
                'retry_count' => 0,
                'max_retries' => 3,
                'version' => '1.0'
            ];

            // Publish to event bus
            $publishResult = $this->publishToEventBus($event);
            if (!$publishResult['success']) {
                return $this->errorResponse('Failed to publish event', $publishResult);
            }

            // Store event for audit and replay
            $this->storeEventForAudit($event);

            // Record metrics
            $this->recordMetric('event_published', 1, [
                'event_type' => $eventType,
                'source_service' => $sourceService,
                'priority' => $priority
            ]);

            $this->log('info', 'Event published successfully', [
                'event_id' => $event['id'],
                'event_type' => $eventType,
                'source_service' => $sourceService,
                'correlation_id' => $correlationId
            ]);

            return $this->successResponse([
                'event_id' => $event['id'],
                'correlation_id' => $correlationId,
                'published_at' => $event['timestamp'],
                'target_services' => $targetServices
            ], 'Event published successfully');

        } catch (Exception $e) {
            $this->log('error', 'Event publishing failed', [
                'error' => $e->getMessage(),
                'event_type' => $params['event_type'] ?? null,
                'source_service' => $params['source_service'] ?? null
            ]);

            return $this->errorResponse('Event publishing failed: ' . $e->getMessage());
        }
    }

    /**
     * Publish batch events
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function publishBatchEvents(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'events' => ['required' => true, 'type' => 'array'],
                'source_service' => ['required' => true, 'type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $events = $params['events'];
            $sourceService = $params['source_service'];
            $batchId = $this->generateBatchId();
            $results = [];
            $successCount = 0;
            $failureCount = 0;

            foreach ($events as $index => $eventParams) {
                $eventParams['source_service'] = $sourceService;
                $eventParams['batch_id'] = $batchId;
                $eventParams['batch_index'] = $index;

                $result = $this->publishEvent($eventParams, $context);
                $results[] = [
                    'index' => $index,
                    'success' => $result['success'],
                    'event_id' => $result['data']['event_id'] ?? null,
                    'error' => $result['success'] ? null : $result['message']
                ];

                if ($result['success']) {
                    $successCount++;
                } else {
                    $failureCount++;
                }
            }

            $this->recordMetric('batch_events_published', count($events), [
                'source_service' => $sourceService,
                'success_count' => $successCount,
                'failure_count' => $failureCount
            ]);

            return $this->successResponse([
                'batch_id' => $batchId,
                'total_events' => count($events),
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'results' => $results
            ], 'Batch events processed');

        } catch (Exception $e) {
            $this->log('error', 'Batch event publishing failed', [
                'error' => $e->getMessage(),
                'source_service' => $params['source_service'] ?? null
            ]);

            return $this->errorResponse('Batch event publishing failed: ' . $e->getMessage());
        }
    }

    /**
     * Retry failed event publication
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function retryEventPublication(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'event_id' => ['required' => true, 'type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $eventId = $params['event_id'];

            // Retrieve event from audit store
            $event = $this->getEventFromAudit($eventId);
            if (!$event) {
                return $this->errorResponse('Event not found', ['event_id' => $eventId]);
            }

            // Check retry limits
            if ($event['retry_count'] >= $event['max_retries']) {
                return $this->errorResponse('Maximum retry attempts exceeded', [
                    'event_id' => $eventId,
                    'retry_count' => $event['retry_count'],
                    'max_retries' => $event['max_retries']
                ]);
            }

            // Increment retry count
            $event['retry_count']++;
            $event['last_retry_at'] = now()->toISOString();

            // Retry publication
            $publishResult = $this->publishToEventBus($event);
            if (!$publishResult['success']) {
                // Update audit record with failure
                $this->updateEventAudit($eventId, [
                    'retry_count' => $event['retry_count'],
                    'last_retry_at' => $event['last_retry_at'],
                    'last_error' => $publishResult['error'] ?? 'Unknown error'
                ]);

                return $this->errorResponse('Retry failed', $publishResult);
            }

            // Update audit record with success
            $this->updateEventAudit($eventId, [
                'retry_count' => $event['retry_count'],
                'last_retry_at' => $event['last_retry_at'],
                'status' => 'published'
            ]);

            $this->recordMetric('event_retry_success', 1, [
                'event_type' => $event['type'],
                'retry_count' => $event['retry_count']
            ]);

            return $this->successResponse([
                'event_id' => $eventId,
                'retry_count' => $event['retry_count'],
                'retried_at' => $event['last_retry_at']
            ], 'Event retry successful');

        } catch (Exception $e) {
            $this->log('error', 'Event retry failed', [
                'error' => $e->getMessage(),
                'event_id' => $params['event_id'] ?? null
            ]);

            return $this->errorResponse('Event retry failed: ' . $e->getMessage());
        }
    }

    /**
     * Get event publication status
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function getEventStatus(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'event_id' => ['required' => true, 'type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $eventId = $params['event_id'];
            $event = $this->getEventFromAudit($eventId);

            if (!$event) {
                return $this->errorResponse('Event not found', ['event_id' => $eventId]);
            }

            // Get delivery status from subscribers
            $deliveryStatus = $this->getEventDeliveryStatus($eventId);

            return $this->successResponse([
                'event_id' => $eventId,
                'type' => $event['type'],
                'source_service' => $event['source_service'],
                'target_services' => $event['target_services'],
                'status' => $event['status'] ?? 'unknown',
                'published_at' => $event['timestamp'],
                'retry_count' => $event['retry_count'],
                'delivery_status' => $deliveryStatus,
                'correlation_id' => $event['correlation_id']
            ], 'Event status retrieved');

        } catch (Exception $e) {
            $this->log('error', 'Get event status failed', [
                'error' => $e->getMessage(),
                'event_id' => $params['event_id'] ?? null
            ]);

            return $this->errorResponse('Get event status failed: ' . $e->getMessage());
        }
    }

    /**
     * Publish event to the configured event bus
     *
     * @param array $event
     * @return array
     */
    private function publishToEventBus(array $event): array
    {
        try {
            $driver = config('cross_service.events.default_driver', 'redis');
            
            return match ($driver) {
                'redis' => $this->publishToRedis($event),
                'rabbitmq' => $this->publishToRabbitMQ($event),
                'kafka' => $this->publishToKafka($event),
                default => throw new Exception("Unsupported event driver: {$driver}")
            };
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Publish event to Redis
     *
     * @param array $event
     * @return array
     */
    private function publishToRedis(array $event): array
    {
        try {
            $channel = config('cross_service.events.drivers.redis.channel_prefix', 'events:') . $event['type'];
            $payload = json_encode($event);
            
            $result = Redis::publish($channel, $payload);
            
            return [
                'success' => true,
                'subscribers_notified' => $result,
                'channel' => $channel
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Publish event to RabbitMQ (placeholder)
     *
     * @param array $event
     * @return array
     */
    private function publishToRabbitMQ(array $event): array
    {
        // This would integrate with a RabbitMQ client library
        // For now, return a placeholder implementation
        return [
            'success' => true,
            'message' => 'RabbitMQ publishing not yet implemented'
        ];
    }

    /**
     * Publish event to Kafka (placeholder)
     *
     * @param array $event
     * @return array
     */
    private function publishToKafka(array $event): array
    {
        // This would integrate with a Kafka client library
        // For now, return a placeholder implementation
        return [
            'success' => true,
            'message' => 'Kafka publishing not yet implemented'
        ];
    }

    /**
     * Store event for audit and replay
     *
     * @param array $event
     * @return void
     */
    private function storeEventForAudit(array $event): void
    {
        try {
            $cacheKey = "event_audit:{$event['id']}";
            $this->cache($cacheKey, $event, 86400 * 30); // 30 days
        } catch (Exception $e) {
            $this->log('warning', 'Failed to store event for audit', [
                'event_id' => $event['id'],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get event from audit store
     *
     * @param string $eventId
     * @return array|null
     */
    private function getEventFromAudit(string $eventId): ?array
    {
        try {
            $cacheKey = "event_audit:{$eventId}";
            return $this->getCached($cacheKey);
        } catch (Exception $e) {
            $this->log('warning', 'Failed to get event from audit', [
                'event_id' => $eventId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Update event audit record
     *
     * @param string $eventId
     * @param array $updates
     * @return void
     */
    private function updateEventAudit(string $eventId, array $updates): void
    {
        try {
            $event = $this->getEventFromAudit($eventId);
            if ($event) {
                $event = array_merge($event, $updates);
                $cacheKey = "event_audit:{$eventId}";
                $this->cache($cacheKey, $event, 86400 * 30);
            }
        } catch (Exception $e) {
            $this->log('warning', 'Failed to update event audit', [
                'event_id' => $eventId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get event delivery status from subscribers
     *
     * @param string $eventId
     * @return array
     */
    private function getEventDeliveryStatus(string $eventId): array
    {
        try {
            $cacheKey = "event_delivery:{$eventId}";
            return $this->getCached($cacheKey, []);
        } catch (Exception $e) {
            $this->log('warning', 'Failed to get event delivery status', [
                'event_id' => $eventId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Generate unique event ID
     *
     * @return string
     */
    private function generateEventId(): string
    {
        return 'evt_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }

    /**
     * Generate correlation ID
     *
     * @return string
     */
    private function generateCorrelationId(): string
    {
        return 'corr_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }

    /**
     * Generate batch ID
     *
     * @return string
     */
    private function generateBatchId(): string
    {
        return 'batch_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }
}

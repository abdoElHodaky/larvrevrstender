<?php

namespace NotificationService\Procedures;

use NotificationService\Factories\NotificationFactory;
use Shared\Services\TemplateManager;

/**
 * Notification Factory RPC Procedure
 * 
 * Provides RPC endpoints for other services to access the notification factory
 * and builders through cross-service communication.
 * 
 * @package NotificationService\Procedures
 */
class NotificationFactoryProcedure
{
    /**
     * Template manager instance
     */
    private TemplateManager $templateManager;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->templateManager = new TemplateManager();
    }
    
    /**
     * Create notification factory instance
     *
     * @param string|null $service Service context
     * @param string $language Language code
     * @return array Factory configuration
     */
    public function createFactory(?string $service = null, string $language = 'en'): array
    {
        $factory = new NotificationFactory($this->templateManager);
        
        if ($service) {
            $factory->forService($service);
        }
        
        $factory->inLanguage($language);
        
        return [
            'factory_id' => uniqid('factory_', true),
            'service' => $service,
            'language' => $language,
            'available_channels' => $factory->getAvailableChannels()
        ];
    }
    
    /**
     * Send simple notification
     *
     * @param string $channel Channel type
     * @param string $recipient Recipient
     * @param string $template Template name
     * @param array $data Template data
     * @param string|null $service Service context
     * @param string $language Language code
     * @return array Result
     */
    public function sendNotification(
        string $channel,
        string $recipient,
        string $template,
        array $data = [],
        ?string $service = null,
        string $language = 'en'
    ): array {
        try {
            $factory = new NotificationFactory($this->templateManager);
            
            $result = $factory->quick($channel, $recipient, $template, $data, $service, $language);
            
            return [
                'success' => $result,
                'channel' => $channel,
                'recipient' => $recipient,
                'template' => $template,
                'service' => $service,
                'language' => $language
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'channel' => $channel,
                'recipient' => $recipient,
                'template' => $template
            ];
        }
    }
    
    /**
     * Send email notification
     *
     * @param array $config Email configuration
     * @return array Result
     */
    public function sendEmail(array $config): array
    {
        try {
            $factory = new NotificationFactory($this->templateManager);
            $builder = $factory->email($config['template'] ?? null);
            
            // Apply configuration
            $this->applyBaseConfig($builder, $config);
            
            // Email-specific configuration
            if (isset($config['subject'])) {
                $builder->withSubject($config['subject']);
            }
            
            if (isset($config['attachments'])) {
                $builder->withAttachments($config['attachments']);
            }
            
            if (isset($config['cc'])) {
                $builder->withCcMany($config['cc']);
            }
            
            if (isset($config['bcc'])) {
                $builder->withBccMany($config['bcc']);
            }
            
            if (isset($config['reply_to'])) {
                $builder->withReplyTo($config['reply_to']);
            }
            
            if (isset($config['headers'])) {
                $builder->withHeaders($config['headers']);
            }
            
            $result = $builder->send();
            
            return [
                'success' => $result,
                'type' => 'email',
                'recipients' => $config['recipients'] ?? [],
                'template' => $config['template'] ?? null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'type' => 'email'
            ];
        }
    }
    
    /**
     * Send SMS notification
     *
     * @param array $config SMS configuration
     * @return array Result
     */
    public function sendSms(array $config): array
    {
        try {
            $factory = new NotificationFactory($this->templateManager);
            $builder = $factory->sms($config['template'] ?? null);
            
            // Apply configuration
            $this->applyBaseConfig($builder, $config);
            
            // SMS-specific configuration
            if (isset($config['provider'])) {
                $builder->withProvider($config['provider']);
            }
            
            if (isset($config['sender_id'])) {
                $builder->withSenderId($config['sender_id']);
            }
            
            if (isset($config['max_length'])) {
                $builder->withMaxLength($config['max_length']);
            }
            
            if (isset($config['delivery_reports'])) {
                $builder->withDeliveryReports($config['delivery_reports']);
            }
            
            if (isset($config['encoding'])) {
                $builder->withEncoding($config['encoding']);
            }
            
            $result = $builder->send();
            
            return [
                'success' => $result,
                'type' => 'sms',
                'recipients' => $config['recipients'] ?? [],
                'template' => $config['template'] ?? null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'type' => 'sms'
            ];
        }
    }
    
    /**
     * Send WhatsApp notification
     *
     * @param array $config WhatsApp configuration
     * @return array Result
     */
    public function sendWhatsApp(array $config): array
    {
        try {
            $factory = new NotificationFactory($this->templateManager);
            $builder = $factory->whatsapp($config['template'] ?? null);
            
            // Apply configuration
            $this->applyBaseConfig($builder, $config);
            
            // WhatsApp-specific configuration
            if (isset($config['provider'])) {
                $builder->withProvider($config['provider']);
            }
            
            if (isset($config['media'])) {
                foreach ($config['media'] as $media) {
                    $builder->withMedia($media['type'], $media['url'], $media['caption'] ?? null);
                }
            }
            
            if (isset($config['buttons'])) {
                foreach ($config['buttons'] as $button) {
                    $builder->withButton($button['text'], $button['action'], $button['value']);
                }
            }
            
            if (isset($config['business_template'])) {
                $builder->withBusinessTemplate(
                    $config['business_template']['id'],
                    $config['business_template']['params'] ?? []
                );
            }
            
            $result = $builder->send();
            
            return [
                'success' => $result,
                'type' => 'whatsapp',
                'recipients' => $config['recipients'] ?? [],
                'template' => $config['template'] ?? null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'type' => 'whatsapp'
            ];
        }
    }
    
    /**
     * Send multi-channel notification
     *
     * @param array $config Multi-channel configuration
     * @return array Result
     */
    public function sendMultiChannel(array $config): array
    {
        try {
            $factory = new NotificationFactory($this->templateManager);
            $builder = $factory->multiChannel($config['channels'], $config['template'] ?? null);
            
            // Apply configuration
            $this->applyBaseConfig($builder, $config);
            
            // Multi-channel specific configuration
            if (isset($config['fallback_strategy'])) {
                $builder->withFallbackStrategy($config['fallback_strategy']);
            }
            
            if (isset($config['priority_order'])) {
                $builder->withPriorityOrder($config['priority_order']);
            }
            
            if (isset($config['channel_configs'])) {
                foreach ($config['channel_configs'] as $channel => $channelConfig) {
                    $builder->configureChannel($channel, $channelConfig);
                }
            }
            
            $result = $builder->send();
            
            return [
                'success' => $result,
                'type' => 'multi_channel',
                'channels' => $config['channels'],
                'recipients' => $config['recipients'] ?? [],
                'template' => $config['template'] ?? null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'type' => 'multi_channel'
            ];
        }
    }
    
    /**
     * Send bulk notification
     *
     * @param array $config Bulk configuration
     * @return array Result
     */
    public function sendBulk(array $config): array
    {
        try {
            $factory = new NotificationFactory($this->templateManager);
            $builder = $factory->bulk($config['channel'], $config['template'] ?? null);
            
            // Apply configuration
            $this->applyBaseConfig($builder, $config);
            
            // Bulk-specific configuration
            if (isset($config['batch_size'])) {
                $builder->withBatchSize($config['batch_size']);
            }
            
            if (isset($config['rate_limit'])) {
                $builder->withRateLimit($config['rate_limit']);
            }
            
            $result = $builder->send();
            
            return [
                'success' => $result,
                'type' => 'bulk',
                'channel' => $config['channel'],
                'recipients_count' => count($config['recipients'] ?? []),
                'template' => $config['template'] ?? null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'type' => 'bulk'
            ];
        }
    }
    
    /**
     * Schedule notification
     *
     * @param array $config Scheduled configuration
     * @return array Result
     */
    public function scheduleNotification(array $config): array
    {
        try {
            $factory = new NotificationFactory($this->templateManager);
            $builder = $factory->scheduled($config['channel'], $config['template'] ?? null);
            
            // Apply configuration
            $this->applyBaseConfig($builder, $config);
            
            // Scheduled-specific configuration
            if (isset($config['scheduled_at'])) {
                $builder->scheduleAt($config['scheduled_at']);
            } elseif (isset($config['schedule_in'])) {
                $builder->scheduleIn($config['schedule_in']);
            }
            
            if (isset($config['timezone'])) {
                $builder->inTimezone($config['timezone']);
            }
            
            if (isset($config['recurring'])) {
                $builder->recurring(
                    $config['recurring']['pattern'],
                    $config['recurring']['until'] ?? null
                );
            }
            
            if (isset($config['schedule_id'])) {
                $builder->withScheduleId($config['schedule_id']);
            }
            
            $result = $builder->send();
            
            return [
                'success' => $result,
                'type' => 'scheduled',
                'channel' => $config['channel'],
                'scheduled_at' => $config['scheduled_at'] ?? null,
                'template' => $config['template'] ?? null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'type' => 'scheduled'
            ];
        }
    }
    
    /**
     * Apply base configuration to builder
     *
     * @param mixed $builder
     * @param array $config
     * @return void
     */
    private function applyBaseConfig($builder, array $config): void
    {
        if (isset($config['recipients'])) {
            foreach ($config['recipients'] as $recipient) {
                if (is_string($recipient)) {
                    $builder->to($recipient);
                } elseif (is_array($recipient)) {
                    $builder->to($recipient['recipient'], $recipient['data'] ?? []);
                }
            }
        }
        
        if (isset($config['data'])) {
            $builder->withData($config['data']);
        }
        
        if (isset($config['service'])) {
            $builder->setService($config['service']);
        }
        
        if (isset($config['language'])) {
            $builder->setLanguage($config['language']);
        }
        
        if (isset($config['priority'])) {
            $builder->withPriority($config['priority']);
        }
        
        if (isset($config['tracking'])) {
            $builder->withTracking($config['tracking']);
        }
        
        if (isset($config['retry'])) {
            $builder->withRetry($config['retry']['max_attempts'], $config['retry']['delay']);
        }
        
        if (isset($config['metadata'])) {
            $builder->withMetadata($config['metadata']);
        }
    }
}

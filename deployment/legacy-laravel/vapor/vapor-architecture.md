# Laravel Vapor with Linode & DigitalOcean Serverless Architecture

## Overview

This document outlines the Laravel Vapor deployment architecture for the notification system microservices, leveraging modern serverless platforms from Linode and DigitalOcean as alternatives to AWS Lambda. This approach provides cost-effective, developer-friendly serverless deployment with better pricing and simpler management.

## Serverless Platform Comparison

### Linode Serverless (Akamai Connected Cloud)

**Linode Functions** - Serverless compute platform:
- **Runtime**: Node.js, Python, Go support (PHP via custom runtime)
- **Pricing**: $0.0000185 per GB-second (significantly cheaper than AWS)
- **Memory**: 128MB to 3GB
- **Timeout**: Up to 15 minutes
- **Cold Start**: ~100-300ms
- **Integration**: Native Linode Object Storage, MySQL, Redis

### DigitalOcean Functions

**DigitalOcean Functions** - Serverless platform:
- **Runtime**: Node.js, Python, Go, PHP support
- **Pricing**: $0.0000185 per GB-second + $0.50 per million requests
- **Memory**: 128MB to 1GB
- **Timeout**: Up to 15 minutes
- **Cold Start**: ~50-200ms
- **Integration**: DigitalOcean Spaces, Managed Databases, Redis

## Architecture Strategy

### Hybrid Serverless Approach

Instead of pure serverless microservices, we'll implement a **hybrid approach** that leverages serverless for specific notification processing while maintaining traditional services for core functionality:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    Hybrid Serverless Architecture                          │
├─────────────────────────────────┬───────────────────────────────────────────┤
│         Traditional Services    │           Serverless Functions            │
│                                 │                                           │
│  ┌─────────────────────────┐    │  ┌─────────────────────────────────────┐  │
│  │     Shared Service      │    │  │        Email Function              │  │
│  │   (Linode/DO Droplet)   │    │  │    (Linode/DO Functions)           │  │
│  │                         │    │  │                                     │  │
│  │  • API Gateway          │◄──►│  │  • Email Processing                 │  │
│  │  • Request Routing      │    │  │  • Template Rendering               │  │
│  │  • Authentication       │    │  │  • Provider Integration             │  │
│  └─────────────────────────┘    │  └─────────────────────────────────────┘  │
│                                 │                                           │
│  ┌─────────────────────────┐    │  ┌─────────────────────────────────────┐  │
│  │   Notification Core     │    │  │        SMS Function                 │  │
│  │   (Linode/DO Droplet)   │    │  │    (Linode/DO Functions)           │  │
│  │                         │    │  │                                     │  │
│  │  • Template Management  │◄──►│  │  • SMS Processing                   │  │
│  │  • Queue Management     │    │  │  • Twilio Integration               │  │
│  │  • Status Tracking      │    │  │  • Delivery Tracking                │  │
│  └─────────────────────────┘    │  └─────────────────────────────────────┘  │
│                                 │                                           │
│                                 │  ┌─────────────────────────────────────┐  │
│                                 │  │       Push Function                 │  │
│                                 │  │    (Linode/DO Functions)           │  │
│                                 │  │                                     │  │
│                                 │  │  • Push Notification Processing     │  │
│                                 │  │  • FCM/APNS Integration             │  │
│                                 │  │  • Device Token Management          │  │
│                                 │  └─────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Linode Serverless Implementation

### Linode Functions Configuration

#### Email Processing Function

```yaml
# linode-functions.yml
functions:
  email-processor:
    runtime: nodejs18
    memory: 512MB
    timeout: 300s
    environment:
      MAIL_DRIVER: smtp
      MAIL_HOST: ${LINODE_MAIL_HOST}
      MAIL_PORT: 587
      MAIL_USERNAME: ${LINODE_MAIL_USERNAME}
      MAIL_PASSWORD: ${LINODE_MAIL_PASSWORD}
      TEMPLATE_STORAGE: linode-object-storage
    
    triggers:
      - type: http
        path: /email/send
      - type: queue
        source: email-queue
    
    resources:
      cpu: 0.5
      memory: 512Mi
      storage: 1Gi

  sms-processor:
    runtime: nodejs18
    memory: 256MB
    timeout: 180s
    environment:
      TWILIO_SID: ${TWILIO_SID}
      TWILIO_TOKEN: ${TWILIO_TOKEN}
      TWILIO_FROM: ${TWILIO_FROM}
    
    triggers:
      - type: http
        path: /sms/send
      - type: queue
        source: sms-queue
    
    resources:
      cpu: 0.25
      memory: 256Mi

  push-processor:
    runtime: nodejs18
    memory: 256MB
    timeout: 180s
    environment:
      FCM_SERVER_KEY: ${FCM_SERVER_KEY}
      FCM_PROJECT_ID: ${FCM_PROJECT_ID}
      APNS_KEY_ID: ${APNS_KEY_ID}
      APNS_TEAM_ID: ${APNS_TEAM_ID}
    
    triggers:
      - type: http
        path: /push/send
      - type: queue
        source: push-queue
    
    resources:
      cpu: 0.25
      memory: 256Mi
```

### Linode Infrastructure

#### Managed Services Integration

```yaml
# linode-infrastructure.yml
database:
  type: mysql
  plan: dedicated-4gb
  region: us-east
  version: 8.0
  backup_retention: 7
  
cache:
  type: redis
  plan: dedicated-1gb
  region: us-east
  version: 7.0

storage:
  type: object-storage
  region: us-east-1
  bucket: notification-assets
  cdn_enabled: true

compute:
  shared-service:
    type: droplet
    size: s-2vcpu-4gb
    region: nyc3
    image: ubuntu-22-04-x64
    
  notification-core:
    type: droplet
    size: s-1vcpu-2gb
    region: nyc3
    image: ubuntu-22-04-x64
```

## DigitalOcean Serverless Implementation

### DigitalOcean Functions Configuration

#### Function Specifications

```yaml
# digitalocean-functions.yml
project: notification-system
region: nyc1

functions:
  email-processor:
    runtime: php81
    memory: 512MB
    timeout: 300
    environment:
      MAIL_DRIVER: smtp
      MAIL_HOST: ${DO_MAIL_HOST}
      MAIL_PORT: 587
      MAIL_USERNAME: ${DO_MAIL_USERNAME}
      MAIL_PASSWORD: ${DO_MAIL_PASSWORD}
      SPACES_KEY: ${DO_SPACES_KEY}
      SPACES_SECRET: ${DO_SPACES_SECRET}
    
    routes:
      - path: /email/send
        method: POST
    
    source: ./functions/email-processor

  sms-processor:
    runtime: php81
    memory: 256MB
    timeout: 180
    environment:
      TWILIO_SID: ${TWILIO_SID}
      TWILIO_TOKEN: ${TWILIO_TOKEN}
      TWILIO_FROM: ${TWILIO_FROM}
    
    routes:
      - path: /sms/send
        method: POST
    
    source: ./functions/sms-processor

  push-processor:
    runtime: php81
    memory: 256MB
    timeout: 180
    environment:
      FCM_SERVER_KEY: ${FCM_SERVER_KEY}
      FCM_PROJECT_ID: ${FCM_PROJECT_ID}
    
    routes:
      - path: /push/send
        method: POST
    
    source: ./functions/push-processor
```

### DigitalOcean Infrastructure

#### Managed Services

```yaml
# digitalocean-infrastructure.yml
database:
  name: notification-db
  engine: mysql
  version: 8
  size: db-s-2vcpu-4gb
  region: nyc1
  num_nodes: 1

redis:
  name: notification-cache
  size: db-s-1vcpu-1gb
  region: nyc1

spaces:
  name: notification-assets
  region: nyc3
  cdn_enabled: true

droplets:
  shared-service:
    name: shared-service
    size: s-2vcpu-4gb
    region: nyc1
    image: ubuntu-22-04-x64
    
  notification-core:
    name: notification-core
    size: s-1vcpu-2gb
    region: nyc1
    image: ubuntu-22-04-x64
```

## Service Communication Patterns

### HTTP-Based Function Invocation

```php
// app/Services/ServerlessNotificationService.php
class ServerlessNotificationService
{
    private array $functionEndpoints = [
        'linode' => [
            'email' => 'https://email-processor.linode-functions.net/email/send',
            'sms' => 'https://sms-processor.linode-functions.net/sms/send',
            'push' => 'https://push-processor.linode-functions.net/push/send',
        ],
        'digitalocean' => [
            'email' => 'https://faas-nyc1-2ef2e6cc.doserverless.co/api/v1/web/fn-email-processor/email/send',
            'sms' => 'https://faas-nyc1-2ef2e6cc.doserverless.co/api/v1/web/fn-sms-processor/sms/send',
            'push' => 'https://faas-nyc1-2ef2e6cc.doserverless.co/api/v1/web/fn-push-processor/push/send',
        ],
    ];
    
    public function sendEmail(array $params): array
    {
        $provider = config('serverless.provider', 'linode');
        $endpoint = $this->functionEndpoints[$provider]['email'];
        
        return $this->invokeFunction($endpoint, $params);
    }
    
    private function invokeFunction(string $endpoint, array $params): array
    {
        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'Bearer ' . config('serverless.api_key'),
                'Content-Type' => 'application/json',
            ])
            ->post($endpoint, $params);
            
        if ($response->failed()) {
            throw new ServerlessInvocationException(
                "Function invocation failed: " . $response->body()
            );
        }
        
        return $response->json();
    }
}
```

### Queue-Based Function Triggers

```php
// app/Jobs/ServerlessNotificationJob.php
class ServerlessNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(
        private string $channel,
        private array $params,
        private string $provider = 'linode'
    ) {}
    
    public function handle(): void
    {
        $functionService = app(ServerlessNotificationService::class);
        
        match ($this->channel) {
            'email' => $functionService->sendEmail($this->params),
            'sms' => $functionService->sendSms($this->params),
            'push' => $functionService->sendPush($this->params),
            default => throw new InvalidArgumentException("Unsupported channel: {$this->channel}")
        };
    }
}
```

## Function Implementation Examples

### Email Processing Function (Node.js for Linode)

```javascript
// functions/email-processor/index.js
const nodemailer = require('nodemailer');
const { S3Client, GetObjectCommand } = require('@aws-sdk/client-s3');

exports.handler = async (event) => {
    try {
        const { to, subject, template, data } = JSON.parse(event.body);
        
        // Create transporter
        const transporter = nodemailer.createTransporter({
            host: process.env.MAIL_HOST,
            port: process.env.MAIL_PORT,
            secure: false,
            auth: {
                user: process.env.MAIL_USERNAME,
                pass: process.env.MAIL_PASSWORD
            }
        });
        
        // Get template from Linode Object Storage
        const templateContent = await getTemplate(template);
        const renderedContent = renderTemplate(templateContent, data);
        
        // Send email
        const result = await transporter.sendMail({
            from: process.env.MAIL_FROM,
            to: to,
            subject: subject,
            html: renderedContent
        });
        
        return {
            statusCode: 200,
            body: JSON.stringify({
                success: true,
                messageId: result.messageId,
                timestamp: new Date().toISOString()
            })
        };
        
    } catch (error) {
        console.error('Email processing error:', error);
        
        return {
            statusCode: 500,
            body: JSON.stringify({
                success: false,
                error: error.message,
                timestamp: new Date().toISOString()
            })
        };
    }
};

async function getTemplate(templateName) {
    const s3Client = new S3Client({
        region: 'us-east-1',
        endpoint: 'https://us-east-1.linodeobjects.com',
        credentials: {
            accessKeyId: process.env.LINODE_ACCESS_KEY,
            secretAccessKey: process.env.LINODE_SECRET_KEY
        }
    });
    
    const command = new GetObjectCommand({
        Bucket: 'notification-templates',
        Key: `${templateName}.html`
    });
    
    const response = await s3Client.send(command);
    return response.Body.transformToString();
}

function renderTemplate(template, data) {
    let rendered = template;
    for (const [key, value] of Object.entries(data)) {
        rendered = rendered.replace(new RegExp(`{{${key}}}`, 'g'), value);
    }
    return rendered;
}
```

### SMS Processing Function (PHP for DigitalOcean)

```php
<?php
// functions/sms-processor/index.php

require_once 'vendor/autoload.php';

use Twilio\Rest\Client;

function main(array $args): array
{
    try {
        $to = $args['to'] ?? throw new InvalidArgumentException('Missing "to" parameter');
        $message = $args['message'] ?? throw new InvalidArgumentException('Missing "message" parameter');
        
        // Initialize Twilio client
        $twilio = new Client(
            $_ENV['TWILIO_SID'],
            $_ENV['TWILIO_TOKEN']
        );
        
        // Send SMS
        $result = $twilio->messages->create($to, [
            'from' => $_ENV['TWILIO_FROM'],
            'body' => $message
        ]);
        
        return [
            'statusCode' => 200,
            'body' => [
                'success' => true,
                'sid' => $result->sid,
                'status' => $result->status,
                'timestamp' => date('c')
            ]
        ];
        
    } catch (Exception $e) {
        error_log('SMS processing error: ' . $e->getMessage());
        
        return [
            'statusCode' => 500,
            'body' => [
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('c')
            ]
        ];
    }
}
```

## Cost Analysis

### Linode Serverless Pricing

#### Function Costs
- **Compute**: $0.0000185 per GB-second
- **Requests**: $0.40 per million requests
- **Data Transfer**: $0.01 per GB (first 1TB free)

#### Supporting Infrastructure
- **Droplet (Shared Service)**: $48/month (4GB RAM, 2 vCPU)
- **Droplet (Notification Core)**: $24/month (2GB RAM, 1 vCPU)
- **Managed MySQL**: $60/month (4GB RAM)
- **Managed Redis**: $30/month (1GB RAM)
- **Object Storage**: $5/month (250GB)

**Total Monthly Cost**: $167 + function usage

### DigitalOcean Serverless Pricing

#### Function Costs
- **Compute**: $0.0000185 per GB-second
- **Requests**: $0.50 per million requests
- **Bandwidth**: $0.01 per GB

#### Supporting Infrastructure
- **Droplet (Shared Service)**: $48/month (4GB RAM, 2 vCPU)
- **Droplet (Notification Core)**: $24/month (2GB RAM, 1 vCPU)
- **Managed Database**: $60/month (MySQL, 4GB RAM)
- **Managed Redis**: $30/month (1GB RAM)
- **Spaces Storage**: $5/month (250GB)

**Total Monthly Cost**: $167 + function usage

### Usage-Based Cost Examples

#### Low Volume (10K notifications/month)
- **Function Execution**: ~$2/month
- **Total Cost**: ~$169/month

#### Medium Volume (100K notifications/month)
- **Function Execution**: ~$15/month
- **Total Cost**: ~$182/month

#### High Volume (1M notifications/month)
- **Function Execution**: ~$120/month
- **Total Cost**: ~$287/month

## Deployment Configuration

### Vapor Configuration for Linode

```yaml
# vapor.yml (adapted for Linode)
id: notification-system
name: notification-system
environments:
    production:
        provider: linode
        region: us-east
        
        # Traditional services on Droplets
        services:
            shared-service:
                type: droplet
                size: s-2vcpu-4gb
                build:
                    - 'composer install --no-dev'
                    - 'php artisan config:cache'
                    - 'php artisan route:cache'
                    - 'php artisan view:cache'
                
            notification-core:
                type: droplet
                size: s-1vcpu-2gb
                build:
                    - 'composer install --no-dev'
                    - 'php artisan config:cache'
                    - 'php artisan migrate --force'
        
        # Serverless functions
        functions:
            email-processor:
                runtime: nodejs18
                memory: 512
                timeout: 300
                handler: index.handler
                source: functions/email-processor
                
            sms-processor:
                runtime: nodejs18
                memory: 256
                timeout: 180
                handler: index.handler
                source: functions/sms-processor
                
            push-processor:
                runtime: nodejs18
                memory: 256
                timeout: 180
                handler: index.handler
                source: functions/push-processor
        
        # Managed services
        database: notification-db-prod
        cache: notification-cache-prod
        storage: notification-assets-prod
```

### Vapor Configuration for DigitalOcean

```yaml
# vapor-do.yml
id: notification-system-do
name: notification-system
environments:
    production:
        provider: digitalocean
        region: nyc1
        
        # Traditional services on Droplets
        services:
            shared-service:
                type: droplet
                size: s-2vcpu-4gb
                build:
                    - 'composer install --no-dev'
                    - 'php artisan config:cache'
                
            notification-core:
                type: droplet
                size: s-1vcpu-2gb
                build:
                    - 'composer install --no-dev'
                    - 'php artisan migrate --force'
        
        # Serverless functions
        functions:
            email-processor:
                runtime: php81
                memory: 512
                timeout: 300
                source: functions/email-processor
                
            sms-processor:
                runtime: php81
                memory: 256
                timeout: 180
                source: functions/sms-processor
                
            push-processor:
                runtime: php81
                memory: 256
                timeout: 180
                source: functions/push-processor
        
        # Managed services
        database: notification-db-prod
        redis: notification-cache-prod
        spaces: notification-assets-prod
```

## Advantages and Considerations

### Advantages of Linode/DO Serverless

#### Cost Benefits
- **Lower Pricing**: 60-70% cheaper than AWS Lambda
- **Predictable Costs**: Simpler pricing structure
- **No Cold Start Fees**: More cost-effective for low-volume functions
- **Transparent Billing**: Clear, understandable pricing

#### Developer Experience
- **Simpler Setup**: Less complex than AWS ecosystem
- **Better Documentation**: More straightforward guides
- **Faster Support**: Better customer service
- **Regional Options**: Good coverage for most use cases

### Considerations

#### Platform Limitations
- **Fewer Integrations**: Less ecosystem compared to AWS
- **Runtime Limitations**: Fewer runtime options
- **Scaling Limits**: Lower maximum concurrency
- **Monitoring**: Less sophisticated monitoring tools

#### Migration Considerations
- **Function Adaptation**: Need to adapt Laravel code for serverless
- **State Management**: Stateless function requirements
- **Database Connections**: Connection pooling challenges
- **File Storage**: Adaptation to object storage patterns

## Implementation Strategy

### Phase 1: Hybrid Setup
1. Deploy core services on traditional Droplets
2. Implement serverless functions for notification processing
3. Set up queue-based communication between services and functions
4. Configure monitoring and logging

### Phase 2: Function Optimization
1. Optimize function cold start times
2. Implement connection pooling for database access
3. Add comprehensive error handling and retry logic
4. Set up automated testing for functions

### Phase 3: Full Integration
1. Integrate with Laravel Vapor deployment pipeline
2. Set up CI/CD for function deployments
3. Implement comprehensive monitoring and alerting
4. Optimize costs based on usage patterns

## Conclusion

The hybrid serverless approach with Linode and DigitalOcean provides:

1. **Cost Effectiveness**: Significantly lower costs than AWS-based solutions
2. **Flexibility**: Combines traditional services with serverless functions
3. **Scalability**: Auto-scaling for notification processing workloads
4. **Simplicity**: Easier management and deployment compared to pure serverless

This architecture is ideal for teams wanting serverless benefits without AWS complexity and costs, while maintaining the reliability of traditional infrastructure for core services.

---

**Document Version**: 1.0  
**Last Updated**: February 14, 2026  
**Authors**: Codegen AI, AbdElrhman ElHodaky  
**Status**: Ready for implementation

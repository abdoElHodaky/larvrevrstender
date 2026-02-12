<div style="max-width: 38.2rem; line-height: 1.618; font-family: 'Inter', 'Segoe UI', 'Roboto', sans-serif;">

# <span style="font-size: 42px; font-weight: 700; line-height: 1.618;">📚 Documentation Hub</span>

<p style="font-size: 16px; line-height: 1.618; margin-bottom: 2rem;">Comprehensive <strong>API documentation</strong> for the Reverse Tender Platform's cloud storage integration covering file upload, management, and processing capabilities across microservices architecture.</p>

## <span style="font-size: 26px; font-weight: 600; line-height: 1.618;">🎯 Documentation Overview</span>

### <span style="font-size: 20px; font-weight: 600; line-height: 1.618;">62% Major Concepts</span>

- **🔐 JWT Authentication**: Secure API access with token-based authentication across all microservices
- **🌐 Multi-Environment Support**: Production, staging, and development endpoints with API Gateway orchestration
- **📋 Core Services Integration**: Profile management, file upload, and cloud storage with automatic optimization

<details style="border-left: 3px solid #4ECDC4; padding-left: 1rem; margin: 1rem 0;">
<summary style="font-weight: 600; cursor: pointer;">🚀 Quick Start Guide</summary>

### Authentication
All API endpoints require JWT authentication. Obtain a token from the auth-service:

```bash
curl -X POST https://api.reversetender.com/auth-service/api/login \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "password"}'
```

Include the token in all requests:
```bash
curl -H "Authorization: Bearer <your-jwt-token>" \
  https://api.reversetender.com/user-service/api/profile/avatar
```

### Base URLs
- **Production**: `https://api.reversetender.com`
- **Staging**: `https://staging-api.reversetender.com`
- **Development**: `http://localhost:8000` (API Gateway)

### Core Services

#### 1. Profile Management API
**Service**: `user-service`  
**Base Path**: `/user-service/api/profile`

Manage user avatars with automatic image optimization and cloud storage.

- **Upload Avatar**: `POST /avatar`
- **Get Avatar**: `GET /avatar`
- **Delete Avatar**: `DELETE /avatar`

[📖 View Profile API Documentation](./api/profile-endpoints.yaml)

#### 2. KYC Document Management API
**Service**: `user-service`  
**Base Path**: `/user-service/api/kyc`

Complete Know Your Customer document workflow with encryption, versioning, and verification status tracking.

- **Upload Document**: `POST /documents`
- **List Documents**: `GET /documents`
- **Delete Document**: `DELETE /documents/{id}`
- **Get KYC Status**: `GET /status`
- **Submit for Review**: `POST /submit`

[📖 View KYC API Documentation](./api/kyc-endpoints.yaml)

#### 3. VIN OCR Processing API
**Service**: `vin-ocr-service`  
**Base Path**: `/vin-ocr-service/api/vin`

Vehicle Identification Number processing with OCR, validation, and vehicle information lookup.

- **Process VIN Image**: `POST /process`
- **Validate VIN**: `POST /validate`

[📖 View VIN OCR API Documentation](./api/vin-ocr-endpoints.yaml)

### Master API Specification
[📖 View Complete API Documentation](./api/cloud-storage-api.yaml)

## 🏗️ Architecture Overview

### Microservices Architecture
The platform consists of 8 independent microservices:

1. **auth-service** (Port 8001) - Authentication & JWT management
2. **user-service** (Port 8002) - User profiles & KYC documents
3. **bidding-service** (Port 8003) - Auction & bidding operations
4. **order-service** (Port 8004) - Order management
5. **payment-service** (Port 8005) - Payment processing
6. **notification-service** (Port 8006) - Communications
7. **analytics-service** (Port 8008) - Data analysis
8. **vin-ocr-service** (Port 8007) - VIN processing

### Cloud Storage Integration
- **Multi-Cloud Support**: DigitalOcean Spaces, Linode Object Storage, AWS S3
- **Service-Specific Configuration**: Each service has its own storage configuration
- **Automatic Failover**: Graceful fallback to local storage if cloud storage fails
- **CDN Integration**: Optimized content delivery for global access

### File Processing Pipeline
```
Upload Request → Validation → Optimization → Cloud Storage → Database → CDN → Response
```

## 🔧 Integration Examples

### Upload User Avatar
```javascript
const formData = new FormData();
formData.append('avatar', fileInput.files[0]);

const response = await fetch('/user-service/api/profile/avatar', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`
  },
  body: formData
});

const result = await response.json();
console.log('Avatar URL:', result.data.url);
```

### Upload KYC Document
```javascript
const formData = new FormData();
formData.append('document', fileInput.files[0]);
formData.append('document_type', 'identity');
formData.append('description', 'Government issued ID card');

const response = await fetch('/user-service/api/kyc/documents', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`
  },
  body: formData
});
```

### Process VIN Image
```javascript
const formData = new FormData();
formData.append('image', fileInput.files[0]);
formData.append('optimize_image', 'true');

const response = await fetch('/vin-ocr-service/api/vin/process', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`
  },
  body: formData
});

const result = await response.json();
console.log('Detected VIN:', result.data.vin);
console.log('Vehicle Info:', result.data.vehicle_info);
```

## 📋 File Upload Guidelines

### Supported Formats
- **Images**: JPEG, PNG, GIF, WebP, BMP
- **Documents**: PDF, JPEG, PNG

### File Size Limits
- **Avatar Images**: 5MB maximum
- **KYC Documents**: 10MB maximum  
- **VIN Images**: 10MB maximum

### Image Optimization
All uploaded images are automatically optimized:
- **Compression**: Reduces file size while maintaining quality
- **Resizing**: Scales images to optimal dimensions
- **Format Conversion**: Converts to most efficient format
- **Progressive Loading**: Enables progressive JPEG loading

### Security Features
- **Document Encryption**: Sensitive KYC documents are encrypted at rest
- **Access Control**: File access restricted to authenticated users
- **Audit Trail**: Complete file lifecycle tracking
- **Virus Scanning**: All uploads scanned for malware

## 🔍 Error Handling

### Standard Error Response Format
```json
{
  "success": false,
  "message": "Human-readable error message",
  "errors": {
    "field_name": ["Specific validation error"]
  }
}
```

### Common HTTP Status Codes
- **200**: Success
- **400**: Bad Request - Invalid input
- **401**: Unauthorized - Authentication required
- **403**: Forbidden - Insufficient permissions
- **404**: Not Found - Resource not found
- **413**: Payload Too Large - File size exceeds limit
- **422**: Unprocessable Entity - Validation errors
- **500**: Internal Server Error

## 📊 Rate Limiting

API endpoints are rate-limited per user:
- **File Upload**: 100 requests/hour
- **File Retrieval**: 1000 requests/hour
- **VIN Processing**: 50 requests/hour

Rate limit headers are included in responses:
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1640995200
```

## 🔔 Webhooks

Real-time notifications for important events:

### KYC Status Changed
```json
{
  "event": "kyc.status.changed",
  "user_id": 123,
  "document_id": 456,
  "old_status": "under_review",
  "new_status": "approved",
  "timestamp": "2026-02-05T18:30:00Z"
}
```

### VIN Processing Completed
```json
{
  "event": "vin.processed",
  "user_id": 123,
  "vin": "1HGBH41JXMN109186",
  "confidence": 0.95,
  "is_valid": true,
  "timestamp": "2026-02-05T18:30:00Z"
}
```

## 🛠️ Development Tools

### Postman Collection
Import our Postman collection for easy API testing:
[Download Postman Collection](./postman/reverse-tender-cloud-storage.json)

### SDK Libraries
Official SDK libraries available for:
- **JavaScript/Node.js**: `npm install @reversetender/api-client`
- **PHP**: `composer require reversetender/api-client`
- **Python**: `pip install reversetender-api-client`

### Testing Environment
Use our sandbox environment for development:
- **Base URL**: `https://sandbox-api.reversetender.com`
- **Test Credentials**: Available in developer portal

## 📞 Support

### Documentation
- **API Reference**: [https://docs.reversetender.com/api](https://docs.reversetender.com/api)
- **Integration Guides**: [https://docs.reversetender.com/guides](https://docs.reversetender.com/guides)
- **FAQ**: [https://docs.reversetender.com/faq](https://docs.reversetender.com/faq)

### Contact
- **API Support**: [api-support@reversetender.com](mailto:api-support@reversetender.com)
- **Technical Support**: [support@reversetender.com](mailto:support@reversetender.com)
- **Developer Portal**: [https://developers.reversetender.com](https://developers.reversetender.com)

### Status Page
Monitor API status and uptime:
[https://status.reversetender.com](https://status.reversetender.com)

---

## 📝 Changelog

### Version 1.0.0 (2026-02-05)
- ✅ Initial release of cloud storage integration
- ✅ Profile avatar management
- ✅ KYC document workflow
- ✅ VIN OCR processing
- ✅ Multi-cloud storage support
- ✅ Automatic image optimization
- ✅ Document encryption
- ✅ Comprehensive API documentation

---

*Last updated: February 5, 2026*

# Admin Panel User Guide
## Reverse Tender Platform - Saudi Arabia

### Overview
This comprehensive guide provides administrators with detailed instructions for managing the Reverse Tender Platform, including user management, system monitoring, and compliance oversight.

## Table of Contents
1. [Getting Started](#getting-started)
2. [Dashboard Overview](#dashboard-overview)
3. [User Management](#user-management)
4. [Transaction Monitoring](#transaction-monitoring)
5. [ZATCA Compliance](#zatca-compliance)
6. [System Monitoring](#system-monitoring)
7. [Reports and Analytics](#reports-and-analytics)
8. [Security Management](#security-management)
9. [Troubleshooting](#troubleshooting)

## Getting Started

### Accessing the Admin Panel
1. Navigate to `https://admin.reversetender.sa`
2. Enter your administrator credentials
3. Complete two-factor authentication if enabled
4. You'll be redirected to the main dashboard

### Initial Setup Checklist
- [ ] Verify system status indicators
- [ ] Check pending user verifications
- [ ] Review recent transactions
- [ ] Confirm ZATCA compliance status
- [ ] Check system alerts and notifications

## Dashboard Overview

### Main Dashboard Components

#### System Health Indicators
```
🟢 All Services Operational
🟡 Minor Issues Detected
🔴 Critical Issues Require Attention
```

**Key Metrics Displayed:**
- **Active Users**: Real-time count of online users
- **Daily Transactions**: Number of completed transactions today
- **Revenue**: Total platform revenue (24h/7d/30d)
- **System Uptime**: Current uptime percentage
- **API Response Time**: Average response time across services

#### Quick Actions Panel
- **User Verification**: Approve/reject pending merchant verifications
- **Transaction Review**: Review flagged transactions
- **System Alerts**: Address critical system notifications
- **ZATCA Submission**: Submit pending invoices to ZATCA
- **Backup Status**: Monitor backup completion status

### Navigation Menu

#### Primary Sections
1. **Dashboard** - Overview and quick actions
2. **Users** - Customer and merchant management
3. **Transactions** - Order and payment monitoring
4. **Compliance** - ZATCA and regulatory oversight
5. **System** - Technical monitoring and configuration
6. **Reports** - Analytics and business intelligence
7. **Security** - Access control and audit logs

## User Management

### Customer Management

#### Viewing Customer Profiles
1. Navigate to **Users > Customers**
2. Use filters to find specific customers:
   - **Status**: Active, Suspended, Pending
   - **Registration Date**: Date range selector
   - **Location**: City/region filter
   - **Activity Level**: Based on transaction history

#### Customer Profile Details
```
Customer Information:
├── Personal Details
│   ├── Name (Arabic/English)
│   ├── Email Address
│   ├── Phone Number
│   └── Registration Date
├── Location Information
│   ├── City/District
│   ├── GPS Coordinates
│   └── Service Area
├── Vehicle Information
│   ├── Registered Vehicles
│   ├── VIN Numbers
│   └── Primary Vehicle
├── Transaction History
│   ├── Part Requests Created
│   ├── Orders Completed
│   ├── Total Spent
│   └── Average Order Value
└── Compliance Status
    ├── ZATCA Tax ID
    ├── Verification Status
    └── Document Uploads
```

#### Customer Actions
- **View Profile**: Complete customer information
- **Edit Details**: Modify customer information
- **Suspend Account**: Temporarily disable account
- **Send Notification**: Direct message to customer
- **Transaction History**: View all customer transactions
- **Export Data**: Download customer data (GDPR compliance)

### Merchant Management

#### Merchant Verification Process
1. **Pending Verifications**
   - Navigate to **Users > Merchants > Pending**
   - Review submitted documents:
     - Commercial Registration
     - Tax Certificate
     - Business License
     - Bank Account Details

2. **Verification Steps**
   ```
   Document Review Checklist:
   ☐ Commercial Registration Number Valid
   ☐ Tax Number Verified with ZATCA
   ☐ Business License Current
   ☐ Bank Account Ownership Confirmed
   ☐ Physical Address Verified
   ☐ Contact Information Validated
   ```

3. **Approval/Rejection**
   - **Approve**: Grant full merchant access
   - **Request More Info**: Send specific document requests
   - **Reject**: Provide detailed rejection reasons

#### Merchant Profile Management
```
Merchant Information:
├── Business Details
│   ├── Business Name (Arabic/English)
│   ├── Commercial Registration
│   ├── Tax Number (ZATCA)
│   └── Business Type
├── Contact Information
│   ├── Primary Contact Person
│   ├── Email Address
│   ├── Phone Number
│   └── Physical Address
├── Financial Information
│   ├── Bank Account Details
│   ├── Payment Methods
│   └── Fee Structure
├── Performance Metrics
│   ├── Rating (1-5 stars)
│   ├── Response Time
│   ├── Order Completion Rate
│   └── Customer Satisfaction
└── Compliance Status
    ├── ZATCA Registration
    ├── License Validity
    └── Insurance Coverage
```

#### Merchant Actions
- **Verify Documents**: Complete verification process
- **Update Rating**: Adjust merchant rating based on performance
- **Suspend Operations**: Temporarily halt merchant activities
- **Financial Review**: Examine payment history and fees
- **Performance Analytics**: Detailed business metrics

## Transaction Monitoring

### Order Management

#### Order Status Tracking
```
Order Lifecycle:
1. Part Request Created → Customer submits requirement
2. Bids Received → Merchants submit offers
3. Bid Accepted → Customer selects winning bid
4. Order Created → Formal order established
5. Payment Processing → Invoice generated and paid
6. Order Fulfillment → Merchant ships part
7. Delivery Confirmation → Customer confirms receipt
8. Order Completed → Transaction finalized
```

#### Order Dashboard
- **Active Orders**: Currently in progress
- **Pending Payment**: Awaiting customer payment
- **Shipping**: Orders in transit
- **Disputed**: Orders requiring intervention
- **Completed**: Successfully finished orders

#### Order Details View
```
Order Information:
├── Order Number: ORD-260130-ABCD
├── Customer: Ahmed Mohammed
├── Merchant: Riyadh Auto Parts
├── Part Details
│   ├── Description: Front brake pads
│   ├── Part Number: BP-2023-HONDA
│   ├── Vehicle: 2023 Honda Civic
│   └── Condition: New
├── Financial Details
│   ├── Part Cost: 450 SAR
│   ├── Delivery Fee: 50 SAR
│   ├── Platform Fee: 25 SAR (5%)
│   ├── VAT: 78.75 SAR (15%)
│   └── Total: 603.75 SAR
├── Timeline
│   ├── Request Created: 2026-01-30 10:00
│   ├── Bid Accepted: 2026-01-30 14:30
│   ├── Payment Completed: 2026-01-30 15:15
│   └── Estimated Delivery: 2026-02-01
└── Status: Payment Confirmed
```

### Payment Monitoring

#### Payment Dashboard
- **Daily Revenue**: Total payments processed today
- **Payment Methods**: Breakdown by payment type
- **Failed Payments**: Transactions requiring attention
- **Refund Requests**: Customer refund requests
- **Gateway Performance**: Payment gateway success rates

#### Payment Gateway Monitoring
```
Gateway Performance:
├── Stripe
│   ├── Success Rate: 97.2%
│   ├── Average Processing Time: 2.3s
│   └── Daily Volume: 1,247 transactions
├── PayPal
│   ├── Success Rate: 95.8%
│   ├── Average Processing Time: 3.1s
│   └── Daily Volume: 423 transactions
├── Mada (Saudi)
│   ├── Success Rate: 98.1%
│   ├── Average Processing Time: 1.8s
│   └── Daily Volume: 2,156 transactions
└── STC Pay (Saudi)
    ├── Success Rate: 96.4%
    ├── Average Processing Time: 4.2s
    └── Daily Volume: 891 transactions
```

## ZATCA Compliance

### Invoice Management

#### ZATCA Invoice Requirements
All invoices must include:
- **Seller Information**: Business name, tax number, address
- **Buyer Information**: Customer name, tax ID (if applicable)
- **Invoice Details**: Number, date, due date
- **Line Items**: Description, quantity, unit price, total
- **Tax Calculation**: 15% VAT on applicable items
- **QR Code**: ZATCA-compliant QR code
- **Digital Signature**: Cryptographic signature

#### Invoice Status Monitoring
```
ZATCA Submission Status:
├── Draft (45) - Invoices being prepared
├── Pending Submission (12) - Ready for ZATCA
├── Submitted (1,247) - Sent to ZATCA portal
├── Approved (1,198) - ZATCA approved
├── Rejected (3) - Requires correction
└── Failed (1) - Technical submission error
```

#### ZATCA Compliance Dashboard
- **Submission Rate**: Percentage of invoices submitted on time
- **Approval Rate**: ZATCA approval percentage
- **Rejection Reasons**: Common rejection causes
- **Tax Collection**: Total VAT collected and remitted
- **Audit Trail**: Complete transaction history

### Tax Reporting

#### Monthly Tax Summary
```
Tax Report - January 2026:
├── Total Sales: 2,450,000 SAR
├── Taxable Sales: 2,200,000 SAR
├── VAT Collected: 330,000 SAR
├── Platform Fees: 122,500 SAR
├── VAT on Fees: 18,375 SAR
└── Net VAT Payable: 348,375 SAR
```

#### ZATCA Submission Process
1. **Generate Report**: Monthly tax summary
2. **Review Invoices**: Verify all invoices included
3. **Submit to ZATCA**: Electronic submission
4. **Track Status**: Monitor submission status
5. **Handle Rejections**: Address any issues
6. **Archive Records**: Maintain audit trail

## System Monitoring

### Performance Metrics

#### Service Health Dashboard
```
Microservices Status:
├── User Service
│   ├── Status: ✅ Healthy
│   ├── Response Time: 145ms
│   ├── CPU Usage: 23%
│   └── Memory Usage: 512MB
├── Order Service
│   ├── Status: ✅ Healthy
│   ├── Response Time: 167ms
│   ├── CPU Usage: 31%
│   └── Memory Usage: 678MB
├── Payment Service
│   ├── Status: ⚠️ Warning
│   ├── Response Time: 234ms
│   ├── CPU Usage: 67%
│   └── Memory Usage: 1.2GB
└── Notification Service
    ├── Status: ✅ Healthy
    ├── Response Time: 89ms
    ├── CPU Usage: 18%
    └── Memory Usage: 345MB
```

#### Infrastructure Monitoring
- **Database Performance**: Query response times, connection pool
- **Redis Cache**: Hit rate, memory usage, connection count
- **Load Balancer**: Request distribution, health checks
- **CDN Performance**: Cache hit rate, bandwidth usage
- **SSL Certificates**: Expiration dates, renewal status

### Alert Management

#### Alert Categories
1. **Critical**: Service outages, security breaches
2. **Warning**: Performance degradation, high resource usage
3. **Info**: Deployment notifications, scheduled maintenance

#### Alert Response Procedures
```
Alert Response Workflow:
1. Alert Received → Immediate notification
2. Initial Assessment → Determine severity
3. Escalation → Notify appropriate team
4. Investigation → Identify root cause
5. Resolution → Implement fix
6. Verification → Confirm resolution
7. Documentation → Update incident log
```

## Reports and Analytics

### Business Intelligence Dashboard

#### Key Performance Indicators (KPIs)
```
Platform KPIs:
├── User Growth
│   ├── New Registrations: +15% MoM
│   ├── Active Users: 12,450 (30-day)
│   └── User Retention: 78%
├── Transaction Metrics
│   ├── Order Volume: +22% MoM
│   ├── Average Order Value: 485 SAR
│   └── Completion Rate: 94.2%
├── Revenue Metrics
│   ├── Platform Revenue: 125,000 SAR/month
│   ├── GMV: 2,500,000 SAR/month
│   └── Revenue Growth: +18% MoM
└── Operational Metrics
    ├── Response Time: 156ms avg
    ├── Uptime: 99.97%
    └── Customer Satisfaction: 4.6/5
```

#### Custom Reports
- **Financial Reports**: Revenue, fees, tax collection
- **User Analytics**: Registration trends, activity patterns
- **Transaction Reports**: Order volumes, success rates
- **Performance Reports**: System metrics, response times
- **Compliance Reports**: ZATCA submissions, audit trails

### Data Export and Integration

#### Export Formats
- **PDF**: Formatted reports for presentation
- **Excel**: Detailed data for analysis
- **CSV**: Raw data for external systems
- **JSON**: API integration format

#### Scheduled Reports
- **Daily**: Transaction summary, system health
- **Weekly**: User activity, performance metrics
- **Monthly**: Financial reports, compliance summary
- **Quarterly**: Business review, strategic metrics

## Security Management

### Access Control

#### User Roles and Permissions
```
Admin Role Hierarchy:
├── Super Admin
│   ├── Full system access
│   ├── User management
│   ├── System configuration
│   └── Security settings
├── Operations Manager
│   ├── Transaction monitoring
│   ├── User support
│   ├── Report generation
│   └── Basic system monitoring
├── Compliance Officer
│   ├── ZATCA management
│   ├── Tax reporting
│   ├── Audit trail access
│   └── Regulatory compliance
└── Support Agent
    ├── Customer support
    ├── Basic user management
    ├── Transaction inquiry
    └── Report viewing
```

#### Security Audit Log
- **Login Attempts**: Successful and failed logins
- **Permission Changes**: Role modifications
- **Data Access**: Sensitive data viewing
- **System Changes**: Configuration modifications
- **Export Activities**: Data export events

### Incident Management

#### Security Incident Response
1. **Detection**: Automated alerts or manual reporting
2. **Classification**: Determine incident severity
3. **Containment**: Immediate threat mitigation
4. **Investigation**: Root cause analysis
5. **Recovery**: System restoration
6. **Documentation**: Incident report and lessons learned

#### Common Security Scenarios
- **Suspicious Login Activity**: Multiple failed attempts
- **Data Access Anomalies**: Unusual data access patterns
- **Payment Fraud**: Suspicious transaction patterns
- **System Intrusion**: Unauthorized access attempts

## Troubleshooting

### Common Issues and Solutions

#### User Account Issues
**Problem**: Customer cannot log in
**Solution**:
1. Check account status (active/suspended)
2. Verify email address format
3. Reset password if needed
4. Check for account lockout
5. Review security logs for failed attempts

**Problem**: Merchant verification stuck
**Solution**:
1. Review submitted documents
2. Check document format and quality
3. Verify business registration with authorities
4. Contact merchant for additional information
5. Escalate to compliance team if needed

#### Payment Issues
**Problem**: Payment gateway failures
**Solution**:
1. Check gateway status dashboard
2. Review error logs for specific failures
3. Test gateway connectivity
4. Contact gateway support if needed
5. Switch to backup gateway if available

**Problem**: ZATCA submission failures
**Solution**:
1. Verify invoice format compliance
2. Check ZATCA certificate validity
3. Review submission error messages
4. Retry submission with corrections
5. Contact ZATCA support for technical issues

#### System Performance Issues
**Problem**: Slow response times
**Solution**:
1. Check service health dashboard
2. Review database performance metrics
3. Monitor CPU and memory usage
4. Check for high traffic patterns
5. Scale resources if needed

**Problem**: Service unavailability
**Solution**:
1. Check service status indicators
2. Review error logs and alerts
3. Restart affected services
4. Verify database connectivity
5. Escalate to technical team

### Emergency Procedures

#### System Outage Response
1. **Immediate Actions**:
   - Activate incident response team
   - Notify stakeholders
   - Begin service restoration

2. **Communication**:
   - Update status page
   - Notify users via multiple channels
   - Provide regular updates

3. **Recovery**:
   - Implement backup systems
   - Restore from backups if needed
   - Verify system functionality

#### Data Breach Response
1. **Containment**:
   - Isolate affected systems
   - Preserve evidence
   - Prevent further access

2. **Assessment**:
   - Determine scope of breach
   - Identify affected data
   - Assess potential impact

3. **Notification**:
   - Notify authorities as required
   - Inform affected users
   - Coordinate with legal team

### Support Contacts

#### Internal Support
- **Technical Support**: tech-support@reversetender.sa
- **Security Team**: security@reversetender.sa
- **Compliance Team**: compliance@reversetender.sa
- **Emergency Hotline**: +966-11-XXX-XXXX (24/7)

#### External Support
- **ZATCA Support**: zatca-support@zatca.gov.sa
- **Payment Gateway Support**: Various provider contacts
- **Infrastructure Support**: AWS/Azure support channels
- **Legal Counsel**: legal@reversetender.sa

---

**Document Version**: 1.0  
**Last Updated**: January 30, 2026  
**Next Review**: April 30, 2026


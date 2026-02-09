# Phase 5: Auth and User Services Migration

This document outlines the comprehensive plan for migrating the Auth and User services from MySQL to PostgreSQL, building on the lessons learned from the Gateway Service pilot migration.

## Overview

Phase 5 focuses on migrating the foundational authentication and user management services, which are critical dependencies for most other services in the microservices architecture.

### Services in Scope
- **Auth Service** (Priority 1) - Authentication and authorization
- **User Service** (Priority 1) - User profile and account management

### Migration Strategy
- **Sequential Migration**: Auth Service first, then User Service
- **Dependency-Aware**: User Service depends on Auth Service
- **Zero-Downtime**: Parallel database operation with gradual traffic shifting
- **Comprehensive Validation**: Enhanced validation based on pilot learnings

## Pre-Migration Assessment

### Service Dependencies
```
Auth Service:
├── Dependencies: None (foundational service)
├── Dependents: User Service, Order Service, Payment Service, Bidding Service, Auction Service, Notification Service, VIN OCR Service
└── Critical Path: Blocks all other service migrations

User Service:
├── Dependencies: Auth Service
├── Dependents: Order Service, Payment Service, Bidding Service, Auction Service, Notification Service
└── Impact: High - affects all user-facing functionality
```

### Data Complexity Analysis

#### Auth Service Database
- **Estimated Size**: 50-200 MB
- **Key Tables**: 
  - `users` (authentication credentials)
  - `roles` (user roles and permissions)
  - `permissions` (granular permissions)
  - `oauth_tokens` (API tokens)
  - `password_resets` (password reset tokens)
  - `sessions` (user sessions)

#### User Service Database
- **Estimated Size**: 200-1000 MB
- **Key Tables**:
  - `user_profiles` (user profile information)
  - `user_preferences` (user settings)
  - `user_addresses` (shipping/billing addresses)
  - `user_documents` (uploaded documents)
  - `user_activity_logs` (audit trail)

### Expected Challenges

#### Auth Service
- **High Availability Requirements**: Cannot afford downtime
- **Security Sensitivity**: Password hashes and tokens require careful handling
- **Session Management**: Active sessions must remain valid
- **Token Validation**: OAuth tokens must continue working

#### User Service
- **Large Dataset**: Potentially millions of user records
- **File References**: Document storage paths and references
- **Data Relationships**: Complex relationships with other services
- **Privacy Compliance**: GDPR/CCPA data handling requirements

## Migration Timeline

### Phase 5A: Auth Service Migration (Week 8)

#### Day 1-2: Pre-Migration Preparation
- [ ] **Infrastructure Validation**
  - Validate PostgreSQL Auth Service database setup
  - Test PgBouncer connection pooling for auth workload
  - Verify pgcrypto extension for password hashing
  - Validate backup and recovery procedures

- [ ] **Security Assessment**
  - Review password hashing algorithms compatibility
  - Validate OAuth token encryption/decryption
  - Test session storage and retrieval
  - Verify HTTPS/TLS configuration

- [ ] **Performance Baseline**
  - Establish Auth Service performance metrics
  - Document peak authentication load patterns
  - Measure current response times and throughput
  - Identify performance-critical queries

#### Day 3: Schema Migration and Validation
- [ ] **Schema Conversion**
  - Convert Auth Service MySQL schema to PostgreSQL
  - Optimize indexes for PostgreSQL query planner
  - Validate constraint migration (foreign keys, unique constraints)
  - Test stored procedures and function conversion

- [ ] **Data Migration**
  - Execute data migration with enhanced validation
  - Verify password hash integrity
  - Validate OAuth token migration
  - Test session data migration

#### Day 4: Functional Testing
- [ ] **Authentication Testing**
  - Test user login/logout functionality
  - Validate password reset workflows
  - Test OAuth token generation and validation
  - Verify role-based access control

- [ ] **Integration Testing**
  - Test Auth Service API endpoints
  - Validate service-to-service authentication
  - Test load balancer health checks
  - Verify monitoring and logging integration

#### Day 5: Performance Validation and Go-Live
- [ ] **Performance Testing**
  - Load test authentication endpoints
  - Validate concurrent login handling
  - Test token validation performance
  - Measure database connection pooling efficiency

- [ ] **Go-Live Preparation**
  - Final validation and approval
  - Traffic switching to PostgreSQL
  - Monitor system behavior
  - Validate rollback procedures

### Phase 5B: User Service Migration (Week 9)

#### Day 1-2: Pre-Migration Preparation
- [ ] **Dependency Validation**
  - Verify Auth Service PostgreSQL integration
  - Test user authentication against new Auth Service
  - Validate cross-service communication
  - Test service discovery and load balancing

- [ ] **Data Assessment**
  - Analyze User Service data volume and complexity
  - Identify large tables requiring special handling
  - Plan batch migration strategy for user documents
  - Assess data privacy and compliance requirements

#### Day 3-4: Schema and Data Migration
- [ ] **Schema Conversion**
  - Convert User Service MySQL schema to PostgreSQL
  - Optimize for PostgreSQL-specific features (JSONB, arrays)
  - Validate foreign key relationships with Auth Service
  - Test full-text search capabilities with pg_trgm

- [ ] **Data Migration**
  - Execute large-scale data migration with progress monitoring
  - Handle user document references and file paths
  - Validate user profile data integrity
  - Test user preference and settings migration

#### Day 5: Integration and Go-Live
- [ ] **Integration Testing**
  - Test complete Auth + User service integration
  - Validate user registration and profile management
  - Test user document upload/download functionality
  - Verify user activity logging and audit trails

- [ ] **Go-Live and Monitoring**
  - Switch User Service traffic to PostgreSQL
  - Monitor user-facing functionality
  - Validate performance under production load
  - Confirm rollback readiness

## Enhanced Migration Procedures

### Lessons Learned Integration

Based on Gateway Service pilot results, Phase 5 incorporates:

#### Performance Optimizations
- **Batch Size Tuning**: Optimized batch sizes based on pilot performance data
- **Connection Pooling**: Enhanced PgBouncer configuration for auth workloads
- **Index Optimization**: PostgreSQL-specific index strategies
- **Query Optimization**: Rewritten queries for PostgreSQL query planner

#### Validation Enhancements
- **Zero-Tolerance Validation**: Enhanced row count and data integrity checks
- **Security Validation**: Additional checks for password hashes and tokens
- **Performance Regression Detection**: Automated performance comparison
- **Business Logic Validation**: Service-specific functional testing

#### Risk Mitigation
- **Gradual Traffic Shifting**: Percentage-based traffic migration
- **Real-Time Monitoring**: Enhanced monitoring during migration
- **Automated Rollback**: Improved rollback trigger conditions
- **Stakeholder Communication**: Regular status updates and approvals

### Migration Scripts Enhancement

#### Auth Service Specific Enhancements

```php
// Enhanced password hash validation
private function validatePasswordHashes($mysqlData, $postgresData)
{
    foreach ($mysqlData as $index => $mysqlRow) {
        $mysqlHash = $mysqlRow['password'];
        $postgresHash = $postgresData[$index]['password'];
        
        // Validate hash integrity
        if ($mysqlHash !== $postgresHash) {
            throw new Exception("Password hash mismatch for user ID: {$mysqlRow['id']}");
        }
        
        // Validate hash format
        if (!password_verify('test', $mysqlHash)) {
            // Hash format validation logic
        }
    }
}

// OAuth token migration with encryption validation
private function migrateOAuthTokens($tokens)
{
    foreach ($tokens as $token) {
        // Validate token encryption
        $decrypted = decrypt($token['access_token']);
        if (!$decrypted) {
            throw new Exception("Token decryption failed for token ID: {$token['id']}");
        }
        
        // Re-encrypt for PostgreSQL
        $reencrypted = encrypt($decrypted);
        $token['access_token'] = $reencrypted;
    }
    
    return $tokens;
}
```

#### User Service Specific Enhancements

```php
// Large dataset migration with progress tracking
private function migrateLargeUserTable($tableName, $batchSize = 5000)
{
    $totalRows = $this->getRowCount($tableName);
    $processed = 0;
    
    while ($processed < $totalRows) {
        $batch = $this->getBatch($tableName, $processed, $batchSize);
        
        // Process user documents and file references
        foreach ($batch as &$row) {
            if (isset($row['document_path'])) {
                $row['document_path'] = $this->validateDocumentPath($row['document_path']);
            }
        }
        
        $this->insertBatch($batch);
        $processed += count($batch);
        
        // Progress reporting
        $percentage = ($processed / $totalRows) * 100;
        $this->logger->info("User migration progress: {$percentage}% ({$processed}/{$totalRows})");
        
        // Memory management
        unset($batch);
        gc_collect_cycles();
    }
}

// User document validation and migration
private function validateDocumentPath($path)
{
    // Validate document exists
    if (!file_exists($path)) {
        $this->logger->warning("Document not found: {$path}");
        return null;
    }
    
    // Update path for new storage structure if needed
    return $this->updateDocumentPath($path);
}
```

### Security Considerations

#### Auth Service Security
- **Password Hash Validation**: Verify bcrypt/argon2 hash integrity
- **Token Encryption**: Validate OAuth token encryption/decryption
- **Session Security**: Ensure session data remains secure
- **Audit Logging**: Enhanced logging for security events

#### User Service Security
- **PII Protection**: Ensure personal data remains encrypted
- **Document Security**: Validate file access permissions
- **Data Anonymization**: Support for GDPR compliance
- **Access Logging**: Comprehensive user activity logging

### Performance Expectations

#### Auth Service
- **Login Performance**: 10-20% improvement with PostgreSQL
- **Token Validation**: 15-25% improvement with optimized indexes
- **Concurrent Authentication**: Better handling of peak loads
- **Database Connections**: Improved connection pooling efficiency

#### User Service
- **Profile Queries**: 20-30% improvement with JSONB
- **Search Performance**: 50-100% improvement with full-text search
- **Large Dataset Queries**: Better performance with PostgreSQL optimizer
- **Concurrent User Operations**: Improved under high load

### Monitoring and Alerting

#### Key Metrics to Monitor
- **Authentication Success Rate**: Should remain > 99.9%
- **Login Response Time**: Should not exceed baseline + 20%
- **Token Validation Time**: Should improve or remain stable
- **User Profile Load Time**: Should improve with PostgreSQL
- **Database Connection Pool**: Should remain < 80% utilization
- **Error Rates**: Should remain < 0.1%

#### Alert Conditions
- **Authentication Failure Spike**: > 5% failure rate
- **Response Time Degradation**: > 50% slower than baseline
- **Database Connection Issues**: Pool utilization > 90%
- **Data Integrity Issues**: Any row count mismatches
- **Security Events**: Failed token validations or suspicious activity

### Rollback Procedures

#### Auth Service Rollback
- **Emergency Rollback**: < 3 minutes (critical for authentication)
- **Configuration Rollback**: Switch load balancer to MySQL
- **Data Rollback**: Restore from pre-migration backup
- **Session Handling**: Minimize session disruption

#### User Service Rollback
- **Coordinated Rollback**: Ensure Auth Service compatibility
- **Data Synchronization**: Handle any new user data created during migration
- **Document References**: Validate file path references after rollback
- **Dependency Management**: Coordinate with dependent services

### Success Criteria

#### Technical Success Criteria
- [ ] Zero data loss or corruption
- [ ] Authentication success rate > 99.9%
- [ ] Performance meets or exceeds baseline
- [ ] All functional tests pass
- [ ] Security validation passes
- [ ] Integration tests with dependent services pass

#### Business Success Criteria
- [ ] No user-facing authentication issues
- [ ] User profile functionality preserved
- [ ] No impact on user experience
- [ ] Compliance requirements maintained
- [ ] Audit trails preserved

### Risk Assessment

#### High-Risk Areas
1. **Authentication Downtime**: Could affect all services
2. **Password Hash Corruption**: Could lock out all users
3. **Token Invalidation**: Could break API integrations
4. **User Data Loss**: Could impact user experience significantly

#### Mitigation Strategies
1. **Parallel Authentication**: Run both systems during transition
2. **Hash Validation**: Comprehensive password hash integrity checks
3. **Token Migration**: Careful OAuth token migration with validation
4. **Incremental Migration**: Gradual user data migration with validation

### Post-Migration Tasks

#### Immediate (Within 24 hours)
- [ ] Monitor authentication and user service performance
- [ ] Validate all user-facing functionality
- [ ] Check integration with dependent services
- [ ] Verify security controls and audit logging

#### Short-term (Within 1 week)
- [ ] Performance optimization based on production data
- [ ] Update monitoring dashboards and alerts
- [ ] Document lessons learned for Phase 6
- [ ] Prepare for business logic services migration

#### Long-term (Within 1 month)
- [ ] Complete performance analysis and optimization
- [ ] Update security procedures and documentation
- [ ] Plan Phase 6: Business Logic Services (Order, Payment, Bidding)
- [ ] Conduct team retrospective and process improvements

## Conclusion

Phase 5 represents a critical milestone in the PostgreSQL migration project, establishing the foundational authentication and user management services on the new database platform. Success in this phase enables the migration of all remaining business logic services and ensures the security and reliability of the entire system.

The enhanced procedures and lessons learned from the Gateway Service pilot provide confidence in the migration approach, while the comprehensive validation and monitoring ensure business continuity throughout the process.

---

*For detailed technical procedures, refer to the migration scripts in `migration/scripts/` and the comprehensive setup guide in `migration/SETUP-GUIDE.md`.*


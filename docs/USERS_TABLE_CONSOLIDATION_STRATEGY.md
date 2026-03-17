# Users Table Consolidation Strategy

**Date**: March 17, 2026  
**Branch**: v2  
**Priority**: CRITICAL  
**Issue**: Multiple users tables across 4 services violating microservices principles

---

## Problem Analysis

### Current State
Multiple services have their own users tables, creating data duplication and architectural violations:

1. **auth-service**: `2024_01_01_000000_create_users_table.php`
2. **user-service**: `2024_01_01_000000_create_users_table.php`
3. **order-service**: `2024_01_01_000000_create_users_table.php`
4. **gateway-service**: `2024_01_01_000000_create_users_table.php`

### Schema Differences

#### auth-service users table (Recommended as Master)
```php
// Enhanced authentication-focused schema
$table->enum('type', ['customer', 'merchant', 'admin'])->default('customer');
$table->enum('status', ['active', 'inactive', 'suspended', 'banned'])->default('active');

// Social authentication fields
$table->string('google_id')->nullable();
$table->string('facebook_id')->nullable();
$table->string('twitter_id')->nullable();
$table->string('github_id')->nullable();
$table->string('avatar')->nullable();
$table->string('provider')->nullable();

// Two-factor authentication
$table->boolean('two_factor_enabled')->default(false);
$table->text('two_factor_secret')->nullable();
$table->text('two_factor_recovery_codes')->nullable();

// Extended login tracking
$table->integer('login_count')->default(0);
$table->json('metadata')->nullable();
$table->softDeletes();

// Indexes
$table->index(['email', 'phone']);
$table->index(['type', 'status']);
$table->index('last_login_at');
```

#### user-service users table (Profile-focused)
```php
// Basic profile management schema
$table->enum('role', ['customer', 'merchant', 'admin'])->default('customer');
$table->enum('status', ['active', 'inactive', 'suspended'])->default('active');

// Basic login tracking only
$table->timestamp('last_login_at')->nullable();
$table->string('last_login_ip')->nullable();

// No social auth, no 2FA, no soft deletes
// Indexes
$table->index(['email', 'status']);
$table->index(['phone', 'status']);
$table->index('role');
```

---

## Consolidation Strategy

### Phase 1: Design & Architecture (Week 1)

#### 1.1 Single Source of Truth Decision
**Recommendation**: Use **auth-service** as the master users table because:
- ✅ More comprehensive schema (social auth, 2FA, extended tracking)
- ✅ Authentication is the natural owner of user identity
- ✅ Already has soft deletes for data integrity
- ✅ Better indexing strategy for auth operations

#### 1.2 Service Contracts Design
Create standardized contracts for user data access:

```php
// Shared/Contracts/UserServiceContract.php
interface UserServiceContract
{
    public function getUserById(int $userId): ?User;
    public function getUserByEmail(string $email): ?User;
    public function getUserByPhone(string $phone): ?User;
    public function createUser(array $userData): User;
    public function updateUser(int $userId, array $userData): User;
    public function deleteUser(int $userId): bool;
    public function getUsersByType(string $type): Collection;
    public function verifyUserCredentials(string $identifier, string $password): ?User;
}
```

#### 1.3 RPC Service Methods
Extend auth-service RPC to provide user data access:

```php
// AuthService RPC Methods
class UserDataRpcController
{
    public function getUser(int $userId): array
    public function getUserByEmail(string $email): array
    public function getUserByPhone(string $phone): array
    public function getUserProfile(int $userId): array
    public function updateUserProfile(int $userId, array $data): array
    public function getUsersByType(string $type, int $page = 1): array
    public function searchUsers(array $criteria): array
}
```

### Phase 2: Implementation (Week 2)

#### 2.1 Extend auth-service Schema
Merge missing fields from other services into auth-service:

```sql
-- Migration: 2026_03_17_000001_extend_users_table_for_consolidation.php
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_completed_at TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS kyc_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending';
ALTER TABLE users ADD COLUMN IF NOT EXISTS merchant_verified_at TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS preferences JSON NULL;
```

#### 2.2 Create User Data RPC Client
```php
// Shared/RPC/Clients/UserDataServiceClient.php
class UserDataServiceClient extends BaseRpcClient
{
    protected string $serviceName = 'auth-service';
    protected int $servicePort = 8000;
    
    public function getUser(int $userId): ?array
    {
        return $this->call('UserData.getUser', ['userId' => $userId]);
    }
    
    public function getUserByEmail(string $email): ?array
    {
        return $this->call('UserData.getUserByEmail', ['email' => $email]);
    }
    
    // ... other methods
}
```

#### 2.3 Update Service Dependencies
Replace direct database access with RPC calls:

```php
// Before (in user-service, order-service, gateway-service)
$user = User::find($userId);

// After
$userDataClient = app(UserDataServiceClient::class);
$userData = $userDataClient->getUser($userId);
$user = $userData ? new UserDto($userData) : null;
```

### Phase 3: Data Migration (Week 3)

#### 3.1 Data Consolidation Script
```php
// database/migrations/2026_03_17_000002_consolidate_users_data.php
class ConsolidateUsersData extends Migration
{
    public function up(): void
    {
        // 1. Export data from user-service, order-service, gateway-service
        // 2. Merge into auth-service users table
        // 3. Handle conflicts (email/phone duplicates)
        // 4. Update foreign key references
        // 5. Verify data integrity
    }
}
```

#### 3.2 Foreign Key Updates
Update all services to reference auth-service users:

```sql
-- In user-service
ALTER TABLE customer_profiles 
ADD CONSTRAINT fk_customer_profiles_auth_users 
FOREIGN KEY (user_id) REFERENCES auth_service.users(id);

-- In order-service  
ALTER TABLE orders 
ADD CONSTRAINT fk_orders_auth_users 
FOREIGN KEY (user_id) REFERENCES auth_service.users(id);
```

### Phase 4: Service Updates (Week 4)

#### 4.1 Remove Local Users Tables
```php
// Migration: 2026_03_17_000003_remove_duplicate_users_tables.php
class RemoveDuplicateUsersTables extends Migration
{
    public function up(): void
    {
        // Drop users tables from:
        // - user-service
        // - order-service  
        // - gateway-service
        Schema::dropIfExists('users');
    }
}
```

#### 4.2 Update Model References
```php
// Before: Local User model
class CustomerProfile extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

// After: RPC-based relationship
class CustomerProfile extends Model
{
    public function getUserData(): ?array
    {
        $client = app(UserDataServiceClient::class);
        return $client->getUser($this->user_id);
    }
}
```

---

## Implementation Checklist

### Week 1: Design & Architecture
- [ ] Finalize auth-service as master users table
- [ ] Design UserServiceContract interface
- [ ] Plan RPC method signatures
- [ ] Create data mapping strategy
- [ ] Design migration rollback plan

### Week 2: RPC Implementation
- [ ] Extend auth-service with UserDataRpcController
- [ ] Create UserDataServiceClient in shared package
- [ ] Add user data DTOs and validation
- [ ] Implement caching layer for user data
- [ ] Add comprehensive error handling

### Week 3: Data Migration
- [ ] Create data export scripts for each service
- [ ] Implement conflict resolution logic
- [ ] Execute data consolidation migration
- [ ] Verify data integrity and completeness
- [ ] Update all foreign key references

### Week 4: Service Updates
- [ ] Update all services to use RPC client
- [ ] Remove local users tables and models
- [ ] Update authentication flows
- [ ] Update authorization middleware
- [ ] Comprehensive testing across all services

---

## Risk Mitigation

### Data Loss Prevention
1. **Full Backup**: Complete database backup before migration
2. **Staged Migration**: Migrate one service at a time
3. **Rollback Plan**: Detailed rollback procedures for each phase
4. **Data Validation**: Comprehensive data integrity checks

### Service Availability
1. **Blue-Green Deployment**: Deploy changes without downtime
2. **Circuit Breaker**: Implement fallback mechanisms
3. **Monitoring**: Enhanced monitoring during migration
4. **Gradual Rollout**: Feature flags for gradual activation

### Performance Considerations
1. **Caching**: Implement Redis caching for user data
2. **Connection Pooling**: Optimize RPC connection management
3. **Batch Operations**: Efficient bulk data operations
4. **Load Testing**: Validate performance under load

---

## Success Metrics

### Technical Metrics
- ✅ Single users table in auth-service only
- ✅ All services use RPC for user data access
- ✅ Zero data loss during migration
- ✅ Response time < 100ms for user data queries
- ✅ 99.9% uptime during migration

### Business Metrics
- ✅ No user authentication disruptions
- ✅ All user profiles accessible
- ✅ No duplicate user accounts
- ✅ Consistent user data across services

---

## Post-Consolidation Benefits

### Architectural Benefits
1. **Single Source of Truth**: Eliminates data duplication
2. **Microservices Compliance**: Proper service boundaries
3. **Data Consistency**: No synchronization issues
4. **Simplified Maintenance**: Single users schema to maintain

### Operational Benefits
1. **Reduced Complexity**: Fewer database schemas to manage
2. **Better Monitoring**: Centralized user activity tracking
3. **Enhanced Security**: Single point for user security controls
4. **Improved Performance**: Optimized queries and caching

---

## Next Steps

1. **Immediate**: Review and approve this consolidation strategy
2. **Week 1**: Begin Phase 1 implementation (Design & Architecture)
3. **Ongoing**: Regular progress reviews and risk assessment
4. **Completion**: Full validation and documentation update

This consolidation will resolve the critical architectural violation while maintaining system reliability and performance.

# 🔄 System State Diagram - Order & Bidding Lifecycle

```mermaid
stateDiagram-v2
    [*] --> Draft: Customer creates order
    
    state "Order Lifecycle" as OrderStates {
        Draft --> Published: Customer publishes order
        Published --> Bidding: First bid received
        Bidding --> Bidding: More bids received
        Bidding --> Awarded: Customer selects winner
        Bidding --> Expired: Deadline reached
        Awarded --> InProgress: Work begins
        InProgress --> Completed: Work finished
        InProgress --> Disputed: Issue reported
        Disputed --> Resolved: Issue resolved
        Disputed --> Cancelled: Cannot resolve
        Resolved --> Completed: Work continues
        Expired --> Republished: Customer republishes
        Republished --> Published: Order active again
        
        state Published {
            [*] --> WaitingForBids
            WaitingForBids --> HasBids: First bid received
            HasBids --> HasBids: Additional bids
        }
        
        state Bidding {
            [*] --> ActiveBidding
            ActiveBidding --> ActiveBidding: Bid updates
            ActiveBidding --> BiddingClosed: Deadline approaching
            BiddingClosed --> BiddingClosed: Final bids
        }
        
        state InProgress {
            [*] --> WorkStarted
            WorkStarted --> PartOrdered: Merchant orders part
            PartOrdered --> PartReceived: Part arrives
            PartReceived --> WorkInProgress: Installation begins
            WorkInProgress --> WorkCompleted: Work finished
            WorkCompleted --> CustomerReview: Awaiting approval
            CustomerReview --> [*]: Customer approves
        }
    }
    
    state "Bid Lifecycle" as BidStates {
        [*] --> BidDraft: Merchant starts bid
        BidDraft --> BidSubmitted: Merchant submits bid
        BidSubmitted --> BidActive: Bid is live
        BidActive --> BidUpdated: Merchant updates amount
        BidUpdated --> BidActive: Bid remains active
        BidActive --> BidAwarded: Customer selects bid
        BidActive --> BidRejected: Customer selects other bid
        BidActive --> BidWithdrawn: Merchant withdraws
        BidActive --> BidExpired: Order deadline reached
        BidAwarded --> BidCompleted: Work completed successfully
        BidAwarded --> BidDisputed: Issue with work
        BidDisputed --> BidResolved: Issue resolved
        BidDisputed --> BidCancelled: Cannot resolve
        
        state BidActive {
            [*] --> Competing
            Competing --> Leading: Lowest bid
            Leading --> Competing: Outbid by competitor
            Competing --> AutoBidding: Auto-bid enabled
            AutoBidding --> Competing: Auto-bid placed
        }
    }
    
    state "Payment Lifecycle" as PaymentStates {
        [*] --> PaymentPending: Award created
        PaymentPending --> PaymentProcessing: Customer initiates payment
        PaymentProcessing --> PaymentCompleted: Payment successful
        PaymentProcessing --> PaymentFailed: Payment declined
        PaymentFailed --> PaymentPending: Retry payment
        PaymentCompleted --> InvoiceGenerated: ZATCA invoice created
        InvoiceGenerated --> InvoiceSubmitted: Submitted to ZATCA
        InvoiceSubmitted --> InvoiceApproved: ZATCA approval
        InvoiceSubmitted --> InvoiceRejected: ZATCA rejection
        InvoiceRejected --> InvoiceGenerated: Regenerate invoice
        InvoiceApproved --> PaymentFinalized: Process complete
        
        PaymentCompleted --> RefundRequested: Customer requests refund
        RefundRequested --> RefundProcessing: Admin approves refund
        RefundProcessing --> RefundCompleted: Refund successful
        RefundProcessing --> RefundFailed: Refund failed
        RefundFailed --> RefundRequested: Retry refund
    }
    
    state "User Account States" as UserStates {
        [*] --> Unverified: Registration complete
        Unverified --> PhoneVerified: Phone OTP verified
        PhoneVerified --> EmailVerified: Email verified
        EmailVerified --> Active: Account fully verified
        Active --> Suspended: Policy violation
        Suspended --> Active: Suspension lifted
        Active --> Deactivated: User deactivates
        Deactivated --> Active: User reactivates
        
        state Active {
            [*] --> Customer: Customer profile
            [*] --> Merchant: Merchant profile
            Customer --> MerchantApplication: Apply to be merchant
            MerchantApplication --> MerchantPending: Application submitted
            MerchantPending --> Merchant: Application approved
            MerchantPending --> Customer: Application rejected
        }
        
        state Merchant {
            [*] --> MerchantUnverified
            MerchantUnverified --> MerchantVerified: Documents approved
            MerchantVerified --> MerchantSuspended: Policy violation
            MerchantSuspended --> MerchantVerified: Suspension lifted
        }
    }
    
    state "Notification States" as NotificationStates {
        [*] --> NotificationQueued: Event triggered
        NotificationQueued --> NotificationSending: Worker processing
        NotificationSending --> NotificationSent: Delivered successfully
        NotificationSending --> NotificationFailed: Delivery failed
        NotificationFailed --> NotificationRetrying: Retry attempt
        NotificationRetrying --> NotificationSent: Retry successful
        NotificationRetrying --> NotificationAbandoned: Max retries reached
        NotificationSent --> NotificationRead: User reads notification
        NotificationSent --> NotificationExpired: TTL expired
    }
```

## 🔄 State Transition Rules

### **Order State Transitions**

| From State | To State | Trigger | Conditions |
|------------|----------|---------|------------|
| Draft | Published | Customer action | Order details complete |
| Published | Bidding | System | First bid received |
| Bidding | Awarded | Customer action | Customer selects bid |
| Bidding | Expired | System | Deadline reached |
| Awarded | InProgress | Merchant action | Merchant accepts award |
| InProgress | Completed | Customer action | Customer approves work |
| InProgress | Disputed | Either party | Issue reported |
| Disputed | Resolved | Admin action | Issue mediated |
| Expired | Republished | Customer action | Customer republishes |

### **Bid State Transitions**

| From State | To State | Trigger | Conditions |
|------------|----------|---------|------------|
| Draft | Submitted | Merchant action | Bid details complete |
| Submitted | Active | System | Validation passed |
| Active | Updated | Merchant action | New amount provided |
| Active | Awarded | Customer action | Customer selects bid |
| Active | Rejected | Customer action | Customer selects other bid |
| Active | Withdrawn | Merchant action | Merchant withdraws |
| Active | Expired | System | Order deadline reached |
| Awarded | Completed | System | Order completed |
| Awarded | Disputed | Either party | Issue with work |

### **Payment State Transitions**

| From State | To State | Trigger | Conditions |
|------------|----------|---------|------------|
| Pending | Processing | Customer action | Payment initiated |
| Processing | Completed | Gateway | Payment successful |
| Processing | Failed | Gateway | Payment declined |
| Completed | InvoiceGenerated | System | ZATCA invoice created |
| InvoiceGenerated | InvoiceSubmitted | System | Submitted to ZATCA |
| InvoiceSubmitted | InvoiceApproved | ZATCA | ZATCA validates invoice |
| Completed | RefundRequested | Customer action | Refund requested |
| RefundRequested | RefundProcessing | Admin action | Refund approved |

## 🎯 Business Rules

### **Order Management Rules**
- Orders can only be published if all required fields are complete
- Orders automatically expire after the specified deadline
- Customers can republish expired orders with updated details
- Only verified customers can create orders

### **Bidding Rules**
- Only verified merchants can submit bids
- Bids must meet minimum amount requirements
- Merchants cannot bid on their own orders
- Auto-bidding has maximum limits to prevent runaway bidding
- Bids can be withdrawn before award (with time restrictions)

### **Payment Rules**
- Payment is required within 24 hours of award
- ZATCA invoices are mandatory for Saudi customers
- Refunds require admin approval
- Disputed payments are held in escrow

### **User Account Rules**
- Phone verification is mandatory for all users
- Email verification is required for password reset
- Merchants must provide business documentation
- Suspended users cannot create orders or bids

## 📊 State Monitoring

### **Real-time Dashboards**
- **Order Status Distribution**: Live count of orders in each state
- **Bid Activity**: Active bidding sessions and competition levels
- **Payment Processing**: Transaction success rates and processing times
- **User Activity**: Registration, verification, and engagement metrics

### **Automated Alerts**
- **Stuck Orders**: Orders in same state too long
- **Failed Payments**: Payment processing issues
- **High Dispute Rate**: Quality issues requiring attention
- **System Bottlenecks**: Performance degradation alerts

This state management system ensures proper workflow control, business rule enforcement, and comprehensive monitoring across the entire Reverse Tender Platform.


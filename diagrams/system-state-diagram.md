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
```mermaid

%%{init: {
  'theme': 'base',
  'themeVariables': {
    'primaryColor': '#1e293b',
    'primaryTextColor': '#f8fafc',
    'primaryBorderColor': '#38bdf8',
    'lineColor': '#94a3b8',
    'secondaryColor': '#0f172a',
    'tertiaryColor': '#1e293b',
    'mainBkg': '#0f172a',
    'nodeBorder': '#38bdf8',
    'clusterBkg': '#1e293b',
    'clusterBorder': '#7dd3fc',
    'titleColor': '#f1f5f9',
    'edgeLabelBackground':'#1e293b',
    'nodeTextColor': '#f8fafc'
  }
}}%%

stateDiagram-v2
    direction TB
    [*] --> Draft: 📦 Create Order
    
    state "📦 Order Lifecycle" as OrderStates {
        Draft --> Published: 🌐 Publish
        Published --> Bidding: 📥 First bid
        Bidding --> Bidding: 📈 New bids
        Bidding --> Awarded: 🏆 Select winner
        Bidding --> Expired: ⏰ Deadline
        Awarded --> InProgress: 🛠️ Start
        InProgress --> Completed: ✅ Finish
        InProgress --> Disputed: ⚠️ Dispute
        Disputed --> Resolved: 🤝 Resolve
        Disputed --> Cancelled: 🚫 Cancel
        Resolved --> Completed: 🏗️ Resume
        Expired --> Republished: 🔄 Republish
        Republished --> Published: ✨ Active
        
        state "🌐 Published" as SubPublished {
            [*] --> WaitingForBids
            WaitingForBids --> HasBids: 📥 Bid in
        }
        
        state "📈 Bidding" as SubBidding {
            [*] --> ActiveBidding
            ActiveBidding --> BiddingClosed: 🔒 Closing
        }
        
        state "🛠️ InProgress" as SubInProgress {
            [*] --> WorkStarted
            WorkStarted --> PartOrdered: ⚙️ Parts
            PartOrdered --> PartReceived: 🚚 Delivery
            PartReceived --> WorkInProgress: 🔧 Install
            WorkInProgress --> WorkCompleted: 🏁 Done
            WorkCompleted --> CustomerReview: 👀 Review
        }
    }
    
    state "🤝 Bid Lifecycle" as BidStates {
        [*] --> BidDraft: ✍️ Draft
        BidDraft --> BidSubmitted: 📤 Submit
        BidSubmitted --> BidActive: ⚡ Live
        BidActive --> BidUpdated: ✏️ Edit
        BidActive --> BidAwarded: 🎯 Won
        BidActive --> BidRejected: ❌ Lost
        BidActive --> BidWithdrawn: 🔙 Retract
        BidActive --> BidExpired: ⏳ Close
        
        state "⚡ BidActive" as SubBidActive {
            [*] --> Competing
            Competing --> Leading: 🥇 Top
            Leading --> Competing: 📉 Outbid
        }
    }
    
    state "💰 Payment Lifecycle" as PaymentStates {
        [*] --> PaymentPending: ⏳ Awarded
        PaymentPending --> PaymentProcessing: 💳 Pay
        PaymentProcessing --> PaymentCompleted: 💎 Success
        PaymentProcessing --> PaymentFailed: 🛑 Fail
        PaymentCompleted --> InvoiceGenerated: 📝 ZATCA Calc
        InvoiceGenerated --> InvoiceSubmitted: 📡 ZATCA Sync
        InvoiceSubmitted --> InvoiceApproved: ✅ Approved
        InvoiceRejected --> InvoiceGenerated: 🔄 Regen
        
        PaymentCompleted --> RefundRequested: ↩️ Refund
        RefundProcessing --> RefundCompleted: 💸 Done
    }
    
    state "👤 User Account" as UserStates {
        [*] --> Unverified: 🆕 Reg
        Unverified --> PhoneVerified: 📱 OTP
        PhoneVerified --> EmailVerified: 📧 Mail
        EmailVerified --> Active: ✅ Verified
        
        state "✅ Active" as SubUserActive {
            [*] --> Customer
            Customer --> MerchantApplication: 📝 Apply
            MerchantPending --> Merchant: 🎖️ OK
        }
    }
```
```mermaid


%%{init: {
  'theme': 'base',
  'themeVariables': {
    'primaryColor': '#1a1a2e',
    'primaryTextColor': '#ffffff',
    'primaryBorderColor': '#00d1ff',
    'lineColor': '#7f8c8d',
    'secondaryColor': '#0f3460',
    'tertiaryColor': '#16213e',
    'mainBkg': '#0a0a12',
    'nodeBorder': '#00d1ff',
    'clusterBkg': '#16213e',
    'clusterBorder': '#4ecdc4',
    'titleColor': '#f7d794',
    'edgeLabelBackground':'#1a1a2e'
  }
}}%%

stateDiagram-v2
    direction LR

    %% --- GLOBAL VIEW: THE FIVE PILLARS --- %%

    state "📦 ORDER MANAGEMENT" as OrderSystem {
        direction TB
        [*] --> Draft
        Draft --> Published
        Published --> Bidding
        Bidding --> Awarded
        Awarded --> InProgress
        InProgress --> Completed
        Completed --> [*]
        Bidding --> Expired
        Expired --> Republished
        Republished --> Bidding

        state InProgress {
            [*] --> WorkStarted
            WorkStarted --> PartsHandling
            PartsHandling --> Testing
            Testing --> [*]
        }
    }

    state "🤝 BIDDING ENGINE" as BidSystem {
        direction TB
        [*] --> BidDraft
        BidDraft --> BidSubmitted
        BidSubmitted --> BidActive
        BidActive --> BidWon
        BidActive --> BidLost
        BidWon --> [*]
        BidLost --> [*]

        state BidActive {
            [*] --> Competing
            Competing --> Leading
            Leading --> Outbid
            Outbid --> Competing
        }
    }

    state "💰 FINANCIAL LEDGER" as PaymentSystem {
        direction TB
        [*] --> EscrowPending
        EscrowPending --> EscrowHeld
        EscrowHeld --> PayoutReleased
        PayoutReleased --> [*]
        EscrowHeld --> Refunded
        Refunded --> [*]

        state ZATCA_Flow {
            [*] --> Calc
            Calc --> Sync
            Sync --> Approved
            Approved --> [*]
        }
        EscrowHeld --> ZATCA_Flow
        ZATCA_Flow --> PayoutReleased
    }

    state "👤 IDENTITY & ACCESS" as UserSystem {
        direction TB
        [*] --> Guest
        Guest --> VerifiedUser
        VerifiedUser --> Suspended
        Suspended --> [*]

        state VerifiedUser {
            [*] --> CustomerRole
            [*] --> MerchantRole
            CustomerRole --> [*]
            MerchantRole --> [*]
        }
    }

    state "🔔 EVENT NOTIFICATIONS" as NotifySystem {
        direction TB
        [*] --> Triggered
        Triggered --> Queued
        Queued --> Dispatched
        Dispatched --> Delivered
        Delivered --> [*]
    }

    %% --- INTER-SYSTEM CONNECTIONS (The "Zoom Out" Links) --- %%
    OrderSystem --> BidSystem : "Triggers Bidding"
    BidSystem --> PaymentSystem : "Winning Bid creates Escrow"
    PaymentSystem --> OrderSystem : "Payment unlocks Work"
    UserSystem --> OrderSystem : "Owner of Record"
    OrderSystem --> NotifySystem : "Status Updates"
    PaymentSystem --> NotifySystem : "Transaction Alerts"
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


# 🎯 Bidding System Flow Diagram

```mermaid
sequenceDiagram
    participant Customer as 👤 Customer
    participant PWA as 📱 PWA Client
    participant Gateway as 🚪 API Gateway
    participant Order as 📋 Order Service
    participant Bidding as 🎯 Bidding Service
    participant Notification as 📢 Notification Service
    participant Merchant as 🏪 Merchant
    participant WebSocket as 🔄 WebSocket Server
    participant Redis as ⚡ Redis Queue
    participant DB as 🗃️ Database
    
    Note over Customer,DB: Order Creation & Publishing
    
    Customer->>PWA: Create part request
    PWA->>Gateway: POST /api/orders
    Gateway->>Order: Create order
    Order->>DB: Save order (status: draft)
    Order->>Gateway: Order created
    Gateway->>PWA: Order ID + details
    PWA->>Customer: Order created successfully
    
    Customer->>PWA: Publish order for bidding
    PWA->>Gateway: PUT /api/orders/{id}/publish
    Gateway->>Order: Publish order
    Order->>DB: Update status to 'published'
    Order->>Redis: Publish order_published event
    Order->>Gateway: Order published
    Gateway->>PWA: Order is now live
    
    Note over Customer,DB: Merchant Notification & Bid Submission
    
    Redis->>Notification: order_published event
    Notification->>DB: Get relevant merchants
    Notification->>Notification: Filter by specialization & location
    
    loop For each relevant merchant
        Notification->>Merchant: Push notification
        Notification->>Merchant: SMS notification (if enabled)
        Notification->>Merchant: Email notification (if enabled)
    end
    
    Merchant->>Gateway: GET /api/orders/{id}
    Gateway->>Order: Get order details
    Order->>DB: Fetch order data
    Order->>Gateway: Order details
    Gateway->>Merchant: Order information
    
    Merchant->>Gateway: POST /api/bids
    Gateway->>Bidding: Create bid
    Bidding->>DB: Validate bid constraints
    
    alt Invalid bid (too low, expired order, etc.)
        Bidding->>Gateway: 400 Bad Request
        Gateway->>Merchant: Bid validation failed
    else Valid bid
        Bidding->>DB: Save bid
        Bidding->>Redis: Publish bid_created event
        Bidding->>WebSocket: Broadcast new bid
        Bidding->>Gateway: Bid created
        Gateway->>Merchant: Bid submitted successfully
    end
    
    Note over Customer,DB: Real-time Bid Updates
    
    Redis->>Notification: bid_created event
    Notification->>Customer: Push notification (new bid)
    
    WebSocket->>PWA: Real-time bid update
    PWA->>Customer: Show new bid in real-time
    
    Note over Customer,DB: Competitive Bidding
    
    loop Multiple merchants bidding
        Merchant->>Gateway: PUT /api/bids/{id}
        Gateway->>Bidding: Update bid amount
        Bidding->>DB: Validate new amount
        
        alt Amount too low or invalid
            Bidding->>Gateway: 400 Bad Request
            Gateway->>Merchant: Invalid bid amount
        else Valid amount
            Bidding->>DB: Update bid amount
            Bidding->>DB: Log bid history
            Bidding->>Redis: Publish bid_updated event
            Bidding->>WebSocket: Broadcast bid update
            Bidding->>Gateway: Bid updated
            Gateway->>Merchant: Bid updated successfully
            
            WebSocket->>PWA: Real-time bid update
            PWA->>Customer: Show updated bid
            
            Redis->>Notification: bid_updated event
            Notification->>Customer: Push notification (bid updated)
        end
    end
    
    Note over Customer,DB: Auto-bidding System
    
    Merchant->>Gateway: POST /api/bids/auto
    Gateway->>Bidding: Enable auto-bidding
    Bidding->>DB: Save auto-bid settings
    Bidding->>Gateway: Auto-bidding enabled
    Gateway->>Merchant: Auto-bidding active
    
    loop When new competing bid arrives
        Bidding->>Bidding: Check auto-bid rules
        Bidding->>DB: Get auto-bid settings
        
        alt Auto-bid conditions met
            Bidding->>DB: Create automatic counter-bid
            Bidding->>DB: Log auto-bid action
            Bidding->>Redis: Publish auto_bid_created event
            Bidding->>WebSocket: Broadcast auto-bid
            Bidding->>Notification: Notify merchant of auto-bid
        end
    end
    
    Note over Customer,DB: Bid Award Process
    
    Customer->>PWA: Select winning bid
    PWA->>Gateway: POST /api/orders/{id}/award
    Gateway->>Bidding: Award bid
    Bidding->>DB: Validate award conditions
    
    alt Invalid award (expired, already awarded, etc.)
        Bidding->>Gateway: 400 Bad Request
        Gateway->>PWA: Award failed
        PWA->>Customer: Cannot award this bid
    else Valid award
        Bidding->>DB: Create award record
        Bidding->>DB: Update order status to 'awarded'
        Bidding->>DB: Update winning bid status
        Bidding->>DB: Update losing bids status to 'rejected'
        Bidding->>Redis: Publish bid_awarded event
        Bidding->>Gateway: Award successful
        Gateway->>PWA: Bid awarded
        PWA->>Customer: Congratulations! Bid awarded
        
        Redis->>Notification: bid_awarded event
        Notification->>Merchant: Push notification (you won!)
        Notification->>Merchant: SMS notification (award details)
        Notification->>Merchant: Email notification (contract details)
        
        loop For each losing merchant
            Notification->>Merchant: Push notification (bid not selected)
        end
    end
    
    Note over Customer,DB: Bid Communication
    
    Merchant->>Gateway: POST /api/bids/{id}/messages
    Gateway->>Bidding: Send bid message
    Bidding->>DB: Save message
    Bidding->>Redis: Publish bid_message event
    Bidding->>WebSocket: Broadcast message
    Bidding->>Gateway: Message sent
    Gateway->>Merchant: Message delivered
    
    WebSocket->>PWA: Real-time message
    PWA->>Customer: Show merchant message
    
    Customer->>PWA: Reply to merchant
    PWA->>Gateway: POST /api/bids/{id}/messages
    Gateway->>Bidding: Send customer reply
    Bidding->>DB: Save reply
    Bidding->>Redis: Publish customer_message event
    Bidding->>WebSocket: Broadcast reply
    
    WebSocket->>Merchant: Real-time customer reply
    
    Note over Customer,DB: Bid Withdrawal
    
    Merchant->>Gateway: DELETE /api/bids/{id}
    Gateway->>Bidding: Withdraw bid
    Bidding->>DB: Check if bid can be withdrawn
    
    alt Cannot withdraw (already awarded, too late, etc.)
        Bidding->>Gateway: 400 Bad Request
        Gateway->>Merchant: Cannot withdraw bid
    else Can withdraw
        Bidding->>DB: Update bid status to 'withdrawn'
        Bidding->>Redis: Publish bid_withdrawn event
        Bidding->>WebSocket: Broadcast withdrawal
        Bidding->>Gateway: Bid withdrawn
        Gateway->>Merchant: Bid withdrawn successfully
        
        WebSocket->>PWA: Real-time bid withdrawal
        PWA->>Customer: Merchant withdrew bid
        
        Redis->>Notification: bid_withdrawn event
        Notification->>Customer: Push notification (bid withdrawn)
    end
    
    Note over Customer,DB: Order Expiration
    
    Bidding->>Bidding: Check order deadlines (cron job)
    Bidding->>DB: Get orders near deadline
    
    loop For each expiring order
        Bidding->>DB: Update order status to 'expired'
        Bidding->>DB: Update all bids to 'expired'
        Bidding->>Redis: Publish order_expired event
        
        Redis->>Notification: order_expired event
        Notification->>Customer: Push notification (order expired)
        
        loop For each bidding merchant
            Notification->>Merchant: Push notification (order expired)
        end
    end
```

## 🎯 Bidding System Features

### **1. Real-time Bidding**
- **WebSocket Integration**: Live bid updates without page refresh
- **Instant Notifications**: Push notifications for all bid events
- **Live Chat**: Direct communication between customers and merchants
- **Auto-refresh**: Automatic bid list updates

### **2. Smart Bidding Rules**
- **Minimum Bid**: Configurable minimum bid amounts
- **Bid Increments**: Minimum increment requirements
- **Time Extensions**: Automatic deadline extensions for last-minute bids
- **Maximum Bids**: Customer budget constraints

### **3. Auto-bidding System**
- **Merchant Auto-bids**: Automatic counter-bidding within limits
- **Bid Strategies**: Conservative, aggressive, or custom strategies
- **Maximum Limits**: Auto-bid ceiling amounts
- **Smart Logic**: Intelligent bidding based on competition

### **4. Bid Validation**
- **Merchant Verification**: Only verified merchants can bid
- **Specialization Check**: Merchants must have relevant specializations
- **Location Validation**: Service area coverage verification
- **Capacity Check**: Merchant availability and capacity

### **5. Award Management**
- **Winner Selection**: Customer chooses winning bid
- **Automatic Awards**: Lowest bid auto-award (optional)
- **Contract Generation**: Automatic contract creation
- **Escrow Integration**: Payment hold until completion

## 📊 Bidding Analytics

### **Real-time Metrics**
- **Active Bids**: Current bidding activity
- **Average Response Time**: Merchant response speed
- **Bid Competition**: Number of bids per order
- **Success Rates**: Merchant win rates

### **Business Intelligence**
- **Pricing Trends**: Market price analysis
- **Merchant Performance**: Success rates and ratings
- **Customer Behavior**: Bidding patterns and preferences
- **Market Insights**: Supply and demand analytics

## 🔄 Event-Driven Architecture

### **Key Events**
- `order_published`: New order available for bidding
- `bid_created`: New bid submitted
- `bid_updated`: Bid amount or details changed
- `bid_awarded`: Winning bid selected
- `bid_withdrawn`: Merchant withdraws bid
- `order_expired`: Order deadline reached
- `auto_bid_triggered`: Automatic bid placed

### **Event Handlers**
- **Notification Service**: Sends notifications for all events
- **Analytics Service**: Tracks metrics and generates insights
- **WebSocket Server**: Broadcasts real-time updates
- **Audit Service**: Logs all bidding activities

This bidding system provides a competitive, transparent, and efficient marketplace for automotive parts with real-time capabilities and comprehensive business intelligence.


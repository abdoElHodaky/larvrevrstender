# 🎯 Bidding System Flow Diagram
```mermaid
   graph TB
    subgraph Clients
        PWA[📱 PWA / Mobile App]
    end

    subgraph API_Layer [Edge Layer]
        GW[🚪 API Gateway]
    end

    subgraph Core_Services [Logic Layer]
        Order[📋 Order Service]
        Bidding[🎯 Bidding Service]
        Notif[📢 Notification Service]
    end

    subgraph Data_Messaging [Infrastructure Layer]
        Redis[⚡ Redis Event Bus]
        WS[🔄 WebSocket Server]
        DB[(🗃️ Primary DB)]
    end

    subgraph External [External Integrations]
        SMS[📲 SMS Provider]
        Email[📧 Email Provider]
    end

    %% Relationships
    PWA -->|HTTPS| GW
    GW --> Order
    GW --> Bidding
    
    Order --> DB
    Order -.->|Publish Event| Redis
    
    Bidding --> DB
    Bidding -.->|Publish Event| Redis
    Bidding --> WS
    
    Redis -.->|Subscribe| Notif
    Notif --> DB
    Notif --> SMS
    Notif --> Email
    
    WS -.->|Push Update| PWA
```
```mermaid
%%{init: {
  'theme': 'base',
  'themeVariables': {
    'primaryColor': '#00E5FF',
    'primaryTextColor': '#FFFFFF',
    'primaryBorderColor': '#00E5FF',
    'lineColor': '#00E5FF',
    'secondaryColor': '#FF00FF',
    'tertiaryColor': '#121212',
    'actorTextColor': '#FFFFFF',
    'actorBkg': '#1A1A1A',
    'actorBorder': '#00E5FF',
    'noteTextColor': '#FFFFFF',
    'noteBkgColor': '#2D2D2D',
    'signalColor': '#FFFFFF',
    'signalTextColor': '#FFFFFF',
    'labelTextColor': '#FFFFFF',
    'loopTextColor': '#00E5FF',
    'sequenceNumberColor': '#000000'
  }
}}%%
sequenceDiagram
    autonumber
    
    box "User Experience" #121212
        participant C as 👤 Customer
        participant P as 📱 PWA Client
    end
    
    box "Edge & Real-time" #001F3F
        participant G as 🚪 Gateway
        participant WS as 🔄 WebSocket
    end
    
    box "Core Services" #1F003F
        participant O as 📋 Order SVC
        participant B as 🎯 Bidding SVC
        participant N as 📢 Notify SVC
    end
    
    box "Data Plane" #002B16
        participant R as ⚡ Redis
        participant DB as 🗃️ DB
    end

    Note over C, DB: 🟢 INITIALIZATION & PUBLISHING
    rect rgb(30, 30, 30)
        C->>P: Create & Publish
        P->>G: POST/PUT /api/orders
        G->>O: Process Order
        O->>DB: Status: Published
        O->>R: Event: order_published
        R->>N: Trigger Logic
        N-->>C: Push: "Live"
    end

    Note over C, DB: 🟠 COMPETITIVE BIDDING & MESSAGING
    rect rgb(45, 35, 10)
        loop Merchant Interaction
            participant M as 🏪 Merchant
            M->>G: POST /bids
            B->>DB: Validate & Save
            B->>R: bid_created
            B->>WS: Broadcast
            WS-->>P: Update UI
            M->>G: POST /messages
            B->>WS: Push Message
            WS-->>P: "New Message"
        end
    end

    Note over C, DB: ⚡ AUTO-BIDDING ENGINE (BG PROCESS)
    rect rgb(40, 0, 60)
        B->>B: Check Auto-rules
        B->>DB: Execute Counter-bid
        B->>R: auto_bid_created
        B->>WS: Update Market Price
        B->>N: Alert Merchant
    end

    Note over C, DB: 🏆 AWARD & FINALIZATION
    rect rgb(10, 40, 20)
        C->>P: Select Winner
        P->>G: POST /award
        B->>DB: Atomic State Change
        B->>R: bid_awarded
        N-->>M: SMS: "You Won!"
        N-->>M: SMS: "Not Selected" (Losing Bids)
    end

    Note over C, DB: 🔴 CLEANUP
    rect rgb(50, 10, 10)
        B->>DB: Scan Cron
        B->>R: order_expired
        N-->>C: Push: "Expired"
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


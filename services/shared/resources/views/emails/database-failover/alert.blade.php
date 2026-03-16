<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Failover Alert</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, {{ $severityColor }}, {{ $severityColor }}dd);
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        .content {
            background: #f9f9f9;
            padding: 20px;
            border: 1px solid #ddd;
            border-top: none;
        }
        .alert-details {
            background: white;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid {{ $severityColor }};
        }
        .action-items {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }
        .action-items h3 {
            color: #856404;
            margin-top: 0;
        }
        .action-items ol {
            margin: 10px 0;
            padding-left: 20px;
        }
        .action-items li {
            margin: 5px 0;
            color: #856404;
        }
        .footer {
            background: #333;
            color: white;
            padding: 15px;
            text-align: center;
            border-radius: 0 0 8px 8px;
            font-size: 12px;
        }
        .severity-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            background: {{ $severityColor }};
            color: white;
        }
        .context-info {
            background: #e9ecef;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 12px;
        }
        .timestamp {
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $severityIcon }} Database Failover Alert</h1>
        <div class="severity-badge">{{ strtoupper($severity) }}</div>
        <div class="timestamp">{{ $timestamp }}</div>
    </div>

    <div class="content">
        <div class="alert-details">
            <h2>{{ $alertDetails['title'] }}</h2>
            <p><strong>Description:</strong> {{ $alertDetails['description'] }}</p>
            
            @if(isset($alertDetails['affected_connections']))
                <p><strong>Affected Connections:</strong> 
                    @foreach($alertDetails['affected_connections'] as $connection)
                        <code>{{ $connection }}</code>@if(!$loop->last), @endif
                    @endforeach
                </p>
            @endif

            @if(isset($alertDetails['affected_service']))
                <p><strong>Affected Service:</strong> <code>{{ $alertDetails['affected_service'] }}</code></p>
            @endif

            @if(isset($alertDetails['failed_connection']))
                <p><strong>Failed Connection:</strong> <code>{{ $alertDetails['failed_connection'] }}</code></p>
            @endif

            @if(isset($alertDetails['connection']))
                <p><strong>Connection:</strong> <code>{{ $alertDetails['connection'] }}</code></p>
            @endif

            <p><strong>Risk Level:</strong> <span style="color: {{ $severityColor }}; font-weight: bold;">{{ $alertDetails['risk_level'] }}</span></p>
            <p><strong>Impact:</strong> {{ $alertDetails['impact'] }}</p>

            @if(isset($alertDetails['error_details']))
                <p><strong>Error Details:</strong></p>
                <div class="context-info">{{ $alertDetails['error_details'] }}</div>
            @endif

            @if(isset($alertDetails['inconsistencies']) && !empty($alertDetails['inconsistencies']))
                <p><strong>Inconsistencies Detected:</strong></p>
                <ul>
                    @foreach($alertDetails['inconsistencies'] as $inconsistency)
                        <li>{{ $inconsistency }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="action-items">
            <h3>🚨 Immediate Action Required</h3>
            <ol>
                @foreach($actionItems as $action)
                    <li>{{ $action }}</li>
                @endforeach
            </ol>
        </div>

        <div class="context-info">
            <strong>Event Context:</strong><br>
            <strong>Service:</strong> {{ $serviceName }}<br>
            <strong>Environment:</strong> {{ $environment }}<br>
            <strong>Event Type:</strong> {{ $eventType }}<br>
            <strong>Notification ID:</strong> {{ $notificationId }}<br>
            <strong>Timestamp:</strong> {{ $timestamp }}
        </div>
    </div>

    <div class="footer">
        <p>This is an automated alert from the Database Failover System.</p>
        <p>For support, please contact the operations team immediately.</p>
        <p>Alert ID: {{ $notificationId }}</p>
    </div>
</body>
</html>

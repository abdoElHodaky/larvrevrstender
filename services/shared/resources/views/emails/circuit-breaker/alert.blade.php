<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Circuit Breaker Alert</title>
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
        .circuit-details {
            background: white;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid {{ $severityColor }};
        }
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .metric-card {
            background: white;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #ddd;
            text-align: center;
        }
        .metric-value {
            font-size: 24px;
            font-weight: bold;
            color: {{ $severityColor }};
        }
        .metric-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        .health-status {
            padding: 10px;
            border-radius: 5px;
            margin: 15px 0;
            text-align: center;
            font-weight: bold;
        }
        .health-healthy { background: #d4edda; color: #155724; }
        .health-degraded { background: #fff3cd; color: #856404; }
        .health-recovering { background: #cce7ff; color: #004085; }
        .health-unhealthy { background: #f8d7da; color: #721c24; }
        .health-unknown { background: #e2e3e5; color: #383d41; }
        .recommendations {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }
        .recommendations h3 {
            color: #004085;
            margin-top: 0;
        }
        .recommendations ol {
            margin: 10px 0;
            padding-left: 20px;
        }
        .recommendations li {
            margin: 5px 0;
            color: #004085;
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
        <h1>{{ $severityIcon }} Circuit Breaker Alert</h1>
        <div class="severity-badge">{{ strtoupper($severity) }}</div>
        <div class="timestamp">{{ $timestamp }}</div>
    </div>

    <div class="content">
        <div class="circuit-details">
            <h2>{{ $circuitDetails['title'] }}</h2>
            <p><strong>Description:</strong> {{ $circuitDetails['description'] }}</p>
            
            <p><strong>Circuit Name:</strong> <code>{{ $circuitDetails['circuit_name'] }}</code></p>
            <p><strong>Connection:</strong> <code>{{ $circuitDetails['connection'] }}</code></p>
            <p><strong>Service:</strong> <code>{{ $circuitDetails['service'] }}</code></p>
            
            @if(isset($circuitDetails['failure_count']))
                <p><strong>Failure Count:</strong> {{ $circuitDetails['failure_count'] }}</p>
            @endif
            
            <p><strong>Circuit State:</strong> <code>{{ strtoupper($circuitDetails['circuit_state']) }}</code></p>
            <p><strong>Risk Level:</strong> <span style="color: {{ $severityColor }}; font-weight: bold;">{{ $circuitDetails['risk_level'] }}</span></p>
            <p><strong>Impact:</strong> {{ $circuitDetails['impact'] }}</p>
        </div>

        <div class="health-status health-{{ $circuitHealthStatus['health'] }}">
            <strong>Circuit Health:</strong> {{ strtoupper($circuitHealthStatus['health']) }}<br>
            <small>{{ $circuitHealthStatus['status'] }}</small>
        </div>

        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-value">{{ $performanceMetrics['failure_count'] }}</div>
                <div class="metric-label">Failures</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ $performanceMetrics['success_count'] }}</div>
                <div class="metric-label">Successes</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ $performanceMetrics['failure_rate'] }}</div>
                <div class="metric-label">Failure Rate</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ $performanceMetrics['average_response_time'] }}ms</div>
                <div class="metric-label">Avg Response</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ $performanceMetrics['circuit_open_duration'] }}</div>
                <div class="metric-label">Open Duration</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ $performanceMetrics['recovery_attempts'] }}</div>
                <div class="metric-label">Recovery Attempts</div>
            </div>
        </div>

        @if($performanceMetrics['last_failure_time'] !== 'N/A')
            <div class="context-info">
                <strong>Last Failure:</strong> {{ $performanceMetrics['last_failure_time'] }}
            </div>
        @endif

        <div class="recommendations">
            <h3>🔧 Recommended Actions</h3>
            <ol>
                @foreach($recommendations as $recommendation)
                    <li>{{ $recommendation }}</li>
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
        <p>This is an automated alert from the Circuit Breaker System.</p>
        <p>Monitor the circuit status and take appropriate action if needed.</p>
        <p>Alert ID: {{ $notificationId }}</p>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pipedrive Connection Status</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .status-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }
        .status-connected { background: #28a745; }
        .status-disconnected { background: #dc3545; }
        .status-error { background: #ffc107; color: #333; }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            font-size: 16px;
        }
        .status-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .status-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .status-row:last-child {
            border-bottom: none;
        }
        .status-label {
            font-weight: 600;
            color: #555;
        }
        .status-value {
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        .indicator-success { background: #28a745; }
        .indicator-error { background: #dc3545; }
        .indicator-warning { background: #ffc107; }
        
        .connection-test {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .connection-test.error {
            background: #f8d7da;
            border-color: #f5c6cb;
        }
        .connection-test h3 {
            margin: 0 0 15px 0;
            color: #004085;
        }
        .connection-test.error h3 {
            color: #721c24;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            margin: 5px;
        }
        .btn:hover {
            background: #218838;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        .btn-warning:hover {
            background: #e0a800;
        }
        .actions {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        .code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="status-icon {{ $isAuthenticated && $connectionTest && $connectionTest['success'] ? 'status-connected' : ($isConfigured ? 'status-disconnected' : 'status-error') }}">
                @if($isAuthenticated && $connectionTest && $connectionTest['success'])
                    ✓
                @elseif($isConfigured)
                    ⚠️
                @else
                    ❌
                @endif
            </div>
            <h1>Pipedrive Connection Status</h1>
            <p class="subtitle">Current authentication and connection status</p>
        </div>

        <div class="status-card">
            <div class="status-row">
                <span class="status-label">Authentication Method</span>
                <span class="status-value">
                    <span class="status-indicator {{ $authMethod === 'oauth' ? 'indicator-success' : 'indicator-warning' }}"></span>
                    {{ ucfirst($authMethod) }}
                </span>
            </div>
            
            <div class="status-row">
                <span class="status-label">OAuth Configuration</span>
                <span class="status-value">
                    <span class="status-indicator {{ $isConfigured ? 'indicator-success' : 'indicator-error' }}"></span>
                    {{ $isConfigured ? 'Configured' : 'Not Configured' }}
                </span>
            </div>
            
            <div class="status-row">
                <span class="status-label">Authentication Status</span>
                <span class="status-value">
                    <span class="status-indicator {{ $isAuthenticated ? 'indicator-success' : 'indicator-error' }}"></span>
                    {{ $isAuthenticated ? 'Authenticated' : 'Not Authenticated' }}
                </span>
            </div>
            
            @if($connectionTest)
            <div class="status-row">
                <span class="status-label">API Connection</span>
                <span class="status-value">
                    <span class="status-indicator {{ $connectionTest['success'] ? 'indicator-success' : 'indicator-error' }}"></span>
                    {{ $connectionTest['success'] ? 'Connected' : 'Failed' }}
                </span>
            </div>
            @endif
        </div>

        @if($connectionTest)
        <div class="connection-test {{ $connectionTest['success'] ? '' : 'error' }}">
            <h3>{{ $connectionTest['success'] ? '✅ Connection Test Results' : '❌ Connection Test Failed' }}</h3>
            <p><strong>Message:</strong> {{ $connectionTest['message'] }}</p>
            @if(isset($connectionTest['user']))
                <p><strong>User:</strong> {{ $connectionTest['user'] }}</p>
            @endif
            @if(isset($connectionTest['company']))
                <p><strong>Company:</strong> {{ $connectionTest['company'] }}</p>
            @endif
            @if(isset($connectionTest['error']) && !$connectionTest['success'])
                <details style="margin-top: 15px;">
                    <summary style="cursor: pointer; color: #721c24; font-weight: 600;">Show Error Details</summary>
                    <pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin-top: 10px; font-size: 12px; overflow-x: auto;">{{ $connectionTest['error'] }}</pre>
                </details>
            @endif
        </div>
        @endif

        @if(!$isConfigured)
        <div class="connection-test error">
            <h3>⚙️ Configuration Required</h3>
            <p>OAuth is not properly configured. Please set the following environment variables:</p>
            <ul>
                <li><span class="code">PIPEDRIVE_AUTH_METHOD=oauth</span></li>
                <li><span class="code">PIPEDRIVE_CLIENT_ID=your_client_id</span></li>
                <li><span class="code">PIPEDRIVE_CLIENT_SECRET=your_client_secret</span></li>
                <li><span class="code">PIPEDRIVE_REDIRECT_URL=https://your-domain.com/pipedrive/oauth/callback</span></li>
            </ul>
            <p>After updating your configuration, run <span class="code">php artisan config:clear</span></p>
        </div>
        @endif

        <div class="actions">
            @if($isConfigured && !$isAuthenticated)
                <a href="{{ route('pipedrive.oauth.authorize') }}" class="btn">
                    🔗 Connect to Pipedrive
                </a>
            @elseif($isAuthenticated)
                <a href="{{ route('pipedrive.oauth.authorize') }}" class="btn btn-warning">
                    🔄 Reconnect
                </a>
                <a href="{{ route('pipedrive.oauth.disconnect') }}" class="btn btn-danger">
                    🔌 Disconnect
                </a>
            @endif
            
            <a href="javascript:location.reload()" class="btn btn-secondary">
                🔄 Refresh Status
            </a>
            
            <a href="{{ url('/') }}" class="btn btn-secondary">
                🏠 Dashboard
            </a>
        </div>

        <div style="margin-top: 30px; text-align: center; font-size: 12px; color: #999;">
            Last checked: {{ now()->format('Y-m-d H:i:s') }}
        </div>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pipedrive Connection {{ ucfirst($action) }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background: #28a745;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 40px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .message {
            color: #666;
            margin-bottom: 30px;
            font-size: 18px;
            line-height: 1.5;
        }
        .details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        .details h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 16px;
        }
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .detail-item:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #555;
        }
        .detail-value {
            color: #333;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            margin: 10px;
        }
        .btn:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }
        .actions {
            margin-top: 30px;
        }
        .next-steps {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        .next-steps h3 {
            margin: 0 0 15px 0;
            color: #004085;
        }
        .next-steps ul {
            margin: 0;
            padding-left: 20px;
            color: #004085;
        }
        .next-steps li {
            margin: 8px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">
            @if($action === 'connected')
                ✓
            @elseif($action === 'disconnected')
                🔌
            @else
                ℹ️
            @endif
        </div>
        
        <h1>
            @if($action === 'connected')
                Successfully Connected!
            @elseif($action === 'disconnected')
                Disconnected
            @elseif($action === 'reconnect')
                Already Connected
            @else
                Success
            @endif
        </h1>
        
        <p class="message">{{ $message }}</p>

        @if(isset($user) && isset($company))
        <div class="details">
            <h3>Connection Details</h3>
            <div class="detail-item">
                <span class="detail-label">User:</span>
                <span class="detail-value">{{ $user }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Company:</span>
                <span class="detail-value">{{ $company }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Status:</span>
                <span class="detail-value">✅ Active</span>
            </div>
        </div>
        @endif

        @if($action === 'connected')
        <div class="next-steps">
            <h3>🎉 What's Next?</h3>
            <ul>
                <li>Your application can now sync data with Pipedrive</li>
                <li>Run <code>php artisan pipedrive:sync-entities</code> to start syncing</li>
                <li>Set up webhooks for real-time updates</li>
                <li>Configure scheduled sync for automatic updates</li>
            </ul>
        </div>
        @endif

        <div class="actions">
            @if($action === 'connected' || $action === 'reconnect')
                <a href="{{ route('pipedrive.oauth.status') }}" class="btn">
                    📊 View Status
                </a>
                <a href="{{ route('pipedrive.oauth.disconnect') }}" class="btn btn-danger">
                    🔌 Disconnect
                </a>
            @elseif($action === 'disconnected')
                <a href="{{ route('pipedrive.oauth.authorize') }}" class="btn">
                    🔗 Reconnect
                </a>
            @endif
            
            <br>
            <a href="{{ url('/') }}" class="btn btn-secondary">
                🏠 Go to Dashboard
            </a>
        </div>

        <div style="margin-top: 30px; font-size: 12px; color: #999;">
            @if($action === 'connected')
                Your OAuth token is securely stored and will be automatically refreshed when needed.
            @elseif($action === 'disconnected')
                Your OAuth token has been removed from our system.
            @endif
        </div>
    </div>
</body>
</html>

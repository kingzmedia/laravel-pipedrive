<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pipedrive Connection Error</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
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
        .error-icon {
            width: 80px;
            height: 80px;
            background: #dc3545;
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
        .error-title {
            color: #dc3545;
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 20px;
        }
        .message {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
            line-height: 1.5;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #dc3545;
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
        .troubleshooting {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        .troubleshooting h3 {
            margin: 0 0 15px 0;
            color: #856404;
        }
        .troubleshooting ul {
            margin: 0;
            padding-left: 20px;
            color: #856404;
        }
        .troubleshooting li {
            margin: 8px 0;
        }
        .troubleshooting code {
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
        <div class="error-icon">
            ⚠️
        </div>
        
        <h1>Connection Failed</h1>
        <div class="error-title">{{ $error }}</div>
        
        <div class="message">
            {{ $message }}
        </div>

        <div class="troubleshooting">
            <h3>🔧 Troubleshooting Steps</h3>
            <ul>
                @if(str_contains($error, 'not configured'))
                    <li>Check your <code>.env</code> file for OAuth configuration</li>
                    <li>Ensure <code>PIPEDRIVE_CLIENT_ID</code> is set</li>
                    <li>Ensure <code>PIPEDRIVE_CLIENT_SECRET</code> is set</li>
                    <li>Ensure <code>PIPEDRIVE_REDIRECT_URL</code> matches your app URL</li>
                    <li>Run <code>php artisan config:clear</code> after making changes</li>
                @elseif(str_contains($error, 'Invalid State'))
                    <li>This may be a security issue or session timeout</li>
                    <li>Try starting the OAuth process again</li>
                    <li>Clear your browser cookies and try again</li>
                @elseif(str_contains($error, 'Authorization Denied'))
                    <li>You declined the authorization request</li>
                    <li>Try the authorization process again</li>
                    <li>Make sure you click "Allow" on the Pipedrive authorization page</li>
                @else
                    <li>Check your internet connection</li>
                    <li>Verify your Pipedrive app configuration</li>
                    <li>Check the Laravel logs for more details</li>
                    <li>Ensure your redirect URL is correctly configured in Pipedrive</li>
                    <li>Try running <code>php artisan pipedrive:test-connection</code></li>
                @endif
            </ul>
        </div>

        <div style="margin-top: 30px;">
            <a href="{{ route('pipedrive.oauth.authorize') }}" class="btn">
                🔄 Try Again
            </a>
            <br>
            <a href="{{ route('pipedrive.oauth.status') }}" class="btn btn-secondary">
                📊 Check Status
            </a>
            <a href="{{ url('/') }}" class="btn btn-secondary">
                🏠 Go to Dashboard
            </a>
        </div>

        <div style="margin-top: 30px; font-size: 12px; color: #999;">
            If the problem persists, check your Laravel logs or contact support.
        </div>
    </div>
</body>
</html>

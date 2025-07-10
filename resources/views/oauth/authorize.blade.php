<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connect to Pipedrive</title>
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
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        .logo {
            width: 60px;
            height: 60px;
            background: #28a745;
            border-radius: 12px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }
        .scopes {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        .scopes h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 16px;
        }
        .scope-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .scope-list li {
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
            color: #555;
        }
        .scope-list li:last-child {
            border-bottom: none;
        }
        .scope-list li::before {
            content: "✓";
            color: #28a745;
            font-weight: bold;
            margin-right: 10px;
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
        .info {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            color: #004085;
            font-size: 14px;
        }
        .client-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">P</div>
        <h1>Connect to Pipedrive</h1>
        <p class="subtitle">Authorize your application to access Pipedrive data</p>

        <div class="client-info">
            <strong>Client ID:</strong> {{ $clientId }}
        </div>

        <div class="scopes">
            <h3>This application will be able to:</h3>
            <ul class="scope-list">
                @foreach($scopes as $scope)
                    <li>{{ ucfirst(str_replace([':', '_'], [' ', ' '], $scope)) }}</li>
                @endforeach
            </ul>
        </div>

        <div class="info">
            <strong>🔒 Secure Connection:</strong> Your credentials are handled securely through Pipedrive's OAuth 2.0 protocol. 
            We never see your Pipedrive password.
        </div>

        <div style="margin-top: 30px;">
            <a href="{{ $authUrl }}" class="btn">
                🚀 Connect to Pipedrive
            </a>
            <br>
            <a href="{{ url()->previous() }}" class="btn btn-secondary">
                ← Cancel
            </a>
        </div>

        <div style="margin-top: 30px; font-size: 12px; color: #999;">
            By connecting, you agree to allow this application to access your Pipedrive data 
            according to the permissions listed above.
        </div>
    </div>
</body>
</html>

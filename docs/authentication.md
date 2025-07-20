# 🔐 Authentication

## 🎯 **Overview**

Laravel-Pipedrive supports both **API Token** and **OAuth** authentication methods, providing flexibility for different use cases and security requirements.

## 🔑 **API Token Authentication (Recommended)**

### **Setup**

1. **Get your API token** from Pipedrive:
   - Go to Settings → Personal → API
   - Copy your API token

2. **Add to environment**:
   ```env
   PIPEDRIVE_TOKEN=your_api_token_here
   ```

3. **Test connection**:
   ```bash
   php artisan pipedrive:test-connection
   ```

### **Advantages**

✅ **Simple setup** - Just one token needed  
✅ **No expiration** - Tokens don't expire automatically  
✅ **Direct access** - No OAuth flow required  
✅ **Perfect for servers** - Ideal for background sync  

### **Limitations**

⚠️ **User-specific** - Limited to one user's permissions  
⚠️ **Rate limits** - 10,000 requests per day  
⚠️ **Manual rotation** - Must manually update if changed  

## 🔄 **OAuth Authentication (Enhanced)**

### **Complete OAuth Setup Guide**

#### **Step 1: Create Pipedrive App**

1. **Visit [Pipedrive Developer Hub](https://developers.pipedrive.com/)**
2. **Sign in** with your Pipedrive account
3. **Click "Create an app"**
4. **Fill in app details:**
   - **App name**: Your application name
   - **App description**: Brief description of your app
   - **App URL**: Your application's homepage URL
   - **Callback URL**: `https://your-domain.com/pipedrive/oauth/callback`
5. **Select required scopes** (permissions your app needs)
6. **Submit for review** (for public apps) or **create as private app**

#### **Step 2: Configure Laravel Application**

Add OAuth credentials to your `.env` file:

```env
PIPEDRIVE_AUTH_METHOD=oauth
PIPEDRIVE_CLIENT_ID=your_client_id_from_pipedrive
PIPEDRIVE_CLIENT_SECRET=your_client_secret_from_pipedrive
PIPEDRIVE_REDIRECT_URL=https://your-domain.com/pipedrive/oauth/callback
   ```

3. **Configure OAuth flow** (see implementation below)

### **Advantages**

✅ **Higher rate limits** - Based on your plan  
✅ **Multi-user support** - Different users can authorize  
✅ **Granular permissions** - Scope-based access  
✅ **Automatic refresh** - Tokens refresh automatically  

### **Limitations**

⚠️ **Complex setup** - Requires OAuth flow implementation  
⚠️ **Token management** - Must handle refresh tokens  
⚠️ **User interaction** - Requires user authorization  

## ⚙️ **Configuration**

### **Config File**

Publish and edit the config file:

```bash
php artisan vendor:publish --tag="laravel-pipedrive-config"
```

```php
// config/pipedrive.php
return [
    'auth' => [
        // Primary authentication method
        'method' => env('PIPEDRIVE_AUTH_METHOD', 'token'), // 'token' or 'oauth'
        
        // API Token settings
        'token' => env('PIPEDRIVE_TOKEN'),
        
        // OAuth settings
        'oauth' => [
            'client_id' => env('PIPEDRIVE_CLIENT_ID'),
            'client_secret' => env('PIPEDRIVE_CLIENT_SECRET'),
            'redirect_url' => env('PIPEDRIVE_REDIRECT_URL'),
            'scopes' => ['deals:read', 'deals:write', 'contacts:read'], // Optional
        ],
    ],
    
    // API settings
    'api' => [
        'base_url' => env('PIPEDRIVE_BASE_URL', 'https://api.pipedrive.com/v1'),
        'timeout' => env('PIPEDRIVE_TIMEOUT', 30),
        'retry_attempts' => env('PIPEDRIVE_RETRY_ATTEMPTS', 3),
    ],
];
```

## 🔧 **Implementation Examples**

### **Using API Token**

```php
use Keggermont\LaravelPipedrive\Services\PipedriveAuthService;

$authService = app(PipedriveAuthService::class);

// Test connection
$connection = $authService->testConnection();
if ($connection['success']) {
    echo "Connected as: {$connection['user']} ({$connection['company']})";
} else {
    echo "Connection failed: {$connection['error']}";
}

// Get authenticated client
$client = $authService->getClient();
$deals = $client->deals->all();
```

### **OAuth Flow Implementation**

#### **1. Authorization URL**

```php
use Keggermont\LaravelPipedrive\Services\PipedriveAuthService;

// Generate authorization URL
$authService = app(PipedriveAuthService::class);
$authUrl = $authService->getAuthorizationUrl([
    'deals:read',
    'deals:write',
    'contacts:read',
    'activities:read'
]);

return redirect($authUrl);
```

#### **2. Handle Callback**

```php
// routes/web.php
Route::get('/pipedrive/callback', function (Request $request) {
    $authService = app(PipedriveAuthService::class);
    
    try {
        $tokens = $authService->handleCallback($request->get('code'));
        
        // Store tokens for the user
        auth()->user()->update([
            'pipedrive_access_token' => $tokens['access_token'],
            'pipedrive_refresh_token' => $tokens['refresh_token'],
            'pipedrive_token_expires_at' => now()->addSeconds($tokens['expires_in']),
        ]);
        
        return redirect('/dashboard')->with('success', 'Pipedrive connected successfully!');
        
    } catch (\Exception $e) {
        return redirect('/dashboard')->with('error', 'Failed to connect to Pipedrive: ' . $e->getMessage());
    }
});
```

#### **3. Using OAuth Tokens**

```php
use Keggermont\LaravelPipedrive\Services\PipedriveAuthService;

$authService = app(PipedriveAuthService::class);

// Set user tokens
$authService->setOAuthTokens(
    auth()->user()->pipedrive_access_token,
    auth()->user()->pipedrive_refresh_token
);

// Get client (automatically refreshes if needed)
$client = $authService->getClient();
$deals = $client->deals->all();

// Check if tokens were refreshed
if ($authService->tokensWereRefreshed()) {
    $newTokens = $authService->getRefreshedTokens();
    auth()->user()->update([
        'pipedrive_access_token' => $newTokens['access_token'],
        'pipedrive_refresh_token' => $newTokens['refresh_token'],
        'pipedrive_token_expires_at' => now()->addSeconds($newTokens['expires_in']),
    ]);
}
```

## 🔄 **Token Management**

### **Automatic Token Refresh**

```php
use Keggermont\LaravelPipedrive\Services\PipedriveAuthService;

class PipedriveTokenManager
{
    public function refreshUserToken(User $user)
    {
        $authService = app(PipedriveAuthService::class);
        
        try {
            $newTokens = $authService->refreshToken($user->pipedrive_refresh_token);
            
            $user->update([
                'pipedrive_access_token' => $newTokens['access_token'],
                'pipedrive_refresh_token' => $newTokens['refresh_token'],
                'pipedrive_token_expires_at' => now()->addSeconds($newTokens['expires_in']),
            ]);
            
            return true;
        } catch (\Exception $e) {
            // Token refresh failed, user needs to re-authorize
            $user->update([
                'pipedrive_access_token' => null,
                'pipedrive_refresh_token' => null,
                'pipedrive_token_expires_at' => null,
            ]);
            
            return false;
        }
    }
}
```

### **Token Validation Middleware**

```php
class ValidatePipedriveToken
{
    public function handle($request, Closure $next)
    {
        $user = auth()->user();
        
        if (!$user->pipedrive_access_token) {
            return redirect()->route('pipedrive.connect');
        }
        
        // Check if token is expired
        if ($user->pipedrive_token_expires_at && $user->pipedrive_token_expires_at->isPast()) {
            $tokenManager = new PipedriveTokenManager();
            
            if (!$tokenManager->refreshUserToken($user)) {
                return redirect()->route('pipedrive.connect')
                    ->with('error', 'Pipedrive authorization expired. Please reconnect.');
            }
        }
        
        return $next($request);
    }
}
```

## 🧪 **Testing Authentication**

### **Test Connection Command**

```bash
# Test current configuration
php artisan pipedrive:test-connection

# Test with specific token
PIPEDRIVE_TOKEN=your_token php artisan pipedrive:test-connection

# Verbose output
php artisan pipedrive:test-connection --verbose
```

### **Programmatic Testing**

```php
use Keggermont\LaravelPipedrive\Services\PipedriveAuthService;

$authService = app(PipedriveAuthService::class);

// Test API token
$result = $authService->testConnection();
if ($result['success']) {
    echo "✅ Connected successfully";
    echo "User: {$result['user']}";
    echo "Company: {$result['company']}";
    echo "Method: {$result['method']}";
} else {
    echo "❌ Connection failed: {$result['error']}";
}

// Test OAuth tokens
$authService->setOAuthTokens($accessToken, $refreshToken);
$result = $authService->testConnection();
```

## 🔒 **Security Best Practices**

### **Environment Variables**

```env
# Use strong, unique tokens
PIPEDRIVE_TOKEN=your_very_long_secure_token_here

# Keep OAuth credentials secure
PIPEDRIVE_CLIENT_SECRET=your_super_secret_client_secret

# Use HTTPS for redirect URLs
PIPEDRIVE_REDIRECT_URL=https://your-app.com/pipedrive/callback
```

### **Token Storage**

```php
// ✅ Good: Encrypted database storage
Schema::table('users', function (Blueprint $table) {
    $table->text('pipedrive_access_token')->nullable();
    $table->text('pipedrive_refresh_token')->nullable();
    $table->timestamp('pipedrive_token_expires_at')->nullable();
});

// Add to User model
protected $casts = [
    'pipedrive_access_token' => 'encrypted',
    'pipedrive_refresh_token' => 'encrypted',
    'pipedrive_token_expires_at' => 'datetime',
];
```

### **Rate Limiting**

```php
// Implement rate limiting for API calls
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('pipedrive-api', function (Request $request) {
    return Limit::perMinute(100)->by($request->user()->id);
});
```

## 🚨 **Troubleshooting**

### **Common Issues**

1. **Invalid Token**
   ```bash
   ❌ Error: 401 Unauthorized
   # Solution: Check token validity, regenerate if needed
   ```

2. **Rate Limit Exceeded**
   ```bash
   ❌ Error: 429 Too Many Requests
   # Solution: Implement backoff, reduce request frequency
   ```

3. **OAuth Callback Error**
   ```bash
   ❌ Error: Invalid redirect URI
   # Solution: Check redirect URL matches exactly
   ```

4. **Token Refresh Failed**
   ```bash
   ❌ Error: Invalid refresh token
   # Solution: User needs to re-authorize
   ```

## 🌐 **Built-in OAuth Web Interface**

The package includes a complete OAuth web interface with beautiful, responsive pages for easy OAuth management.

### **Available Routes**

| Route | Purpose | Description |
|-------|---------|-------------|
| `/pipedrive/oauth/status` | Status Dashboard | Check connection status and manage OAuth |
| `/pipedrive/oauth/authorize` | Start OAuth Flow | Beautiful authorization page with scope details |
| `/pipedrive/oauth/callback` | OAuth Callback | Handles Pipedrive response (set in app config) |
| `/pipedrive/oauth/disconnect` | Disconnect | Safely remove stored tokens |

### **OAuth Flow with Web Interface**

#### **1. Check Status**
Visit `/pipedrive/oauth/status` to see:
- Current connection status
- Configuration validation
- Connection test results
- Token information

#### **2. Start Authorization**
Click "Connect to Pipedrive" or visit `/pipedrive/oauth/authorize`:
- Beautiful authorization page
- Shows required permissions/scopes
- CSRF protection included
- Client ID display for verification

#### **3. Pipedrive Authorization**
User is redirected to Pipedrive:
- User logs into Pipedrive
- Reviews and approves permissions
- Redirected back to your app

#### **4. Success Page**
After successful authorization:
- Displays connection success message
- Shows user and company information
- Provides next steps and management options

### **Token Management for Non-Expiring Tokens**

The package is optimized for non-expiring tokens:

```php
// Check token status
$tokenStorage = app(\Keggermont\LaravelPipedrive\Contracts\PipedriveTokenStorageInterface::class);

// Check if token exists
$hasToken = $tokenStorage->hasToken();

// Get token data without creating PipedriveToken object
$tokenData = $tokenStorage->getTokenData();

// Clear token (disconnect)
$tokenStorage->clearToken();
```

#### **Token Storage Details**

- **Cache Key**: `pipedrive_oauth_token`
- **Storage**: Laravel cache system (Redis/File)
- **TTL**: 1 year for non-expiring tokens
- **Security**: Encrypted by Laravel's cache encryption
- **Automatic Refresh**: For expiring tokens (when supported)

### **Customizing OAuth Pages**

You can customize the OAuth interface by publishing the views:

```bash
# Publish views for customization
php artisan vendor:publish --tag="laravel-pipedrive-views"
```

Views are located in `resources/views/vendor/laravel-pipedrive/oauth/`:
- `authorize.blade.php` - Authorization page
- `success.blade.php` - Success/completion page
- `error.blade.php` - Error handling page
- `status.blade.php` - Status dashboard

### **Security Features**

- **CSRF Protection**: State parameter validation
- **Secure Storage**: Tokens encrypted in cache
- **Error Handling**: Comprehensive error pages
- **Logging**: All OAuth events logged
- **Session Management**: Proper session handling

### **Debug Mode**

```php
// Enable debug logging
'debug' => env('PIPEDRIVE_DEBUG', false),

// Check logs
tail -f storage/logs/laravel.log | grep pipedrive
```

Choose the authentication method that best fits your use case - **API tokens** for simple server-to-server integration, or **OAuth** for multi-user applications! 🔐

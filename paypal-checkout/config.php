<?php
/**
 * EN: Handles application behavior in `paypal-checkout/config.php`.
 * AR: يدير سلوك جزء من التطبيق في `paypal-checkout/config.php`.
 */
/**
 * PayPal Checkout Configuration
 * Secure configuration file for PayPal REST API v2 integration
 * 
 * Security: Never commit .env file to version control
 */

// Load environment variables from .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue; // Skip empty lines and comments
        }
        
        if (strpos($line, '=') === false) {
            continue; // Skip lines without =
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        // Remove quotes if present
        $value = trim($value, '"\'');
        
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
    return true;
}

// Load .env file from parent directory
$envPath = __DIR__ . '/../.env';
if (!loadEnv($envPath)) {
    // Fallback: try current directory
    loadEnv(__DIR__ . '/.env');
}

/**
 * PayPal API Configuration
 * 
 * Sandbox: https://api.sandbox.paypal.com
 * Live: https://api.paypal.com
 */
define('PAYPAL_API_BASE', getenv('PAYPAL_API_BASE') ?: 'https://api.sandbox.paypal.com');
define('PAYPAL_CLIENT_ID', getenv('PAYPAL_CLIENT_ID') ?: '');
define('PAYPAL_SECRET', getenv('PAYPAL_SECRET') ?: '');

// Application Configuration
define('CURRENCY', 'USD');
define('COUNTRY_CODE', 'SA'); // Saudi Arabia

// Security: Validate required credentials
if (empty(PAYPAL_CLIENT_ID) || empty(PAYPAL_SECRET)) {
    error_log('PayPal: Missing credentials. Check .env file.');
    // Don't die here - let individual endpoints handle gracefully
}

/**
 * Get PayPal Access Token
 * 
 * This function authenticates with PayPal and returns an access token.
 * Access tokens expire after 9 hours.
 * 
 * @return string|false Access token or false on failure
 */
function getPayPalAccessToken() {
    // Validate credentials before making request
    if (empty(PAYPAL_CLIENT_ID) || empty(PAYPAL_SECRET)) {
        error_log('PayPal: Missing credentials in getPayPalAccessToken()');
        return false;
    }
    
    $url = PAYPAL_API_BASE . '/v1/oauth2/token';
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_USERPWD => PAYPAL_CLIENT_ID . ':' . PAYPAL_SECRET,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Accept-Language: en_US',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("PayPal Token Error: " . $error);
        return false;
    }
    
    if ($httpCode !== 200) {
        error_log("PayPal Token HTTP Error: " . $httpCode . " - " . $response);
        return false;
    }
    
    $data = json_decode($response, true);
    return $data['access_token'] ?? false;
}

/**
 * Make PayPal API Request
 * 
 * Generic function to make authenticated requests to PayPal API
 * 
 * @param string $endpoint API endpoint (without base URL)
 * @param string $method HTTP method (GET, POST, PATCH)
 * @param array|null $data Request body data
 * @return array|false Response data or false on failure
 */
function paypalApiRequest($endpoint, $method = 'GET', $data = null) {
    $token = getPayPalAccessToken();
    if (!$token) {
        return false;
    }
    
    $url = PAYPAL_API_BASE . $endpoint;
    
    $ch = curl_init($url);
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
        'PayPal-Request-Id: ' . uniqid('ratib_', true),
    ];
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'PATCH') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("PayPal API Error: " . $error);
        return false;
    }
    
    $responseData = json_decode($response, true);
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'data' => $responseData,
    ];
}

/**
 * Log Transaction
 * 
 * Log successful payment transactions for record keeping
 * 
 * @param string $orderId PayPal Order ID
 * @param string $transactionId PayPal Transaction ID
 * @param float $amount Payment amount
 * @param string $currency Currency code
 */
function logTransaction($orderId, $transactionId, $amount, $currency) {
    $logFile = __DIR__ . '/logs/transactions.log';
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0755, true)) {
            error_log('PayPal: Failed to create logs directory: ' . $logDir);
            return false;
        }
    }
    
    // Check if directory is writable
    if (!is_writable($logDir)) {
        error_log('PayPal: Logs directory is not writable: ' . $logDir);
        return false;
    }
    
    $logEntry = sprintf(
        "[%s] Order: %s | Transaction: %s | Amount: %s %s | IP: %s | User-Agent: %s\n",
        date('Y-m-d H:i:s'),
        $orderId,
        $transactionId,
        number_format($amount, 2),
        $currency,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 100)
    );
    
    $result = @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    if ($result === false) {
        error_log('PayPal: Failed to write transaction log');
        return false;
    }
    
    return true;
}

/**
 * Send JSON Response
 * 
 * Helper function to send consistent JSON responses
 * 
 * @param bool $success Success status
 * @param mixed $data Response data
 * @param string $message Error or success message
 * @param int $httpCode HTTP status code
 */
function jsonResponse($success, $data = null, $message = '', $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=UTF-8');
    
    $response = ['success' => $success];
    if ($message) {
        $response['message'] = $message;
    }
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

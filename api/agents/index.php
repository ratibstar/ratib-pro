<?php
/**
 * EN: Handles API endpoint/business logic in `api/agents/index.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/agents/index.php`.
 */
// Common API configuration
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
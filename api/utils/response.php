<?php
/**
 * EN: Handles API endpoint/business logic in `api/utils/response.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/utils/response.php`.
 */
/**
 * Response utility functions for API endpoints
 */

/**
 * Send a JSON response
 * @param array $data The data to send
 * @param int $status_code HTTP status code (default: 200)
 */
function sendResponse($data, $status_code = 200) {
    // Set headers
    header("Content-Type: application/json; charset=utf-8");
    header("Cache-Control: no-cache, must-revalidate");
    http_response_code($status_code);
    
    // Send JSON response
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send a success response
 * @param array $data The data to send
 * @param string $message Success message
 */
function sendSuccessResponse($data = null, $message = "Success") {
    sendResponse([
        "success" => true,
        "data" => $data,
        "message" => $message
    ]);
}

/**
 * Send an error response
 * @param string $message Error message
 * @param int $status_code HTTP status code (default: 400)
 */
function sendErrorResponse($message = "An error occurred", $status_code = 400) {
    sendResponse([
        "success" => false,
        "message" => $message
    ], $status_code);
}

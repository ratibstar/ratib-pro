<?php
/**
 * EN: Handles API endpoint/business logic in `api/core/ApiResponse.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/core/ApiResponse.php`.
 */
if (!class_exists('ApiResponse')) {
class ApiResponse {
    public static function success($data = null, $message = '') {
        return json_encode([
            'success' => true,
            'data' => $data,
            'message' => $message
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function error($message = 'An error occurred', $code = 400) {
        http_response_code($code);
        return json_encode([
            'success' => false,
            'message' => $message
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
} // End of class_exists check
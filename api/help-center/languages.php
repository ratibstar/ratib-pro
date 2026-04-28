<?php
/**
 * EN: Handles API endpoint/business logic in `api/help-center/languages.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/help-center/languages.php`.
 */
/**
 * Help Center - Languages API
 * Handles language-related operations
 */

ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

session_start();
require_once(__DIR__ . '/../core/ApiResponse.php');

class LanguagesAPI {
    /**
     * Supported languages configuration
     */
    private $languages = [
        'en' => ['name' => 'English', 'native_name' => 'English', 'rtl' => false, 'country' => 'Global']
    ];

    /**
     * Get all supported languages
     */
    public function getLanguages() {
        return ApiResponse::success($this->languages);
    }

    /**
     * Switch language
     */
    public function switchLanguage($langCode) {
        // Validate language code
        if (!isset($this->languages[$langCode])) {
            $langCode = 'en'; // Default to English if invalid
        }
        
        $_SESSION['help_language'] = $langCode;
        
        $message = 'Language set to ' . $this->languages[$langCode]['name'];
        
        return ApiResponse::success([
            'language' => $langCode,
            'language_info' => $this->languages[$langCode]
        ], $message);
    }

    /**
     * Get current language
     */
    public function getCurrentLanguage() {
        $langCode = $_SESSION['help_language'] ?? 'en';
        
        // Validate language code
        if (!isset($this->languages[$langCode])) {
            $langCode = 'en';
            $_SESSION['help_language'] = 'en';
        }
        
        return ApiResponse::success([
            'language' => $langCode,
            'language_info' => $this->languages[$langCode]
        ]);
    }

    /**
     * Handle request
     */
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $action = $_GET['action'] ?? 'list';

        try {
            // Clean output buffer before sending JSON
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            switch ($method) {
                case 'GET':
                    if ($action === 'switch') {
                        $lang = $_GET['lang'] ?? $_SESSION['help_language'] ?? 'en';
                        echo $this->switchLanguage($lang);
                    } elseif ($action === 'current') {
                        echo $this->getCurrentLanguage();
                    } else {
                        echo $this->getLanguages();
                    }
                    break;
                case 'POST':
                    $data = json_decode(file_get_contents('php://input'), true);
                    $lang = $data['language'] ?? $data['lang'] ?? $_SESSION['help_language'] ?? 'en';
                    echo $this->switchLanguage($lang);
                    break;
                default:
                    echo ApiResponse::error('Method not allowed', 405);
            }
        } catch (Exception $e) {
            error_log("LanguagesAPI handleRequest error: " . $e->getMessage());
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            echo ApiResponse::error('Internal server error', 500);
        }
    }
}

$api = new LanguagesAPI();
$api->handleRequest();

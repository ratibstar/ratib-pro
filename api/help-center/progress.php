<?php
/**
 * EN: Handles API endpoint/business logic in `api/help-center/progress.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/help-center/progress.php`.
 */
/**
 * Help Center - Progress API
 * Handles user progress tracking
 */

ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

session_start();
require_once(__DIR__ . '/../core/Database.php');
require_once(__DIR__ . '/../core/ApiResponse.php');

class ProgressAPI {
    private $db;
    private $conn;

    public function __construct() {
        try {
            $this->db = Database::getInstance();
            $this->conn = $this->db->getConnection();
        } catch (Exception $e) {
            error_log('ProgressAPI constructor error: ' . $e->getMessage());
            $this->db = null;
            $this->conn = null;
        }
    }

    /**
     * Get user progress
     */
    public function getProgress($userId = null) {
        try {
            if (!$userId) {
                $userId = $_SESSION['user_id'] ?? null;
            }

            if (!$userId) {
                return ApiResponse::error('User not authenticated', 401);
            }

            // Get language from query parameter, session, or default to 'en'
            $lang = $_GET['lang'] ?? $_SESSION['help_language'] ?? 'en';
            
            // Validate language code
            if (!in_array($lang, ['en', 'bn'])) {
                $lang = 'en';
            }

            $sql = "SELECT 
                        utp.*,
                        tl.title,
                        t.slug,
                        tc.slug as category_slug
                    FROM user_tutorial_progress utp
                    LEFT JOIN tutorials t ON utp.tutorial_id = t.id
                    LEFT JOIN tutorial_languages tl ON t.id = tl.tutorial_id AND tl.language_code = ?
                    LEFT JOIN tutorial_categories tc ON t.category_id = tc.id
                    WHERE utp.user_id = ? AND utp.language_code = ?
                    ORDER BY utp.last_accessed_at DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$lang, $userId, $lang]);
            $progress = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get statistics
            $statsSql = "SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                            SUM(time_spent) as total_time
                        FROM user_tutorial_progress
                        WHERE user_id = ? AND language_code = ?";
            $statsStmt = $this->conn->prepare($statsSql);
            $statsStmt->execute([$userId, $lang]);
            $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

            return ApiResponse::success([
                'progress' => $progress,
                'stats' => $stats
            ]);
        } catch (Exception $e) {
            error_log("getProgress error: " . $e->getMessage());
            return ApiResponse::error('Failed to fetch progress: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update progress
     */
    public function updateProgress($data) {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                return ApiResponse::error('User not authenticated', 401);
            }

            $tutorialId = $data['tutorial_id'] ?? null;
            $status = $data['status'] ?? 'started';
            $progress = $data['progress_percentage'] ?? 0;
            $timeSpent = $data['time_spent'] ?? 0;
            
            // Get language from data, query parameter, session, or default to 'en'
            $lang = $data['language_code'] ?? $_GET['lang'] ?? $_SESSION['help_language'] ?? 'en';
            
            // Validate language code
            if (!in_array($lang, ['en', 'bn'])) {
                $lang = 'en';
            }

            if (!$tutorialId) {
                return ApiResponse::error('Tutorial ID required', 400);
            }

            $sql = "INSERT INTO user_tutorial_progress (
                        user_id, tutorial_id, language_code, status, 
                        progress_percentage, time_spent, last_accessed_at,
                        completed_at
                    ) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)
                    ON DUPLICATE KEY UPDATE
                        status = VALUES(status),
                        progress_percentage = VALUES(progress_percentage),
                        time_spent = time_spent + VALUES(time_spent),
                        last_accessed_at = CURRENT_TIMESTAMP,
                        completed_at = VALUES(completed_at),
                        updated_at = CURRENT_TIMESTAMP";

            $completedAt = ($status === 'completed') ? date('Y-m-d H:i:s') : null;

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $userId,
                $tutorialId,
                $lang,
                $status,
                $progress,
                $timeSpent,
                $completedAt
            ]);

            return ApiResponse::success(null, 'Progress updated successfully');
        } catch (Exception $e) {
            error_log("updateProgress error: " . $e->getMessage());
            return ApiResponse::error('Failed to update progress: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle request
     */
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];

        try {
            // Clean output buffer before sending JSON
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            switch ($method) {
                case 'GET':
                    echo $this->getProgress();
                    break;
                case 'POST':
                    $data = json_decode(file_get_contents('php://input'), true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        echo ApiResponse::error('Invalid JSON data', 400);
                        return;
                    }
                    echo $this->updateProgress($data);
                    break;
                default:
                    echo ApiResponse::error('Method not allowed', 405);
            }
        } catch (Exception $e) {
            error_log("ProgressAPI handleRequest error: " . $e->getMessage());
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            echo ApiResponse::error('Internal server error', 500);
        }
    }
}

$api = new ProgressAPI();
$api->handleRequest();

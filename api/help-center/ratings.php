<?php
/**
 * EN: Handles API endpoint/business logic in `api/help-center/ratings.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/help-center/ratings.php`.
 */
/**
 * Help Center - Ratings API
 * Handles tutorial ratings and feedback
 */

ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

session_start();
require_once(__DIR__ . '/../core/Database.php');
require_once(__DIR__ . '/../core/ApiResponse.php');

class RatingsAPI {
    private $db;
    private $conn;

    public function __construct() {
        try {
            $this->db = Database::getInstance();
            $this->conn = $this->db->getConnection();
        } catch (Exception $e) {
            error_log('RatingsAPI constructor error: ' . $e->getMessage());
            $this->db = null;
            $this->conn = null;
        }
    }

    /**
     * Submit rating
     */
    public function submitRating($data) {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                return ApiResponse::error('User not authenticated', 401);
            }

            $tutorialId = $data['tutorial_id'] ?? null;
            $rating = (int)($data['rating'] ?? 0);
            $helpful = isset($data['helpful']) ? (int)$data['helpful'] : null;
            $comment = $data['comment'] ?? null;
            
            // Get language from data, query parameter, session, or default to 'en'
            $lang = $data['language_code'] ?? $_GET['lang'] ?? $_SESSION['help_language'] ?? 'en';
            
            // Validate language code
            if (!in_array($lang, ['en', 'bn'])) {
                $lang = 'en';
            }

            if (!$tutorialId || $rating < 1 || $rating > 5) {
                return ApiResponse::error('Invalid rating data', 400);
            }

            $sql = "INSERT INTO tutorial_ratings (
                        tutorial_id, user_id, rating, helpful, comment, language_code
                    ) VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        rating = VALUES(rating),
                        helpful = VALUES(helpful),
                        comment = VALUES(comment),
                        updated_at = CURRENT_TIMESTAMP";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $tutorialId,
                $userId,
                $rating,
                $helpful,
                $comment,
                $lang
            ]);

            // Update tutorial likes count based on ratings
            $this->updateTutorialRatingStats($tutorialId);

            return ApiResponse::success(null, 'Rating submitted successfully');
        } catch (Exception $e) {
            error_log("submitRating error: " . $e->getMessage());
            return ApiResponse::error('Failed to submit rating: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get tutorial ratings
     */
    public function getRatings($tutorialId) {
        try {
            $sql = "SELECT 
                        r.*,
                        u.username
                    FROM tutorial_ratings r
                    LEFT JOIN users u ON r.user_id = u.id
                    WHERE r.tutorial_id = ?
                    ORDER BY r.created_at DESC
                    LIMIT 50";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$tutorialId]);
            $ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate average rating
            $avgSql = "SELECT 
                        AVG(rating) as average_rating,
                        COUNT(*) as total_ratings,
                        SUM(CASE WHEN helpful = 1 THEN 1 ELSE 0 END) as helpful_count
                    FROM tutorial_ratings
                    WHERE tutorial_id = ?";
            $avgStmt = $this->conn->prepare($avgSql);
            $avgStmt->execute([$tutorialId]);
            $stats = $avgStmt->fetch(PDO::FETCH_ASSOC);

            return ApiResponse::success([
                'ratings' => $ratings,
                'stats' => $stats
            ]);
        } catch (Exception $e) {
            error_log("getRatings error: " . $e->getMessage());
            return ApiResponse::error('Failed to fetch ratings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update tutorial rating statistics
     */
    private function updateTutorialRatingStats($tutorialId) {
        try {
            $sql = "UPDATE tutorials SET 
                        likes_count = (
                            SELECT COUNT(*) 
                            FROM tutorial_ratings 
                            WHERE tutorial_id = ? AND rating >= 4
                        )
                    WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$tutorialId, $tutorialId]);
        } catch (Exception $e) {
            error_log("updateTutorialRatingStats error: " . $e->getMessage());
        }
    }

    /**
     * Handle request
     */
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $tutorialId = $_GET['tutorial_id'] ?? null;

        try {
            // Clean output buffer before sending JSON
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            switch ($method) {
                case 'GET':
                    if (!$tutorialId) {
                        echo ApiResponse::error('Tutorial ID required', 400);
                        return;
                    }
                    echo $this->getRatings($tutorialId);
                    break;
                case 'POST':
                    $data = json_decode(file_get_contents('php://input'), true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        echo ApiResponse::error('Invalid JSON data', 400);
                        return;
                    }
                    echo $this->submitRating($data);
                    break;
                default:
                    echo ApiResponse::error('Method not allowed', 405);
            }
        } catch (Exception $e) {
            error_log("RatingsAPI handleRequest error: " . $e->getMessage());
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            echo ApiResponse::error('Internal server error', 500);
        }
    }
}

$api = new RatingsAPI();
$api->handleRequest();

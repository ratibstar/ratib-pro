<?php
/**
 * EN: Handles API endpoint/business logic in `api/help-center/search.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/help-center/search.php`.
 */
/**
 * Help Center - Search API
 * Handles tutorial search functionality
 */

ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

session_start();
require_once(__DIR__ . '/../core/Database.php');
require_once(__DIR__ . '/../core/ApiResponse.php');

class SearchAPI {
    private $db;
    private $conn;

    public function __construct() {
        try {
            $this->db = Database::getInstance();
            $this->conn = $this->db->getConnection();
        } catch (Exception $e) {
            error_log('SearchAPI constructor error: ' . $e->getMessage());
            $this->db = null;
            $this->conn = null;
        }
    }

    /**
     * Search tutorials
     */
    public function search($query, $lang = 'en', $limit = 20) {
        try {
            if (empty($query)) {
                return ApiResponse::error('Search query required', 400);
            }

            // Full-text search
            $sql = "SELECT 
                        t.id,
                        t.slug,
                        t.category_id,
                        t.difficulty_level,
                        t.estimated_time,
                        t.views_count,
                        tl.title,
                        tl.overview,
                        tc.slug as category_slug,
                        MATCH(tl.title, tl.overview_text, tl.content_text) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                    FROM tutorials t
                    LEFT JOIN tutorial_languages tl ON t.id = tl.tutorial_id AND tl.language_code = ?
                    LEFT JOIN tutorial_categories tc ON t.category_id = tc.id
                    WHERE t.status = 'published'
                    AND MATCH(tl.title, tl.overview_text, tl.content_text) AGAINST(? IN NATURAL LANGUAGE MODE)
                    ORDER BY relevance DESC, t.views_count DESC
                    LIMIT ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$query, $lang, $query, $limit]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fallback to LIKE search if full-text returns no results
            if (empty($results)) {
                $likeSql = "SELECT 
                                t.id,
                                t.slug,
                                t.category_id,
                                t.difficulty_level,
                                t.estimated_time,
                                t.views_count,
                                tl.title,
                                tl.overview,
                                tc.slug as category_slug
                            FROM tutorials t
                            LEFT JOIN tutorial_languages tl ON t.id = tl.tutorial_id AND tl.language_code = ?
                            LEFT JOIN tutorial_categories tc ON t.category_id = tc.id
                            WHERE t.status = 'published'
                            AND (tl.title LIKE ? OR tl.overview_text LIKE ? OR tl.content_text LIKE ?)
                            ORDER BY t.views_count DESC
                            LIMIT ?";
                $searchTerm = "%{$query}%";
                $likeStmt = $this->conn->prepare($likeSql);
                $likeStmt->execute([$lang, $searchTerm, $searchTerm, $searchTerm, $limit]);
                $results = $likeStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            return ApiResponse::success([
                'query' => $query,
                'results' => $results,
                'count' => count($results)
            ]);
        } catch (Exception $e) {
            error_log("search error: " . $e->getMessage());
            return ApiResponse::error('Search failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle request
     */
    public function handleRequest() {
        try {
            $query = $_GET['q'] ?? $_POST['q'] ?? '';
            
            // Get language from query parameter, session, or default to 'en'
            $lang = $_GET['lang'] ?? $_SESSION['help_language'] ?? 'en';
            
            // Validate language code
            if (!in_array($lang, ['en', 'bn'])) {
                $lang = 'en';
            }
            
            $limit = (int)($_GET['limit'] ?? 20);
            
            // Validate limit
            if ($limit < 1 || $limit > 100) {
                $limit = 20;
            }
            
            // Clean output buffer before sending JSON
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            echo $this->search($query, $lang, $limit);
        } catch (Exception $e) {
            error_log("SearchAPI handleRequest error: " . $e->getMessage());
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            echo ApiResponse::error('Internal server error', 500);
        }
    }
}

$api = new SearchAPI();
$api->handleRequest();

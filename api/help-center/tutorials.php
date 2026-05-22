<?php
/**
 * EN: Handles API endpoint/business logic in `api/help-center/tutorials.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/help-center/tutorials.php`.
 */
/**
 * Help Center - Tutorials API
 * Handles all tutorial-related CRUD operations
 */

ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

session_start();
require_once(__DIR__ . '/../core/Database.php');
require_once(__DIR__ . '/../core/ApiResponse.php');
require_once(__DIR__ . '/seed-tutorial-content.php');

class TutorialsAPI {
    private $db;
    private $conn;

    public function __construct() {
        try {
            $this->db = Database::getInstance();
            $this->conn = $this->db->getConnection();
        } catch (Exception $e) {
            error_log('TutorialsAPI constructor error: ' . $e->getMessage());
            $this->db = null;
            $this->conn = null;
        }
    }

    /**
     * Handle GET requests
     */
    private function handleGet($action) {
        // Get language from query parameter, session, or default to 'en'
        $lang = $_GET['lang'] ?? $_SESSION['help_language'] ?? 'en';
        
        // Validate language code
        if (!in_array($lang, ['en', 'bn'])) {
            $lang = 'en';
        }

        switch ($action) {
            case 'list':
                return $this->getTutorials($lang);
            case 'get':
                $id = $_GET['id'] ?? null;
                return $this->getTutorial($id, $lang);
            case 'by-category':
                $categoryId = $_GET['category_id'] ?? null;
                return $this->getTutorialsByCategory($categoryId, $lang);
            case 'featured':
                return $this->getFeaturedTutorials($lang);
            case 'popular':
                return $this->getPopularTutorials($lang);
            case 'recent':
                return $this->getRecentTutorials($lang);
            default:
                return $this->getTutorials($lang);
        }
    }

    /**
     * Handle POST requests
     */
    private function handlePost($action) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$this->hasAdminPermission()) {
            return ApiResponse::error('Permission denied', 403);
        }

        switch ($action) {
            case 'create':
                return $this->createTutorial($data);
            case 'update':
                return $this->updateTutorial($data);
            default:
                return ApiResponse::error('Invalid action', 400);
        }
    }

    /**
     * Handle PUT requests
     */
    private function handlePut($action) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$this->hasAdminPermission()) {
            return ApiResponse::error('Permission denied', 403);
        }

        return $this->updateTutorial($data);
    }

    /**
     * Handle DELETE requests
     */
    private function handleDelete($action) {
        $id = $_GET['id'] ?? null;
        
        if (!$this->hasAdminPermission()) {
            return ApiResponse::error('Permission denied', 403);
        }

        if (!$id) {
            return ApiResponse::error('Tutorial ID required', 400);
        }

        return $this->deleteTutorial($id);
    }

    /**
     * Create help-center tables if they do not exist (so tutorials can be seeded).
     */
    private function createHelpCenterTablesIfMissing() {
        try {
            $this->conn->exec("CREATE TABLE IF NOT EXISTS tutorial_categories (
                id INT PRIMARY KEY AUTO_INCREMENT,
                parent_id INT NULL DEFAULT NULL,
                slug VARCHAR(100) NOT NULL UNIQUE,
                icon VARCHAR(50) DEFAULT 'fa-circle',
                display_order INT DEFAULT 0,
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_display_order (display_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $this->conn->exec("CREATE TABLE IF NOT EXISTS tutorial_category_translations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                category_id INT NOT NULL,
                language_code VARCHAR(10) NOT NULL,
                name VARCHAR(200) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_cat_lang (category_id, language_code),
                INDEX idx_language (language_code),
                FOREIGN KEY (category_id) REFERENCES tutorial_categories(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $this->conn->exec("CREATE TABLE IF NOT EXISTS tutorials (
                id INT PRIMARY KEY AUTO_INCREMENT,
                category_id INT NOT NULL,
                slug VARCHAR(200) NOT NULL UNIQUE,
                author_id INT NULL,
                difficulty_level ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
                estimated_time INT DEFAULT 5,
                status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
                views_count INT DEFAULT 0,
                likes_count INT DEFAULT 0,
                featured TINYINT(1) DEFAULT 0,
                requires_permission VARCHAR(50) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                published_at TIMESTAMP NULL,
                INDEX idx_category (category_id),
                INDEX idx_status (status),
                INDEX idx_slug (slug),
                FOREIGN KEY (category_id) REFERENCES tutorial_categories(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $this->conn->exec("CREATE TABLE IF NOT EXISTS tutorial_languages (
                id INT PRIMARY KEY AUTO_INCREMENT,
                tutorial_id INT NOT NULL,
                language_code VARCHAR(10) NOT NULL,
                title VARCHAR(300) NOT NULL,
                overview TEXT,
                content LONGTEXT,
                overview_text TEXT,
                content_text LONGTEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_tut_lang (tutorial_id, language_code),
                INDEX idx_language (language_code),
                INDEX idx_tutorial (tutorial_id),
                FOREIGN KEY (tutorial_id) REFERENCES tutorials(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Exception $e) {
            error_log("createHelpCenterTablesIfMissing: " . $e->getMessage());
        }
    }

    /**
     * Ensure tutorial_categories has ids 1-12 so tutorial inserts can succeed (e.g. if user opened a category before loading home).
     */
    private function ensureHelpCenterCategoriesExist() {
        try {
            $this->createHelpCenterTablesIfMissing();
            $chk = $this->conn->query("SELECT COUNT(*) as n FROM tutorial_categories");
            if ($chk && (int)$chk->fetch(PDO::FETCH_ASSOC)['n'] > 0) return;
            $cats = [
                [1, null, 'getting-started', 'fa-rocket', 1], [2, null, 'dashboard', 'fa-tachometer-alt', 2],
                [3, null, 'user-management', 'fa-users-cog', 3], [4, null, 'contracts-recruitment', 'fa-file-contract', 4],
                [5, null, 'client-management', 'fa-building', 5], [6, null, 'worker-management', 'fa-hard-hat', 6],
                [7, null, 'finance-billing', 'fa-dollar-sign', 7], [8, null, 'reports-analytics', 'fa-chart-bar', 8],
                [9, null, 'notifications-automation', 'fa-bell', 9], [10, null, 'troubleshooting-faq', 'fa-question-circle', 10],
                [11, null, 'best-practices', 'fa-star', 11], [12, null, 'compliance-legal', 'fa-gavel', 12],
            ];
            $names = [
                1 => ['Getting Started', 'Introduction to the system and how it works'],
                2 => ['Dashboard', 'Dashboard navigation and features guide'],
                3 => ['User Management & Permissions', 'Managing users, roles, and permissions'],
                4 => ['Contracts & Recruitment', 'Contract management and recruitment processes'],
                5 => ['Client Management', 'Managing clients and client relationships'],
                6 => ['Worker Management', 'Worker profiles, documents, and management'],
                7 => ['Finance & Billing', 'Financial management and billing operations'],
                8 => ['Reports & Analytics', 'Generating and viewing reports and analytics'],
                9 => ['Notifications & Automation', 'System notifications and automation features'],
                10 => ['Troubleshooting & FAQ', 'Common issues and frequently asked questions'],
                11 => ['Best Practices', 'Recommended practices and workflows'],
                12 => ['Compliance & Legal', 'Compliance guidelines and legal requirements'],
            ];
            $insCat = $this->conn->prepare("INSERT INTO tutorial_categories (id, parent_id, slug, icon, display_order, status) VALUES (?, ?, ?, ?, ?, 'active')");
            foreach ($cats as $r) $insCat->execute($r);
            $insTr = $this->conn->prepare("INSERT INTO tutorial_category_translations (category_id, language_code, name, description) VALUES (?, 'en', ?, ?)");
            foreach ($names as $id => $n) $insTr->execute([$id, $n[0], $n[1]]);
        } catch (Exception $e) {
            error_log("ensureHelpCenterCategoriesExist: " . $e->getMessage());
        }
    }

    /**
     * Seed one tutorial per category with detailed program explanations, or upgrade existing ones.
     */
    private function seedDefaultTutorials() {
        if (!$this->conn) return;
        try {
            $this->ensureHelpCenterCategoriesExist();
            $chk = $this->conn->query("SELECT COUNT(*) as n FROM tutorials WHERE status = 'published'");
            $count = $chk ? (int)$chk->fetch(PDO::FETCH_ASSOC)['n'] : 0;
            $content = help_center_seed_content();
            $slugs = [
                1 => 'getting-started-guide', 2 => 'dashboard-overview', 3 => 'user-management-guide', 4 => 'contracts-recruitment-guide',
                5 => 'client-management-guide', 6 => 'worker-management-guide', 7 => 'finance-billing-guide', 8 => 'reports-analytics-guide',
                9 => 'notifications-automation-guide', 10 => 'troubleshooting-faq', 11 => 'best-practices-guide', 12 => 'compliance-legal-guide'
            ];

            if ($count > 0) {
                // Upgrade existing tutorials with detailed content
                $sel = $this->conn->prepare("SELECT id, category_id FROM tutorials WHERE status = 'published' AND category_id BETWEEN 1 AND 12 ORDER BY category_id");
                $sel->execute();
                $rows = $sel->fetchAll(PDO::FETCH_ASSOC);
                $upd = $this->conn->prepare("UPDATE tutorial_languages SET title = ?, overview = ?, content = ?, overview_text = ?, content_text = ? WHERE tutorial_id = ? AND language_code = 'en'");
                foreach ($rows as $r) {
                    $catId = (int)$r['category_id'];
                    if (!isset($content[$catId])) continue;
                    $row = $content[$catId];
                    $upd->execute([
                        $row[0],
                        $row[1],
                        $row[2],
                        strip_tags($row[1]),
                        strip_tags($row[2]),
                        $r['id']
                    ]);
                }
                // Add second (quick-tips) tutorial per category if we only have 12 tutorials
                $extra = function_exists('help_center_seed_extra_tutorials') ? help_center_seed_extra_tutorials() : [];
                if ($count <= 12 && !empty($extra)) {
                    $countPerCat = $this->conn->query("SELECT category_id, COUNT(*) as n FROM tutorials WHERE status = 'published' AND category_id BETWEEN 1 AND 12 GROUP BY category_id")->fetchAll(PDO::FETCH_ASSOC);
                    $catsWithOne = array_column(array_filter($countPerCat, function ($x) { return (int)$x['n'] === 1; }), 'category_id');
                    $insT2 = $this->conn->prepare("INSERT INTO tutorials (category_id, slug, difficulty_level, estimated_time, status, featured) VALUES (?, ?, 'beginner', 5, 'published', 0)");
                    $insL = $this->conn->prepare("INSERT INTO tutorial_languages (tutorial_id, language_code, title, overview, content, overview_text, content_text) VALUES (?, 'en', ?, ?, ?, ?, ?)");
                    foreach ($catsWithOne as $catId) {
                        $catId = (int)$catId;
                        if (!isset($extra[$catId])) continue;
                        $row = $extra[$catId];
                        $insT2->execute([$catId, $row[0]]);
                        $tid = (int)$this->conn->lastInsertId();
                        if ($tid <= 0) continue;
                        $insL->execute([$tid, $row[1], $row[2], $row[3], strip_tags($row[2]), strip_tags($row[3])]);
                    }
                }
                return;
            }

            $insT = $this->conn->prepare("INSERT INTO tutorials (category_id, slug, difficulty_level, estimated_time, status, featured) VALUES (?, ?, 'beginner', 12, 'published', 0)");
            $insL = $this->conn->prepare("INSERT INTO tutorial_languages (tutorial_id, language_code, title, overview, content, overview_text, content_text) VALUES (?, 'en', ?, ?, ?, ?, ?)");
            foreach ($content as $catId => $row) {
                $insT->execute([$catId, $slugs[$catId]]);
                $tid = (int)$this->conn->lastInsertId();
                if ($tid <= 0) continue;
                $insL->execute([$tid, $row[0], $row[1], $row[2], strip_tags($row[1]), strip_tags($row[2])]);
            }
            // Second tutorial per category (quick tips)
            $extra = function_exists('help_center_seed_extra_tutorials') ? help_center_seed_extra_tutorials() : [];
            $insT2 = $this->conn->prepare("INSERT INTO tutorials (category_id, slug, difficulty_level, estimated_time, status, featured) VALUES (?, ?, 'beginner', 5, 'published', 0)");
            foreach ($extra as $catId => $row) {
                $insT2->execute([$catId, $row[0]]);
                $tid = (int)$this->conn->lastInsertId();
                if ($tid <= 0) continue;
                $insL->execute([$tid, $row[1], $row[2], $row[3], strip_tags($row[2]), strip_tags($row[3])]);
            }
        } catch (Exception $e) {
            error_log("seedDefaultTutorials error: " . $e->getMessage());
        }
    }

    /**
     * Get all tutorials
     */
    private function getTutorials($lang = 'en') {
        try {
            if ($this->conn) $this->seedDefaultTutorials();
            $categoryId = $_GET['category_id'] ?? null;
            $status = $_GET['status'] ?? 'published';
            $search = $_GET['search'] ?? null;
            $limit = (int)($_GET['limit'] ?? 50);
            $offset = (int)($_GET['offset'] ?? 0);

            $sql = "SELECT 
                        t.id,
                        t.category_id,
                        t.slug,
                        t.difficulty_level,
                        t.estimated_time,
                        t.views_count,
                        t.likes_count,
                        t.featured,
                        t.status,
                        t.created_at,
                        t.updated_at,
                        tl.title,
                        tl.overview,
                        tc.slug as category_slug
                    FROM tutorials t
                    LEFT JOIN tutorial_languages tl ON t.id = tl.tutorial_id AND tl.language_code = ?
                    LEFT JOIN tutorial_categories tc ON t.category_id = tc.id
                    WHERE t.status = ?";

            $params = [$lang, $status];

            if ($categoryId) {
                $sql .= " AND t.category_id = ?";
                $params[] = $categoryId;
            }

            if ($search) {
                $sql .= " AND (tl.title LIKE ? OR tl.overview_text LIKE ?)";
                $searchTerm = "%{$search}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $sql .= " ORDER BY t.featured DESC, t.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $tutorials = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fallback: if requested language not found, try English, then use slug-based fallback
            $isArabic = function ($s) { return preg_match('/[\x{0600}-\x{06FF}]/u', $s ?? ''); };
            
            // Translation strings for fallback messages
            $fallbackMessages = [
                'en' => [
                    'guideFor' => 'Guide for ',
                    'tutorial' => 'Tutorial'
                ],
                'bn' => [
                    'guideFor' => 'নির্দেশিকা: ',
                    'tutorial' => 'টিউটোরিয়াল'
                ]
            ];
            $msgs = $fallbackMessages[$lang] ?? $fallbackMessages['en'];
            
            foreach ($tutorials as &$t) {
                $slug = $t['slug'] ?? '';
                $fallbackTitle = ucwords(str_replace('-', ' ', $slug)) ?: $msgs['tutorial'];
                
                // If title/overview empty or Arabic, try to get English version
                if (empty($t['title']) || $isArabic($t['title'])) {
                    if ($lang !== 'en') {
                        // Try to get English version
                        $enSql = "SELECT title, overview FROM tutorial_languages WHERE tutorial_id = ? AND language_code = 'en' LIMIT 1";
                        $enStmt = $this->conn->prepare($enSql);
                        $enStmt->execute([$t['id']]);
                        $enContent = $enStmt->fetch(PDO::FETCH_ASSOC);
                        if ($enContent && !empty($enContent['title']) && !$isArabic($enContent['title'])) {
                            $t['title'] = $enContent['title'];
                            $t['overview'] = $enContent['overview'] ?? '';
                        } else {
                            $t['title'] = $fallbackTitle;
                            $t['overview'] = $msgs['guideFor'] . $fallbackTitle;
                        }
                    } else {
                        $t['title'] = $fallbackTitle;
                        $t['overview'] = $msgs['guideFor'] . $fallbackTitle;
                    }
                }
                if (empty($t['overview']) || $isArabic($t['overview'])) {
                    if ($lang !== 'en' && empty($t['overview'])) {
                        // Already tried English above, use fallback
                        $t['overview'] = $msgs['guideFor'] . ($t['title'] ?? $fallbackTitle);
                    } else if ($isArabic($t['overview'])) {
                        $t['overview'] = $msgs['guideFor'] . ($t['title'] ?? $fallbackTitle);
                    }
                }
            }
            unset($t);

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM tutorials t
                        LEFT JOIN tutorial_languages tl ON t.id = tl.tutorial_id AND tl.language_code = ?
                        WHERE t.status = ?";
            $countParams = [$lang, $status];
            if ($categoryId) {
                $countSql .= " AND t.category_id = ?";
                $countParams[] = $categoryId;
            }
            if ($search) {
                $countSql .= " AND (tl.title LIKE ? OR tl.overview_text LIKE ?)";
                $searchTerm = "%{$search}%";
                $countParams[] = $searchTerm;
                $countParams[] = $searchTerm;
            }
            $countStmt = $this->conn->prepare($countSql);
            $countStmt->execute($countParams);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            return ApiResponse::success([
                'tutorials' => $tutorials,
                'total' => (int)$total,
                'limit' => $limit,
                'offset' => $offset
            ]);
        } catch (Exception $e) {
            error_log("getTutorials error: " . $e->getMessage());
            return ApiResponse::error('Failed to fetch tutorials: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get single tutorial by ID
     */
    private function getTutorial($id, $lang = 'en') {
        try {
            if (!$id) {
                return ApiResponse::error('Tutorial ID required', 400);
            }

            // Get tutorial basic info
            $sql = "SELECT 
                        t.*,
                        tc.slug as category_slug
                    FROM tutorials t
                    LEFT JOIN tutorial_categories tc ON t.category_id = tc.id
                    WHERE t.id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id]);
            $tutorial = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tutorial) {
                return ApiResponse::error('Tutorial not found', 404);
            }

            // Get language-specific content (try requested language first, fallback to English)
            $langSql = "SELECT * FROM tutorial_languages WHERE tutorial_id = ? AND language_code = ?";
            $langStmt = $this->conn->prepare($langSql);
            $langStmt->execute([$id, $lang]);
            $langContent = $langStmt->fetch(PDO::FETCH_ASSOC);
            
            // If requested language not found, try English
            if (!$langContent || empty($langContent['title'])) {
                $langStmt->execute([$id, 'en']);
                $langContent = $langStmt->fetch(PDO::FETCH_ASSOC);
            }
            
            $slug = $tutorial['slug'] ?? '';
            
            // Translation strings for fallback messages
            $fallbackMessages = [
                'en' => [
                    'guideFor' => 'Guide for ',
                    'tutorial' => 'Tutorial',
                    'contentMessage' => '<p>This guide will help you get started. Content is available in English.</p>'
                ],
                'bn' => [
                    'guideFor' => 'নির্দেশিকা: ',
                    'tutorial' => 'টিউটোরিয়াল',
                    'contentMessage' => '<p>এই নির্দেশিকা আপনাকে শুরু করতে সাহায্য করবে। বিষয়বস্তু ইংরেজিতে উপলব্ধ।</p>'
                ]
            ];
            $msgs = $fallbackMessages[$lang] ?? $fallbackMessages['en'];
            
            $fallbackTitle = ucwords(str_replace('-', ' ', $slug)) ?: $msgs['tutorial'];
            $isArabic = function ($s) { return preg_match('/[\x{0600}-\x{06FF}]/u', $s ?? ''); };
            $useFallback = !$langContent || empty($langContent['title']) || $isArabic($langContent['title'] ?? '');
            
            if ($useFallback) {
                $langContent = [
                    'title' => $fallbackTitle,
                    'overview' => $msgs['guideFor'] . $fallbackTitle,
                    'content' => $msgs['contentMessage'],
                    'overview_text' => '',
                    'content_text' => ''
                ];
            } else {
                if (empty($langContent['content'])) $langContent['content'] = '<p>' . ($langContent['overview'] ?? '') . '</p>';
                if ($isArabic($langContent['content'] ?? '')) $langContent['content'] = $msgs['contentMessage'];
            }

            // Get video versions
            $videoSql = "SELECT * FROM tutorial_video_versions WHERE tutorial_id = ? AND status = 'ready'";
            $videoStmt = $this->conn->prepare($videoSql);
            $videoStmt->execute([$id]);
            $videos = $videoStmt->fetchAll(PDO::FETCH_ASSOC);

            // Get user progress if logged in
            $progress = null;
            if (isset($_SESSION['user_id'])) {
                $progressSql = "SELECT * FROM user_tutorial_progress 
                               WHERE user_id = ? AND tutorial_id = ? AND language_code = ?";
                $progressStmt = $this->conn->prepare($progressSql);
                $progressStmt->execute([$_SESSION['user_id'], $id, $lang]);
                $progress = $progressStmt->fetch(PDO::FETCH_ASSOC);
            }

            // Increment view count
            $updateSql = "UPDATE tutorials SET views_count = views_count + 1 WHERE id = ?";
            $updateStmt = $this->conn->prepare($updateSql);
            $updateStmt->execute([$id]);

            $tutorial['content'] = $langContent;
            $tutorial['videos'] = $videos;
            $tutorial['progress'] = $progress;

            return ApiResponse::success($tutorial);
        } catch (Exception $e) {
            error_log("getTutorial error: " . $e->getMessage());
            return ApiResponse::error('Failed to fetch tutorial: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get tutorials by category
     */
    private function getTutorialsByCategory($categoryId, $lang = 'en') {
        try {
            $_GET['category_id'] = $categoryId;
            return $this->getTutorials($lang);
        } catch (Exception $e) {
            error_log("getTutorialsByCategory error: " . $e->getMessage());
            return ApiResponse::error('Failed to fetch tutorials', 500);
        }
    }

    /**
     * Get featured tutorials
     */
    private function getFeaturedTutorials($lang = 'en') {
        try {
            $_GET['status'] = 'published';
            $sql = "SELECT 
                        t.id,
                        t.slug,
                        t.featured,
                        tl.title,
                        tl.overview,
                        tc.slug as category_slug
                    FROM tutorials t
                    LEFT JOIN tutorial_languages tl ON t.id = tl.tutorial_id AND tl.language_code = ?
                    LEFT JOIN tutorial_categories tc ON t.category_id = tc.id
                    WHERE t.status = 'published' AND t.featured = 1
                    ORDER BY t.created_at DESC
                    LIMIT 10";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$lang]);
            $tutorials = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ApiResponse::success($tutorials);
        } catch (Exception $e) {
            error_log("getFeaturedTutorials error: " . $e->getMessage());
            return ApiResponse::error('Failed to fetch featured tutorials', 500);
        }
    }

    /**
     * Get popular tutorials
     */
    private function getPopularTutorials($lang = 'en') {
        try {
            $sql = "SELECT 
                        t.id,
                        t.slug,
                        t.views_count,
                        tl.title,
                        tl.overview,
                        tc.slug as category_slug
                    FROM tutorials t
                    LEFT JOIN tutorial_languages tl ON t.id = tl.tutorial_id AND tl.language_code = ?
                    LEFT JOIN tutorial_categories tc ON t.category_id = tc.id
                    WHERE t.status = 'published'
                    ORDER BY t.views_count DESC, t.likes_count DESC
                    LIMIT 10";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$lang]);
            $tutorials = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ApiResponse::success($tutorials);
        } catch (Exception $e) {
            error_log("getPopularTutorials error: " . $e->getMessage());
            return ApiResponse::error('Failed to fetch popular tutorials', 500);
        }
    }

    /**
     * Get recent tutorials
     */
    private function getRecentTutorials($lang = 'en') {
        try {
            $sql = "SELECT 
                        t.id,
                        t.slug,
                        t.created_at,
                        tl.title,
                        tl.overview,
                        tc.slug as category_slug
                    FROM tutorials t
                    LEFT JOIN tutorial_languages tl ON t.id = tl.tutorial_id AND tl.language_code = ?
                    LEFT JOIN tutorial_categories tc ON t.category_id = tc.id
                    WHERE t.status = 'published'
                    ORDER BY t.created_at DESC
                    LIMIT 10";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$lang]);
            $tutorials = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ApiResponse::success($tutorials);
        } catch (Exception $e) {
            error_log("getRecentTutorials error: " . $e->getMessage());
            return ApiResponse::error('Failed to fetch recent tutorials', 500);
        }
    }

    /**
     * Create new tutorial
     */
    private function createTutorial($data) {
        try {
            $this->conn->beginTransaction();

            // Insert tutorial
            $sql = "INSERT INTO tutorials (
                        category_id, slug, author_id, difficulty_level, 
                        estimated_time, status, featured
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $data['category_id'],
                $data['slug'],
                $_SESSION['user_id'] ?? null,
                $data['difficulty_level'] ?? 'beginner',
                $data['estimated_time'] ?? 5,
                $data['status'] ?? 'draft',
                $data['featured'] ?? 0
            ]);

            $tutorialId = $this->conn->lastInsertId();

            // Insert language content
            if (isset($data['languages'])) {
                foreach ($data['languages'] as $langCode => $content) {
                    $langSql = "INSERT INTO tutorial_languages (
                                    tutorial_id, language_code, title, overview, content,
                                    overview_text, content_text
                                ) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $langStmt = $this->conn->prepare($langSql);
                    $langStmt->execute([
                        $tutorialId,
                        $langCode,
                        $content['title'],
                        $content['overview'] ?? null,
                        $content['content'] ?? null,
                        strip_tags($content['overview'] ?? ''),
                        strip_tags($content['content'] ?? '')
                    ]);
                }
            }

            $this->conn->commit();
            return ApiResponse::success(['id' => $tutorialId], 'Tutorial created successfully');
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("createTutorial error: " . $e->getMessage());
            return ApiResponse::error('Failed to create tutorial: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update tutorial
     */
    private function updateTutorial($data) {
        try {
            if (!isset($data['id'])) {
                return ApiResponse::error('Tutorial ID required', 400);
            }

            $this->conn->beginTransaction();

            // Update tutorial
            $sql = "UPDATE tutorials SET 
                        category_id = ?, slug = ?, difficulty_level = ?,
                        estimated_time = ?, status = ?, featured = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $data['category_id'],
                $data['slug'],
                $data['difficulty_level'] ?? 'beginner',
                $data['estimated_time'] ?? 5,
                $data['status'] ?? 'draft',
                $data['featured'] ?? 0,
                $data['id']
            ]);

            // Update language content
            if (isset($data['languages'])) {
                foreach ($data['languages'] as $langCode => $content) {
                    $langSql = "INSERT INTO tutorial_languages (
                                    tutorial_id, language_code, title, overview, content,
                                    overview_text, content_text
                                ) VALUES (?, ?, ?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE
                                    title = VALUES(title),
                                    overview = VALUES(overview),
                                    content = VALUES(content),
                                    overview_text = VALUES(overview_text),
                                    content_text = VALUES(content_text),
                                    updated_at = CURRENT_TIMESTAMP";
                    $langStmt = $this->conn->prepare($langSql);
                    $langStmt->execute([
                        $data['id'],
                        $langCode,
                        $content['title'],
                        $content['overview'] ?? null,
                        $content['content'] ?? null,
                        strip_tags($content['overview'] ?? ''),
                        strip_tags($content['content'] ?? '')
                    ]);
                }
            }

            $this->conn->commit();
            return ApiResponse::success(null, 'Tutorial updated successfully');
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("updateTutorial error: " . $e->getMessage());
            return ApiResponse::error('Failed to update tutorial: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete tutorial
     */
    private function deleteTutorial($id) {
        try {
            $sql = "DELETE FROM tutorials WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id]);

            return ApiResponse::success(null, 'Tutorial deleted successfully');
        } catch (Exception $e) {
            error_log("deleteTutorial error: " . $e->getMessage());
            return ApiResponse::error('Failed to delete tutorial: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Check admin permission
     */
    private function hasAdminPermission() {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        // Check if user is admin (role_id = 1)
        // You can integrate with your permissions system here
        return isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1;
    }

    /**
     * Main request handler
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
                    echo $this->handleGet($action);
                    break;
                case 'POST':
                    echo $this->handlePost($action);
                    break;
                case 'PUT':
                    echo $this->handlePut($action);
                    break;
                case 'DELETE':
                    echo $this->handleDelete($action);
                    break;
                default:
                    echo ApiResponse::error('Method not allowed', 405);
            }
        } catch (Exception $e) {
            error_log("TutorialsAPI handleRequest error: " . $e->getMessage());
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            echo ApiResponse::error('Internal server error', 500);
        }
    }
}

// Initialize and handle request
$api = new TutorialsAPI();
$api->handleRequest();

<?php
/**
 * EN: Handles API endpoint/business logic in `api/help-center/categories.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/help-center/categories.php`.
 */
/**
 * Help Center - Categories API
 * Handles category-related operations
 */

ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

session_start();
require_once(__DIR__ . '/../core/Database.php');
require_once(__DIR__ . '/../core/ApiResponse.php');

class CategoriesAPI {
    private $db;
    private $conn;

    public function __construct() {
        try {
            $this->db = Database::getInstance();
            $this->conn = $this->db->getConnection();
        } catch (Exception $e) {
            error_log('CategoriesAPI constructor error: ' . $e->getMessage());
            $this->db = null;
            $this->conn = null;
        }
    }

    /**
     * Get all categories with translations
     */
    public function getCategories($lang = 'en') {
        try {
            if (!$this->conn) {
                return ApiResponse::error('Database connection failed', 500);
            }
            $this->createHelpCenterTablesIfMissing();
            $this->seedDefaultCategoriesIfEmpty();
            $this->ensureBengaliTranslations();
            $this->ensurePartnerAgenciesCategory();
            $sql = "SELECT 
                        c.id,
                        c.parent_id,
                        c.slug,
                        c.icon,
                        c.display_order,
                        c.status,
                        ct.name,
                        ct.description
                    FROM tutorial_categories c
                    LEFT JOIN tutorial_category_translations ct ON c.id = ct.category_id AND ct.language_code = ?
                    WHERE c.status = 'active'
                    ORDER BY c.display_order ASC, c.id ASC";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$lang]);
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fallback to English if translation not found
            $slugToTitle = function ($slug) {
                return ucwords(str_replace('-', ' ', $slug ?? ''));
            };
            $isArabic = function ($s) {
                return preg_match('/[\x{0600}-\x{06FF}]/u', $s ?? '');
            };
            
            // If Bengali requested but no translation found, get English as fallback
            foreach ($categories as &$category) {
                if (empty($category['name']) || ($lang === 'bn' && !$isArabic($category['name']) && !preg_match('/[\x{0980}-\x{09FF}]/u', $category['name'] ?? ''))) {
                    // Try to get English fallback
                    if ($lang === 'bn') {
                        $fallbackSql = "SELECT name, description FROM tutorial_category_translations WHERE category_id = ? AND language_code = 'en' LIMIT 1";
                        $fallbackStmt = $this->conn->prepare($fallbackSql);
                        $fallbackStmt->execute([$category['id']]);
                        $fallback = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
                        if ($fallback && !empty($fallback['name'])) {
                            $category['name'] = $fallback['name'];
                            $category['description'] = $fallback['description'] ?? '';
                        } else {
                            $category['name'] = $slugToTitle($category['slug'] ?? '');
                            // Translate fallback description based on language
                            $fallbackDescriptions = [
                                'en' => 'Help and tutorials for ',
                                'bn' => 'সাহায্য এবং টিউটোরিয়াল: '
                            ];
                            $descPrefix = $fallbackDescriptions[$lang] ?? $fallbackDescriptions['en'];
                            $category['description'] = $descPrefix . $slugToTitle($category['slug'] ?? '');
                        }
                    } else {
                        // For English, use slug fallback
                        if (empty($category['name']) || $isArabic($category['name'])) {
                            $category['name'] = $slugToTitle($category['slug'] ?? '');
                        }
                        if (empty($category['description']) || $isArabic($category['description'])) {
                            $category['description'] = 'Help and tutorials for ' . $slugToTitle($category['slug'] ?? '');
                        }
                    }
                }
                $countSql = "SELECT COUNT(*) as count FROM tutorials WHERE category_id = ? AND status = 'published'";
                $countStmt = $this->conn->prepare($countSql);
                $countStmt->execute([$category['id']]);
                $count = $countStmt->fetch(PDO::FETCH_ASSOC);
                $category['tutorial_count'] = (int)$count['count'];
            }


            // Build hierarchical structure
            $tree = $this->buildCategoryTree($categories);

            return ApiResponse::success($tree);
        } catch (Exception $e) {
            error_log("getCategories error: " . $e->getMessage());
            return ApiResponse::error('Failed to fetch categories: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create help-center tables if they do not exist.
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
            error_log("createHelpCenterTablesIfMissing (categories): " . $e->getMessage());
        }
    }

    /**
     * Seed default categories (ids 1–13) when table is empty so tutorials can be linked.
     */
    private function seedDefaultCategoriesIfEmpty() {
        try {
            $chk = $this->conn->query("SELECT COUNT(*) as n FROM tutorial_categories");
            if (!$chk || (int)$chk->fetch(PDO::FETCH_ASSOC)['n'] > 0) return;
            $cats = [
                [1, null, 'getting-started', 'fa-rocket', 1], [2, null, 'dashboard', 'fa-tachometer-alt', 2],
                [3, null, 'user-management', 'fa-users-cog', 3], [4, null, 'contracts-recruitment', 'fa-file-contract', 4],
                [5, null, 'client-management', 'fa-building', 5], [6, null, 'worker-management', 'fa-hard-hat', 6],
                [7, null, 'finance-billing', 'fa-dollar-sign', 7], [8, null, 'reports-analytics', 'fa-chart-bar', 8],
                [9, null, 'notifications-automation', 'fa-bell', 9], [10, null, 'troubleshooting-faq', 'fa-question-circle', 10],
                [11, null, 'best-practices', 'fa-star', 11], [12, null, 'compliance-legal', 'fa-gavel', 12],
                [13, null, 'partner-agencies', 'fa-globe', 13],
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
                13 => ['🌍 Partner Agencies', 'Overseas partner offices, workers sent abroad, and deployment tracking (View on each agency row).'],
            ];
            $insCat = $this->conn->prepare("INSERT INTO tutorial_categories (id, parent_id, slug, icon, display_order, status) VALUES (?, ?, ?, ?, ?, 'active')");
            foreach ($cats as $r) $insCat->execute($r);
            
            // Insert English translations
            $insTr = $this->conn->prepare("INSERT INTO tutorial_category_translations (category_id, language_code, name, description) VALUES (?, 'en', ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description)");
            foreach ($names as $id => $n) $insTr->execute([$id, $n[0], $n[1]]);
            
            // Insert Bengali translations
            $bnNames = [
                1 => ['শুরু করা', 'সিস্টেমের পরিচিতি এবং এটি কীভাবে কাজ করে'],
                2 => ['ড্যাশবোর্ড', 'ড্যাশবোর্ড নেভিগেশন এবং বৈশিষ্ট্য নির্দেশিকা'],
                3 => ['ব্যবহারকারী ব্যবস্থাপনা ও অনুমতি', 'ব্যবহারকারী, ভূমিকা এবং অনুমতি পরিচালনা'],
                4 => ['চুক্তি ও নিয়োগ', 'চুক্তি ব্যবস্থাপনা এবং নিয়োগ প্রক্রিয়া'],
                5 => ['ক্লায়েন্ট ব্যবস্থাপনা', 'ক্লায়েন্ট এবং ক্লায়েন্ট সম্পর্ক পরিচালনা'],
                6 => ['কর্মী ব্যবস্থাপনা', 'কর্মী প্রোফাইল, নথি এবং ব্যবস্থাপনা'],
                7 => ['অর্থ ও বিলিং', 'আর্থিক ব্যবস্থাপনা এবং বিলিং অপারেশন'],
                8 => ['রিপোর্ট ও বিশ্লেষণ', 'রিপোর্ট এবং বিশ্লেষণ তৈরি এবং দেখানো'],
                9 => ['বিজ্ঞপ্তি ও স্বয়ংক্রিয়করণ', 'সিস্টেম বিজ্ঞপ্তি এবং স্বয়ংক্রিয়করণ বৈশিষ্ট্য'],
                10 => ['সমস্যা সমাধান ও FAQ', 'সাধারণ সমস্যা এবং প্রায়শই জিজ্ঞাসিত প্রশ্ন'],
                11 => ['সেরা অনুশীলন', 'প্রস্তাবিত অনুশীলন এবং ওয়ার্কফ্লো'],
                12 => ['সম্মতি ও আইনি', 'সম্মতি নির্দেশিকা এবং আইনি প্রয়োজনীয়তা'],
                13 => ['🌍 পার্টনার এজেন্সি', 'বিদেশী পার্টনার অফিস, প্রেরিত কর্মী এবং মোতায়েন ট্র্যাকিং।'],
            ];
            $insTrBn = $this->conn->prepare("INSERT INTO tutorial_category_translations (category_id, language_code, name, description) VALUES (?, 'bn', ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description)");
            foreach ($bnNames as $id => $n) $insTrBn->execute([$id, $n[0], $n[1]]);
        } catch (Exception $e) {
            error_log("seedDefaultCategoriesIfEmpty: " . $e->getMessage());
        }
    }

    /**
     * Ensure Bengali translations exist for all categories
     */
    private function ensureBengaliTranslations() {
        try {
            $bnTranslations = [
                1 => ['শুরু করা', 'সিস্টেমের পরিচিতি এবং এটি কীভাবে কাজ করে'],
                2 => ['ড্যাশবোর্ড', 'ড্যাশবোর্ড নেভিগেশন এবং বৈশিষ্ট্য নির্দেশিকা'],
                3 => ['ব্যবহারকারী ব্যবস্থাপনা ও অনুমতি', 'ব্যবহারকারী, ভূমিকা এবং অনুমতি পরিচালনা'],
                4 => ['চুক্তি ও নিয়োগ', 'চুক্তি ব্যবস্থাপনা এবং নিয়োগ প্রক্রিয়া'],
                5 => ['ক্লায়েন্ট ব্যবস্থাপনা', 'ক্লায়েন্ট এবং ক্লায়েন্ট সম্পর্ক পরিচালনা'],
                6 => ['কর্মী ব্যবস্থাপনা', 'কর্মী প্রোফাইল, নথি এবং ব্যবস্থাপনা'],
                7 => ['অর্থ ও বিলিং', 'আর্থিক ব্যবস্থাপনা এবং বিলিং অপারেশন'],
                8 => ['রিপোর্ট ও বিশ্লেষণ', 'রিপোর্ট এবং বিশ্লেষণ তৈরি এবং দেখানো'],
                9 => ['বিজ্ঞপ্তি ও স্বয়ংক্রিয়করণ', 'সিস্টেম বিজ্ঞপ্তি এবং স্বয়ংক্রিয়করণ বৈশিষ্ট্য'],
                10 => ['সমস্যা সমাধান ও FAQ', 'সাধারণ সমস্যা এবং প্রায়শই জিজ্ঞাসিত প্রশ্ন'],
                11 => ['সেরা অনুশীলন', 'প্রস্তাবিত অনুশীলন এবং ওয়ার্কফ্লো'],
                12 => ['সম্মতি ও আইনি', 'সম্মতি নির্দেশিকা এবং আইনি প্রয়োজনীয়তা'],
                13 => ['🌍 পার্টনার এজেন্সি', 'বিদেশী পার্টনার অফিস, প্রেরিত কর্মী এবং মোতায়েন ট্র্যাকিং।'],
            ];
            
            $stmt = $this->conn->prepare("INSERT INTO tutorial_category_translations (category_id, language_code, name, description) VALUES (?, 'bn', ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description)");
            
            foreach ($bnTranslations as $id => $translation) {
                $stmt->execute([$id, $translation[0], $translation[1]]);
            }
        } catch (Exception $e) {
            error_log("ensureBengaliTranslations error: " . $e->getMessage());
        }
    }

    /**
     * Ensure 🌍 Partner Agencies category exists (id 13 when possible) for Help Center + chat deep links.
     */
    private function ensurePartnerAgenciesCategory(): void
    {
        try {
            if (!$this->conn) {
                return;
            }
            $slug = 'partner-agencies';
            $chk = $this->conn->prepare('SELECT id FROM tutorial_categories WHERE slug = ? LIMIT 1');
            $chk->execute([$slug]);
            $existingId = (int) $chk->fetchColumn();
            if ($existingId > 0) {
                $this->upsertPartnerAgencyCategoryTranslations($existingId);
                return;
            }
            try {
                $ins = $this->conn->prepare(
                    "INSERT INTO tutorial_categories (id, parent_id, slug, icon, display_order, status)
                     VALUES (13, NULL, ?, 'fa-globe', 13, 'active')"
                );
                $ins->execute([$slug]);
                $this->upsertPartnerAgencyCategoryTranslations(13);
            } catch (Throwable $e) {
                $ins2 = $this->conn->prepare(
                    "INSERT INTO tutorial_categories (parent_id, slug, icon, display_order, status)
                     VALUES (NULL, ?, 'fa-globe', 13, 'active')"
                );
                $ins2->execute([$slug]);
                $cidStmt = $this->conn->prepare('SELECT id FROM tutorial_categories WHERE slug = ? LIMIT 1');
                $cidStmt->execute([$slug]);
                $newId = (int) $cidStmt->fetchColumn();
                if ($newId > 0) {
                    $this->upsertPartnerAgencyCategoryTranslations($newId);
                }
            }
        } catch (Throwable $e) {
            error_log('ensurePartnerAgenciesCategory: ' . $e->getMessage());
        }
    }

    private function upsertPartnerAgencyCategoryTranslations(int $categoryId): void
    {
        if ($categoryId <= 0 || !$this->conn) {
            return;
        }
        $stmt = $this->conn->prepare(
            "INSERT INTO tutorial_category_translations (category_id, language_code, name, description)
             VALUES (?, 'en', ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)"
        );
        $stmt->execute([
            $categoryId,
            '🌍 Partner Agencies',
            'Overseas partner offices, workers sent abroad, and deployment tracking (View on each agency row).',
        ]);
        $stmtBn = $this->conn->prepare(
            "INSERT INTO tutorial_category_translations (category_id, language_code, name, description)
             VALUES (?, 'bn', ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)"
        );
        $stmtBn->execute([
            $categoryId,
            '🌍 পার্টনার এজেন্সি',
            'বিদেশী পার্টনার অফিস, প্রেরিত কর্মী এবং মোতায়েন ট্র্যাকিং।',
        ]);
    }

    /**
     * Build hierarchical category tree
     */
    private function buildCategoryTree($categories) {
        $tree = [];
        $indexed = [];

        // Index all categories
        foreach ($categories as $category) {
            $indexed[$category['id']] = $category;
            $indexed[$category['id']]['children'] = [];
        }

        // Build tree
        foreach ($indexed as $id => $category) {
            if ($category['parent_id'] === null) {
                $tree[] = &$indexed[$id];
            } else {
                if (isset($indexed[$category['parent_id']])) {
                    $indexed[$category['parent_id']]['children'][] = &$indexed[$id];
                }
            }
        }

        return $tree;
    }

    /**
     * Handle request
     */
    public function handleRequest() {
        try {
            // Get language from query parameter, session, or default to 'en'
            $lang = $_GET['lang'] ?? $_SESSION['help_language'] ?? 'en';
            
            // Validate language code
            if (!in_array($lang, ['en', 'bn'])) {
                $lang = 'en';
            }
            
            // Clean output buffer before sending JSON
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            echo $this->getCategories($lang);
        } catch (Exception $e) {
            error_log("CategoriesAPI handleRequest error: " . $e->getMessage());
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            echo ApiResponse::error('Internal server error', 500);
        }
    }
}

$api = new CategoriesAPI();
$api->handleRequest();

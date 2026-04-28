<?php
/**
 * EN: Handles API endpoint/business logic in `api/help-center/test-categories.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/help-center/test-categories.php`.
 */
/**
 * Test script to check category translations
 */

header('Content-Type: application/json');

session_start();
require_once(__DIR__ . '/../core/Database.php');

$response = [
    'error' => null,
    'translations_count' => 0,
    'sample_category' => null,
    'sample_translation' => null
];

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check translations count
    $stmt = $conn->query("SELECT COUNT(*) as count FROM tutorial_category_translations");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['translations_count'] = $result['count'];
    
    // Get a sample category with translation
    $sql = "SELECT 
                c.id,
                c.slug,
                ct.name,
                ct.description,
                ct.language_code
            FROM tutorial_categories c
            LEFT JOIN tutorial_category_translations ct ON c.id = ct.category_id AND ct.language_code = 'en'
            WHERE c.id = 1
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['sample_category'] = $category;
    
    // Get all translations for category 1
    $stmt = $conn->query("SELECT * FROM tutorial_category_translations WHERE category_id = 1");
    $translations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $response['sample_translation'] = $translations;
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);

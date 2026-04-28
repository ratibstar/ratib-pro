<?php
/**
 * EN: Handles API endpoint/business logic in `api/help-center/seed-bengali-translations.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/help-center/seed-bengali-translations.php`.
 */
/**
 * Seed Bengali translations for categories
 * Run this once to add Bengali translations to existing categories
 */

require_once(__DIR__ . '/../core/Database.php');

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Bengali translations
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
    ];
    
    $stmt = $conn->prepare("INSERT INTO tutorial_category_translations (category_id, language_code, name, description) VALUES (?, 'bn', ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description)");
    
    foreach ($bnTranslations as $id => $translation) {
        $stmt->execute([$id, $translation[0], $translation[1]]);
        echo "Inserted Bengali translation for category ID $id: {$translation[0]}\n";
    }
    
    echo "\nAll Bengali translations seeded successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

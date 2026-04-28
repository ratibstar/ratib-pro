<?php
/**
 * EN: Handles API endpoint/business logic in `api/settings/reset-tables.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/settings/reset-tables.php`.
 */
require_once __DIR__ . '/../../config/database.php';

try {
    // Drop existing settings table
    $conn->query("DROP TABLE IF EXISTS settings");
    echo "Old settings table dropped<br>";

    // Create new settings table
    $sql = "CREATE TABLE settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        category VARCHAR(50) NOT NULL,
        setting_key VARCHAR(50) NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_setting (category, setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql)) {
        echo "New settings table created successfully<br>";
    } else {
        throw new Exception("Failed to create table: " . $conn->error);
    }

    // Insert default settings
    $defaultSettings = [
        ['general', 'language', 'en'],
        ['general', 'timezone', 'GMT+3'],
        ['general', 'dateFormat', 'dd/mm/yyyy'],
        ['general', 'currency', 'SAR']
    ];

    $stmt = $conn->prepare("INSERT INTO settings (category, setting_key, setting_value) VALUES (?, ?, ?)");
    
    foreach ($defaultSettings as $setting) {
        $stmt->bind_param("sss", $setting[0], $setting[1], $setting[2]);
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert setting: " . $stmt->error);
        }
    }
    echo "Default settings inserted successfully<br>";

    // Verify settings
    $result = $conn->query("SELECT * FROM settings");
    echo "<br>Current settings:<br>";
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['category']}.{$row['setting_key']} = {$row['setting_value']}<br>";
    }

} catch (Exception $e) {
    die("Setup failed: " . $e->getMessage());
} 
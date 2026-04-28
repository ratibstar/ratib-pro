<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/setup-followup-messages.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/setup-followup-messages.php`.
 */
/**
 * Setup Follow-ups & Messages Tables
 * Creates the required database tables for follow-ups and messages functionality
 */

require_once '../../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $results = [];
    $errors = [];
    $warnings = [];
    
    // Check if users table exists (required for foreign keys)
    $usersTableCheck = $conn->query("SHOW TABLES LIKE 'users'");
    if ($usersTableCheck->num_rows === 0) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Users table not found. Please ensure the database is properly initialized.'
        ]);
        exit;
    }
    
    // Check if tables already exist
    $followupsCheck = $conn->query("SHOW TABLES LIKE 'accounting_followups'");
    $messagesCheck = $conn->query("SHOW TABLES LIKE 'accounting_messages'");
    $readsCheck = $conn->query("SHOW TABLES LIKE 'accounting_message_reads'");
    
    if ($followupsCheck->num_rows > 0) {
        $warnings[] = "Follow-ups table already exists";
    }
    if ($messagesCheck->num_rows > 0) {
        $warnings[] = "Messages table already exists";
    }
    if ($readsCheck->num_rows > 0) {
        $warnings[] = "Message reads table already exists";
    }
    
    // Create accounting_followups table
    $createFollowupsTable = "
        CREATE TABLE IF NOT EXISTS `accounting_followups` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(255) NOT NULL,
            `description` text DEFAULT NULL,
            `related_type` varchar(50) NOT NULL,
            `related_id` int(11) DEFAULT 0,
            `due_date` date DEFAULT NULL,
            `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
            `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
            `assigned_to` int(11) DEFAULT NULL,
            `created_by` int(11) NOT NULL,
            `reminder_date` datetime DEFAULT NULL,
            `notes` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `completed_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_related` (`related_type`, `related_id`),
            KEY `idx_status` (`status`),
            KEY `idx_priority` (`priority`),
            KEY `idx_due_date` (`due_date`),
            KEY `idx_assigned_to` (`assigned_to`),
            KEY `idx_created_by` (`created_by`),
            CONSTRAINT `fk_followups_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
            CONSTRAINT `fk_followups_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    if ($conn->query($createFollowupsTable)) {
        if ($followupsCheck->num_rows === 0) {
            $results[] = "Follow-ups table created successfully";
        } else {
            $results[] = "Follow-ups table verified (already exists)";
        }
    } else {
        $errorMsg = $conn->error;
        // Check if error is due to existing table (shouldn't happen with IF NOT EXISTS, but just in case)
        if (strpos($errorMsg, 'already exists') === false) {
            $errors[] = "Error creating follow-ups table: " . $errorMsg;
        } else {
            $results[] = "Follow-ups table already exists";
        }
    }
    
    // Create accounting_messages table
    $createMessagesTable = "
        CREATE TABLE IF NOT EXISTS `accounting_messages` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `type` enum('info','warning','error','success','alert') DEFAULT 'info',
            `category` varchar(50) DEFAULT 'system_notification',
            `title` varchar(255) NOT NULL,
            `message` text NOT NULL,
            `related_type` varchar(50) DEFAULT NULL,
            `related_id` int(11) DEFAULT NULL,
            `user_id` int(11) DEFAULT NULL,
            `is_important` tinyint(1) DEFAULT 0,
            `action_url` varchar(500) DEFAULT NULL,
            `action_text` varchar(100) DEFAULT NULL,
            `expires_at` datetime DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_user_id` (`user_id`),
            KEY `idx_type` (`type`),
            KEY `idx_category` (`category`),
            KEY `idx_related` (`related_type`, `related_id`),
            KEY `idx_important` (`is_important`),
            KEY `idx_expires_at` (`expires_at`),
            CONSTRAINT `fk_messages_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    if ($conn->query($createMessagesTable)) {
        if ($messagesCheck->num_rows === 0) {
            $results[] = "Messages table created successfully";
        } else {
            $results[] = "Messages table verified (already exists)";
        }
    } else {
        $errorMsg = $conn->error;
        if (strpos($errorMsg, 'already exists') === false) {
            $errors[] = "Error creating messages table: " . $errorMsg;
        } else {
            $results[] = "Messages table already exists";
        }
    }
    
    // Create accounting_message_reads table
    $createMessageReadsTable = "
        CREATE TABLE IF NOT EXISTS `accounting_message_reads` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `message_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            `read_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_message_user` (`message_id`, `user_id`),
            KEY `idx_user_id` (`user_id`),
            KEY `idx_read_at` (`read_at`),
            CONSTRAINT `fk_message_reads_message_id` FOREIGN KEY (`message_id`) REFERENCES `accounting_messages` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_message_reads_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    if ($conn->query($createMessageReadsTable)) {
        if ($readsCheck->num_rows === 0) {
            $results[] = "Message reads table created successfully";
        } else {
            $results[] = "Message reads table verified (already exists)";
        }
    } else {
        $errorMsg = $conn->error;
        if (strpos($errorMsg, 'already exists') === false) {
            $errors[] = "Error creating message reads table: " . $errorMsg;
        } else {
            $results[] = "Message reads table already exists";
        }
    }
    
    // Verify all tables exist
    $finalCheck = [
        'accounting_followups' => $conn->query("SHOW TABLES LIKE 'accounting_followups'")->num_rows > 0,
        'accounting_messages' => $conn->query("SHOW TABLES LIKE 'accounting_messages'")->num_rows > 0,
        'accounting_message_reads' => $conn->query("SHOW TABLES LIKE 'accounting_message_reads'")->num_rows > 0
    ];
    
    $allTablesExist = array_reduce($finalCheck, function($carry, $exists) {
        return $carry && $exists;
    }, true);
    
    if (count($errors) > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Some errors occurred during setup',
            'results' => $results,
            'errors' => $errors,
            'warnings' => $warnings,
            'tables_exist' => $finalCheck
        ]);
    } else if ($allTablesExist) {
        echo json_encode([
            'success' => true,
            'message' => count($warnings) > 0 ? 'All tables verified successfully' : 'All tables created successfully',
            'results' => $results,
            'warnings' => $warnings,
            'tables_exist' => $finalCheck
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Setup completed but some tables are missing',
            'results' => $results,
            'errors' => ['Not all tables were created successfully'],
            'tables_exist' => $finalCheck
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error during setup: ' . $e->getMessage()
    ]);
}


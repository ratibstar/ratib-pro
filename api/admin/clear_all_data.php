<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/clear_all_data.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/clear_all_data.php`.
 */
/**
 * Clear All Data - Reset the program to a fresh clean state.
 * Clears all business data: Agents, SubAgents, Workers, Cases, Accounting, HR,
 * Reports, Contact, Notifications, Help & Learning Center progress, System Settings history.
 * KEEPS: users, roles, permissions (so you can still log in).
 *
 * GET ?action=clear_all_data&confirm=1 to execute.
 * GET ?action=clear_all_data&dry_run=1 to preview (no deletes).
 * Requires admin (role_id = 1).
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if (!isset($_SESSION['role_id']) || (int)$_SESSION['role_id'] !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin only.']);
    exit;
}

$action = $_GET['action'] ?? '';
if ($action !== 'clear_all_data') {
    echo json_encode(['success' => false, 'message' => 'Invalid action. Use action=clear_all_data']);
    exit;
}

$confirm = isset($_GET['confirm']) && $_GET['confirm'] === '1';
$dryRun = isset($_GET['dry_run']) && $_GET['dry_run'] === '1';

if (!$confirm && !$dryRun) {
    echo json_encode([
        'success' => false,
        'message' => 'Add confirm=1 to execute, or dry_run=1 to preview.',
        'usage' => '?action=clear_all_data&confirm=1'
    ]);
    exit;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection not available']);
    exit;
}

$results = [];
$run = function ($table) use ($conn, $dryRun, &$results) {
    $table = $conn->real_escape_string($table);
    $chk = @$conn->query("SHOW TABLES LIKE '{$table}'");
    if (!$chk || $chk->num_rows === 0) {
        $results[$table] = ['exists' => false, 'deleted' => 0];
        return;
    }
    $cnt = @$conn->query("SELECT COUNT(*) as c FROM `{$table}`");
    $n = $cnt ? (int)$cnt->fetch_assoc()['c'] : 0;
    if (!$dryRun && $n > 0) {
        @$conn->query("DELETE FROM `{$table}`");
    }
    $results[$table] = ['deleted' => $n];
};

// Disable foreign key checks so we can delete in any order
if (!$dryRun) {
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
}

try {
    // ---- Contact & Notifications ----
    $run('contact_notifications');
    $run('contact_communications');
    $run('contacts');

    // ---- Help & Learning Center (child tables first) ----
    $run('tutorial_ratings');
    $run('tutorial_search_index');
    $run('user_tutorial_progress');
    $run('tutorial_video_versions');
    $run('tutorial_languages');
    $run('tutorial_tags');
    $run('tutorials');
    $run('tutorial_category_translations');
    $run('tutorial_categories');

    // ---- Workers ----
    $run('worker_documents');
    $run('workers');

    // ---- Cases ----
    $run('case_activities');
    $run('cases');

    // ---- Agents & SubAgents ----
    $run('subagents');
    $run('agents');

    // ---- Accounting (children first) ----
    $run('journal_entry_lines');
    $run('journal_entries');
    $run('entity_transactions');
    $run('transaction_lines');
    $run('entry_approval');
    $run('entity_totals');
    $run('financial_transactions');
    foreach (['receipt_payment_vouchers', 'payment_receipts', 'payment_payments', 'accounts_receivable', 'accounts_payable', 'accounting_bank_transactions', 'accounting_messages', 'accounting_followups'] as $t) {
        $run($t);
    }

    // ---- HR ----
    $run('advances');
    $run('attendance');
    $run('salaries');
    $run('cars');
    $run('employees');
    $run('hr_employees');
    $run('hr_settings');

    // ---- Reports / History / Logs ----
    $run('activity_logs');
    $run('system_events');
    $run('global_history');
    $run('system_settings_history');

    // ---- In-app notifications ----
    $run('notifications');
} finally {
    if (!$dryRun) {
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    }
}

$total = 0;
foreach ($results as $r) {
    $total += (int)($r['deleted'] ?? 0);
}

echo json_encode([
    'success' => true,
    'dry_run' => $dryRun,
    'message' => $dryRun ? 'Preview only. No data was deleted.' : 'All data cleared. Program is fresh and ready.',
    'results' => $results,
    'total_affected' => $total
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

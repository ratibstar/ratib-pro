<?php
/**
 * EN: Handles API endpoint/business logic in `api/subagents/delete.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/subagents/delete.php`.
 */
// Disable error display to prevent HTML output
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once __DIR__ . '/../core/api-permission-helper.php';
enforceApiPermission('subagents', 'delete');

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ApiResponse.php';

try {
    if (!class_exists('Database') || !class_exists('ApiResponse')) {
        throw new Exception('Required classes are missing');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['ids']) || !is_array($input['ids']) || empty($input['ids'])) {
        echo ApiResponse::error('IDs array is required', 400);
        exit;
    }

    // Sanitize IDs
    $ids = array_values(array_filter(array_map(function ($id) {
        return filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    }, $input['ids']), function ($id) {
        return $id !== false;
    }));

    if (empty($ids)) {
        echo ApiResponse::error('No valid IDs provided', 400);
        exit;
    }

    $db = Database::getInstance();
    $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));

    // Check for workers associated with these subagents
    $checkWorkersSql = "SELECT DISTINCT subagent_id FROM workers WHERE subagent_id IN ($idPlaceholders) AND status != 'deleted'";
    $workersWithSubagents = $db->query($checkWorkersSql, $ids);
    $workersWithSubagents = array_column($workersWithSubagents, 'subagent_id');
    
    if (!empty($workersWithSubagents)) {
        // First, unlink workers from these subagents (set subagent_id to NULL)
        $unlinkWorkersSql = "UPDATE workers SET subagent_id = NULL, updated_at = CURRENT_TIMESTAMP WHERE subagent_id IN ($idPlaceholders)";
        $db->execute($unlinkWorkersSql, $ids);
    }

    // Get deleted data for history (before deletion)
    $conn = $db->getConnection();
    $getDeletedSql = "SELECT * FROM subagents WHERE id IN ($idPlaceholders)";
    $getDeletedStmt = $conn->prepare($getDeletedSql);
    $getDeletedStmt->execute($ids);
    $deletedSubagents = $getDeletedStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Soft delete first
    $softDeleteSql = "UPDATE subagents SET status = 'deleted', updated_at = CURRENT_TIMESTAMP WHERE id IN ($idPlaceholders)";
    $softStmt = $db->execute($softDeleteSql, $ids);
    $affectedRows = $softStmt ? $softStmt->rowCount() : 0;

    // Hard delete to ensure removal from result sets
    $hardDeleteSql = "DELETE FROM subagents WHERE id IN ($idPlaceholders)";
    $hardStmt = $db->execute($hardDeleteSql, $ids);
    if ($hardStmt) {
        $affectedRows = max($affectedRows, $hardStmt->rowCount());
    }
    
    // Log history for each deleted subagent
    if (file_exists(__DIR__ . '/../core/global-history-helper.php')) {
        require_once __DIR__ . '/../core/global-history-helper.php';
        foreach ($deletedSubagents as $deletedSubagent) {
            @logGlobalHistory('subagents', $deletedSubagent['id'], 'delete', 'subagents', $deletedSubagent, null);
        }
    }

    $message = "Successfully deleted $affectedRows subagent(s)";
    if (!empty($workersWithSubagents)) {
        $message .= ". " . count($workersWithSubagents) . " worker(s) were unlinked from these subagents.";
    }

    echo ApiResponse::success([
        'affected_rows' => $affectedRows,
        'deleted_ids' => $ids,
        'workers_unlinked' => !empty($workersWithSubagents) ? count($workersWithSubagents) : 0
    ], $message);
} catch (Exception $e) {
    error_log("Delete subagents error: " . $e->getMessage());
    echo ApiResponse::error('Internal server error: ' . $e->getMessage(), 500);
} catch (Error $e) {
    error_log("Delete subagents fatal error: " . $e->getMessage());
    echo ApiResponse::error('Fatal error: ' . $e->getMessage(), 500);
}
?>


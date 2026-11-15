<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/hr/document-storage.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    http_response_code(404);
    exit;
}
$stmt = $pdo->prepare('SELECT storage_path, file_label FROM hr_documents WHERE id = ? AND company_user_id = ? LIMIT 1');
$stmt->execute([$id, $hrCompanyId]);
$row = $stmt->fetch();
if (!$row || ($row['storage_path'] ?? '') === '') {
    http_response_code(404);
    exit;
}
$full = hr_document_resolve_full_path((string) $row['storage_path']);
if ($full === null) {
    http_response_code(404);
    exit;
}
$mime = match (strtolower(pathinfo($full, PATHINFO_EXTENSION))) {
    'pdf' => 'application/pdf',
    'png' => 'image/png',
    'jpg', 'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    default => 'application/octet-stream',
};
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($full));
$label = (string) ($row['file_label'] ?? 'document');
$safe = preg_replace('/[^\w.\-]+/u', '_', $label) ?: 'document';
header('Content-Disposition: inline; filename="' . str_replace('"', '', $safe) . '"');
readfile($full);
exit;
<?php
/**
 * Upload worker profile photo and update workers.personal_photo_url.
 *
 * Hosting note:
 * Some environments disallow mkdir/write under uploads/. When filesystem upload fails,
 * we fall back to storing a data URL in personal_photo_url (column extended to MEDIUMTEXT when needed).
 */
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../utils/response.php';

function ratib_ensure_personal_photo_column_mediumtext(PDO $conn): void
{
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM workers LIKE 'personal_photo_url'");
        $col = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        if (!$col || empty($col['Type'])) {
            return;
        }
        $type = strtolower((string) $col['Type']);
        if (strpos($type, 'varchar') !== false || strpos($type, 'char') !== false || strpos($type, 'text') === false) {
            $conn->exec("ALTER TABLE workers MODIFY COLUMN personal_photo_url MEDIUMTEXT NULL");
        }
    } catch (Throwable $e) {
        // Best-effort only; DB user may lack ALTER permission.
    }
}

try {
    if (empty($_POST['id']) || empty($_FILES['file'])) {
        throw new Exception('Worker ID and file are required');
    }

    $workerId = (int) $_POST['id'];
    $file = $_FILES['file'];

    if ($workerId <= 0) {
        throw new Exception('Invalid worker ID');
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed with error code: ' . (int) $file['error']);
    }

    // Keep DB-safe default cap (can be increased if hosting allows).
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new Exception('File too large. Max 2MB for profile photo');
    }

    $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $mime = function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : ($file['type'] ?? '');
    if (!in_array($mime, $allowedMimeTypes, true)) {
        throw new Exception('Invalid image type. Allowed: JPG, PNG, WEBP');
    }

    $ext = strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if ($ext === '') {
        $ext = 'jpg';
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Preferred: store as file under existing worker documents folders (no mkdir).
    $candidateDirs = [
        __DIR__ . '/../../uploads/workers/' . $workerId . '/documents/identity/',
        __DIR__ . '/../../uploads/workers/' . $workerId . '/documents/passport/',
        __DIR__ . '/../../uploads/workers/' . $workerId . '/documents/',
    ];

    $targetDir = '';
    foreach ($candidateDirs as $dir) {
        if (is_dir($dir) && is_writable($dir)) {
            $targetDir = $dir;
            break;
        }
    }

    $storedValue = '';
    $storageMode = 'file';

    if ($targetDir !== '') {
        $fileName = 'photo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath = $targetDir . $fileName;
        $moved = move_uploaded_file($file['tmp_name'], $destPath);
        if (!$moved) {
            $moved = @copy($file['tmp_name'], $destPath);
            if ($moved) {
                @unlink($file['tmp_name']);
            }
        }
        if ($moved) {
            if (strpos($targetDir, '/documents/identity/') !== false || strpos($targetDir, '\\documents\\identity\\') !== false) {
                $storedValue = '/uploads/workers/' . $workerId . '/documents/identity/' . $fileName;
            } elseif (strpos($targetDir, '/documents/passport/') !== false || strpos($targetDir, '\\documents\\passport\\') !== false) {
                $storedValue = '/uploads/workers/' . $workerId . '/documents/passport/' . $fileName;
            } else {
                $storedValue = '/uploads/workers/' . $workerId . '/documents/' . $fileName;
            }
        }
    }

    if ($storedValue === '') {
        // Fallback: inline data URL (works even when uploads are not writable).
        $storageMode = 'inline';
        ratib_ensure_personal_photo_column_mediumtext($conn);

        $raw = @file_get_contents($file['tmp_name']);
        if ($raw === false || $raw === '') {
            throw new Exception('Could not read uploaded image');
        }
        $b64 = base64_encode($raw);
        $mimeNorm = $mime === 'image/jpg' ? 'image/jpeg' : $mime;
        $storedValue = 'data:' . $mimeNorm . ';base64,' . $b64;
    }

    $stmt = $conn->prepare('UPDATE workers SET personal_photo_url = ? WHERE id = ?');
    $stmt->execute([$storedValue, $workerId]);
    if ($stmt->rowCount() === 0) {
        throw new Exception('Worker not found or photo not updated');
    }

    sendResponse([
        'success' => true,
        'message' => 'Profile photo uploaded',
        'data' => [
            'path' => $storedValue,
            'storage' => $storageMode,
        ],
    ]);
} catch (Exception $e) {
    sendResponse([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}

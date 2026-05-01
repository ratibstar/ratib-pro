<?php
/**
 * Upload worker profile photo and update personal_photo_url.
 */
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../utils/response.php';

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

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('File too large. Max 5MB');
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

    // Use existing worker document folders to avoid mkdir permission issues on hosting.
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
    if ($targetDir === '') {
        throw new Exception('No writable worker upload directory found. Upload identity/passport once, then retry photo.');
    }

    $fileName = 'photo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destPath = $targetDir . $fileName;
    $moved = move_uploaded_file($file['tmp_name'], $destPath);
    if (!$moved) {
        $moved = @copy($file['tmp_name'], $destPath);
        if ($moved) {
            @unlink($file['tmp_name']);
        }
    }
    if (!$moved) {
        throw new Exception('Failed to store uploaded profile photo');
    }

    // Build public URL matching chosen existing directory.
    if (strpos($targetDir, '/documents/identity/') !== false || strpos($targetDir, '\\documents\\identity\\') !== false) {
        $publicPath = '/uploads/workers/' . $workerId . '/documents/identity/' . $fileName;
    } elseif (strpos($targetDir, '/documents/passport/') !== false || strpos($targetDir, '\\documents\\passport\\') !== false) {
        $publicPath = '/uploads/workers/' . $workerId . '/documents/passport/' . $fileName;
    } else {
        $publicPath = '/uploads/workers/' . $workerId . '/documents/' . $fileName;
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();
    $stmt = $conn->prepare('UPDATE workers SET personal_photo_url = ? WHERE id = ?');
    $stmt->execute([$publicPath, $workerId]);
    if ($stmt->rowCount() === 0) {
        @unlink($destPath);
        throw new Exception('Worker not found or photo not updated');
    }

    sendResponse([
        'success' => true,
        'message' => 'Profile photo uploaded',
        'data' => [
            'file_name' => $fileName,
            'path' => $publicPath,
        ],
    ]);
} catch (Exception $e) {
    sendResponse([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}


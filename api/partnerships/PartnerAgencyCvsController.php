<?php
/**
 * CV / document files attached to a partner agency (shown on partner portal).
 */
require_once __DIR__ . '/PartnerAgencyWorkerDocSharesController.php';

class PartnerAgencyCvsController
{
    /** @var PDO */
    private $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public static function uploadsBaseDir(): string
    {
        $root = realpath(__DIR__ . '/../../uploads');
        if ($root !== false) {
            return $root;
        }

        return __DIR__ . '/../../uploads';
    }

    public static function agencyCvDir(int $agencyId): string
    {
        $base = rtrim(self::uploadsBaseDir(), DIRECTORY_SEPARATOR);
        $dir = $base . DIRECTORY_SEPARATOR . 'partner_agency_cvs' . DIRECTORY_SEPARATOR . $agencyId;

        return $dir;
    }

    /**
     * Creates uploads/partner_agency_cvs/{id} when missing (POST upload).
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public static function ensurePartnerAgencyCvUploadTree(int $agencyId): string
    {
        if ($agencyId <= 0) {
            throw new InvalidArgumentException('Invalid agency');
        }
        $base = rtrim(self::uploadsBaseDir(), DIRECTORY_SEPARATOR);
        self::mkdirWritableOrThrow($base);
        $mid = $base . DIRECTORY_SEPARATOR . 'partner_agency_cvs';
        self::mkdirWritableOrThrow($mid);
        $dir = $mid . DIRECTORY_SEPARATOR . $agencyId;
        self::mkdirWritableOrThrow($dir);

        return $dir;
    }

    /**
     * @throws RuntimeException
     */
    private static function mkdirWritableOrThrow(string $path): void
    {
        if (is_dir($path)) {
            if (!is_writable($path)) {
                @chmod($path, 0777);
            }
            if (!is_writable($path)) {
                throw new RuntimeException('Directory is not writable: ' . $path);
            }

            return;
        }
        if (file_exists($path)) {
            throw new RuntimeException('Upload path exists but is not a directory: ' . $path);
        }
        if (!@mkdir($path, 0777, true) && !is_dir($path)) {
            $err = '';
            if (function_exists('error_get_last')) {
                $le = error_get_last();
                if (is_array($le) && !empty($le['message'])) {
                    $err = ' — ' . (string) $le['message'];
                }
            }
            throw new RuntimeException('Could not create directory: ' . $path . $err);
        }
        if (!is_writable($path)) {
            @chmod($path, 0777);
        }
        if (!is_writable($path)) {
            throw new RuntimeException('Directory was created but is not writable: ' . $path);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForAgency(int $agencyId): array
    {
        if ($agencyId <= 0) {
            return [];
        }
        $stmt = $this->conn->prepare(
            'SELECT id, partner_agency_id, title, original_filename, mime_type, file_size, sort_order, created_at, display_status
             FROM partner_agency_cvs WHERE partner_agency_id = ? ORDER BY sort_order ASC, id DESC'
        );
        $stmt->execute([$agencyId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $ov = PartnerAgencyWorkerDocSharesController::portalDisplayOverrideFromDb($row['display_status'] ?? null);
            $row['display_status'] = $ov;
            $row['portal_status'] = $ov !== null ? $ov : 'ready';
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array{tmp_name: string, name: string, size: int, error: int, type?: string} $file
     * @return array<string, mixed>
     */
    public function create(int $agencyId, string $title, array $file): array
    {
        if ($agencyId <= 0) {
            throw new InvalidArgumentException('Invalid agency');
        }
        $title = trim($title);
        if ($title === '') {
            throw new InvalidArgumentException('Title is required');
        }
        if (empty($file['tmp_name']) || ((int) ($file['error'] ?? 0)) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('File upload failed');
        }

        $maxBytes = 8 * 1024 * 1024;
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            throw new InvalidArgumentException('File too large (max 8MB)');
        }

        $origName = (string) ($file['name'] ?? 'document');
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp'];
        if ($ext === '' || !in_array($ext, $allowed, true)) {
            throw new InvalidArgumentException('Allowed types: PDF, Word, JPG, PNG, WEBP');
        }

        $mime = '';
        if (function_exists('mime_content_type')) {
            $mime = (string) @mime_content_type($file['tmp_name']);
        }
        if ($mime === '' && isset($file['type'])) {
            $mime = (string) $file['type'];
        }

        $dir = self::ensurePartnerAgencyCvUploadTree($agencyId);

        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $stored;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            if (!@copy($file['tmp_name'], $dest)) {
                throw new RuntimeException('Could not save file');
            }
            @unlink($file['tmp_name']);
        }

        $stmt = $this->conn->prepare(
            'INSERT INTO partner_agency_cvs (partner_agency_id, title, stored_filename, original_filename, mime_type, file_size, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, 0)'
        );
        $stmt->execute([
            $agencyId,
            mb_substr($title, 0, 255),
            $stored,
            mb_substr($origName, 0, 255),
            $mime !== '' ? mb_substr($mime, 0, 120) : null,
            $size,
        ]);
        $newId = (int) $this->conn->lastInsertId();

        return $this->findById($newId);
    }

    /**
     * @return array<string, mixed>
     */
    public function findById(int $id): array
    {
        $stmt = $this->conn->prepare(
            'SELECT id, partner_agency_id, title, stored_filename, original_filename, mime_type, file_size, sort_order, created_at, display_status
             FROM partner_agency_cvs WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Document not found');
        }
        $ov = PartnerAgencyWorkerDocSharesController::portalDisplayOverrideFromDb($row['display_status'] ?? null);
        $row['display_status'] = $ov;
        $row['portal_status'] = $ov !== null ? $ov : 'ready';

        return $row;
    }

    public function updateDisplayStatus(int $cvId, int $agencyId, $displayStatus): void
    {
        if ($cvId <= 0 || $agencyId <= 0) {
            throw new InvalidArgumentException('Invalid document');
        }
        $toStore = PartnerAgencyWorkerDocSharesController::parsePortalDisplayStatusForSave($displayStatus);
        $chk = $this->conn->prepare(
            'SELECT id FROM partner_agency_cvs WHERE id = ? AND partner_agency_id = ? LIMIT 1'
        );
        $chk->execute([$cvId, $agencyId]);
        if (!$chk->fetchColumn()) {
            throw new InvalidArgumentException('Document not found');
        }
        $stmt = $this->conn->prepare(
            'UPDATE partner_agency_cvs SET display_status = ? WHERE id = ? AND partner_agency_id = ?'
        );
        $stmt->execute([$toStore, $cvId, $agencyId]);
    }

    public function delete(int $cvId, int $expectedAgencyId): void
    {
        $row = $this->findById($cvId);
        if ((int) ($row['partner_agency_id'] ?? 0) !== $expectedAgencyId) {
            throw new InvalidArgumentException('Document does not belong to this agency');
        }

        $dir = self::agencyCvDir($expectedAgencyId);
        $path = $dir . DIRECTORY_SEPARATOR . (string) ($row['stored_filename'] ?? '');
        if (is_file($path)) {
            @unlink($path);
        }

        $stmt = $this->conn->prepare('DELETE FROM partner_agency_cvs WHERE id = ? AND partner_agency_id = ?');
        $stmt->execute([$cvId, $expectedAgencyId]);
    }

    public function absoluteFilePath(array $row): ?string
    {
        $aid = (int) ($row['partner_agency_id'] ?? 0);
        $name = (string) ($row['stored_filename'] ?? '');
        if ($aid <= 0 || $name === '') {
            return null;
        }
        $path = self::agencyCvDir($aid) . DIRECTORY_SEPARATOR . $name;
        $real = realpath($path);

        return ($real !== false && is_file($real)) ? $real : null;
    }
}

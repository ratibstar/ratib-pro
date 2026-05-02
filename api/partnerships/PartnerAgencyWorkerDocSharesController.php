<?php
/**
 * Staff-selected worker documents visible on a partner agency portal (per worker + document type).
 */
class PartnerAgencyWorkerDocSharesController
{
    /** @var PDO */
    private $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    /** @return list<string> */
    public static function allowedDocumentTypes(): array
    {
        return [
            'identity',
            'passport',
            'police',
            'medical',
            'visa',
            'ticket',
            'training_certificate',
            'contract_signed',
            'insurance',
            'exit_permit',
        ];
    }

    public static function documentTypeLabel(string $t): string
    {
        $map = [
            'identity' => 'Identity',
            'passport' => 'Passport',
            'police' => 'Police clearance',
            'medical' => 'Medical',
            'visa' => 'Visa',
            'ticket' => 'Ticket',
            'training_certificate' => 'Training certificate',
            'contract_signed' => 'Signed contract',
            'insurance' => 'Insurance',
            'exit_permit' => 'Exit permit',
        ];

        return $map[$t] ?? $t;
    }

    /**
     * Workers linked via deployments to this partner (for picker).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listDeploymentWorkers(int $partnerAgencyId): array
    {
        if ($partnerAgencyId <= 0) {
            return [];
        }
        $sql = "SELECT DISTINCT w.id,
                COALESCE(NULLIF(TRIM(w.worker_name), ''), CONCAT('Worker #', w.id)) AS worker_name,
                COALESCE(NULLIF(TRIM(w.passport_number), ''), '') AS passport_number
            FROM worker_deployments wd
            INNER JOIN workers w ON w.id = wd.worker_id
            WHERE wd.partner_agency_id = ?
              AND (w.status IS NULL OR w.status = '' OR w.status != 'deleted')
            ORDER BY worker_name ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$partnerAgencyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchWorkerRow(int $workerId): ?array
    {
        if ($workerId <= 0) {
            return null;
        }
        $stmt = $this->conn->prepare(
            "SELECT * FROM workers WHERE id = ? AND (status IS NULL OR status = '' OR status != 'deleted') LIMIT 1"
        );
        $stmt->execute([$workerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSharesWithDetails(int $partnerAgencyId): array
    {
        if ($partnerAgencyId <= 0) {
            return [];
        }
        $sql = 'SELECT s.id, s.partner_agency_id, s.worker_id, s.document_type, s.created_at
            FROM partner_agency_worker_document_shares s
            WHERE s.partner_agency_id = ?
            ORDER BY s.created_at DESC';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$partnerAgencyId]);
        $shares = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($shares as $s) {
            $wid = (int) ($s['worker_id'] ?? 0);
            $dt = (string) ($s['document_type'] ?? '');
            $worker = $this->fetchWorkerRow($wid);
            if (!$worker) {
                continue;
            }
            $col = $dt . '_file';
            $hasFile = isset($worker[$col]) && trim((string) $worker[$col]) !== '';
            $name = trim((string) ($worker['worker_name'] ?? ''));
            if ($name === '') {
                $name = 'Worker #' . $wid;
            }
            $out[] = [
                'id' => (int) $s['id'],
                'partner_agency_id' => (int) $s['partner_agency_id'],
                'worker_id' => $wid,
                'document_type' => $dt,
                'document_label' => self::documentTypeLabel($dt),
                'worker_name' => $name,
                'passport_number' => trim((string) ($worker['passport_number'] ?? '')) ?: '—',
                'has_file' => $hasFile,
                'created_at' => $s['created_at'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function addShare(int $partnerAgencyId, int $workerId, string $documentType): array
    {
        if ($partnerAgencyId <= 0 || $workerId <= 0) {
            throw new InvalidArgumentException('Partner agency and worker are required');
        }
        $documentType = strtolower(trim($documentType));
        if (!in_array($documentType, self::allowedDocumentTypes(), true)) {
            throw new InvalidArgumentException('Invalid document type');
        }
        if (!$this->fetchWorkerRow($workerId)) {
            throw new InvalidArgumentException('Worker not found');
        }
        $stmt = $this->conn->prepare(
            'INSERT INTO partner_agency_worker_document_shares (partner_agency_id, worker_id, document_type)
             VALUES (?, ?, ?)'
        );
        try {
            $stmt->execute([$partnerAgencyId, $workerId, $documentType]);
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Duplicate') !== false || stripos($msg, 'unique') !== false) {
                throw new InvalidArgumentException('This document type is already shared for this worker and partner');
            }
            throw $e;
        }

        $id = (int) $this->conn->lastInsertId();

        return [
            'id' => $id,
            'partner_agency_id' => $partnerAgencyId,
            'worker_id' => $workerId,
            'document_type' => $documentType,
            'document_label' => self::documentTypeLabel($documentType),
        ];
    }

    public function deleteShare(int $shareId, int $partnerAgencyId): void
    {
        if ($shareId <= 0 || $partnerAgencyId <= 0) {
            throw new InvalidArgumentException('Invalid share');
        }
        $stmt = $this->conn->prepare(
            'DELETE FROM partner_agency_worker_document_shares WHERE id = ? AND partner_agency_id = ?'
        );
        $stmt->execute([$shareId, $partnerAgencyId]);
        if ((int) $stmt->rowCount() === 0) {
            throw new InvalidArgumentException('Share not found');
        }
    }

    /**
     * Resolve share for download (portal or staff); returns worker row fragment + paths.
     *
     * @return array{share: array<string, mixed>, worker: array<string, mixed>, file_column: string, filename: string}|null
     */
    public function resolveShareForDownload(int $shareId, int $expectedPartnerAgencyId): ?array
    {
        if ($shareId <= 0 || $expectedPartnerAgencyId <= 0) {
            return null;
        }
        $stmt = $this->conn->prepare(
            'SELECT id, partner_agency_id, worker_id, document_type FROM partner_agency_worker_document_shares
             WHERE id = ? AND partner_agency_id = ? LIMIT 1'
        );
        $stmt->execute([$shareId, $expectedPartnerAgencyId]);
        $share = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$share) {
            return null;
        }
        $wid = (int) ($share['worker_id'] ?? 0);
        $dt = (string) ($share['document_type'] ?? '');
        $worker = $this->fetchWorkerRow($wid);
        if (!$worker || !in_array($dt, self::allowedDocumentTypes(), true)) {
            return null;
        }
        $col = $dt . '_file';
        $fn = isset($worker[$col]) ? trim((string) $worker[$col]) : '';
        if ($fn === '') {
            return null;
        }

        return [
            'share' => $share,
            'worker' => $worker,
            'file_column' => $col,
            'filename' => $fn,
            'document_type' => $dt,
            'worker_id' => $wid,
        ];
    }
}

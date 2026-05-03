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

    /**
     * Unified portal row status for Documents & CVs (worker shares): file + deployment.
     *
     * @return 'waiting'|'processing'|'ready'|'issues'|'returned'|'transferred'
     */
    public static function computePortalDocumentStatus(bool $hasFile, ?string $deploymentStatus): string
    {
        if (!$hasFile) {
            return 'waiting';
        }
        $d = strtolower(trim((string) ($deploymentStatus ?? 'processing')));
        switch ($d) {
            case 'issue':
                return 'issues';
            case 'returned':
                return 'returned';
            case 'transferred':
                return 'transferred';
            case 'deployed':
                return 'ready';
            case '':
            case 'processing':
                return 'processing';
            default:
                return 'processing';
        }
    }

    /** @return list<string> */
    public static function allowedPortalDisplaySlugs(): array
    {
        return ['waiting', 'processing', 'ready', 'issues', 'returned', 'transferred'];
    }

    /**
     * Staff override for portal status (null = automatic from file + deployment).
     *
     * @param mixed $raw
     *
     * @throws InvalidArgumentException when a non-empty value is not allowed
     */
    public static function parsePortalDisplayStatusForSave($raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $s = strtolower(trim((string) $raw));
        if ($s === '' || $s === 'auto') {
            return null;
        }
        if ($s === 'issue') {
            $s = 'issues';
        }
        if ($s === 'deployed') {
            $s = 'ready';
        }
        if (!in_array($s, self::allowedPortalDisplaySlugs(), true)) {
            throw new InvalidArgumentException('Invalid display_status');
        }

        return $s;
    }

    /**
     * Read stored override; invalid legacy values behave as automatic.
     */
    public static function portalDisplayOverrideFromDb($raw): ?string
    {
        try {
            return self::parsePortalDisplayStatusForSave($raw);
        } catch (InvalidArgumentException $e) {
            return null;
        }
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
     * Workers linked to this partner for the share picker: active placements **or**
     * workers with portal document shares (CV selection) even without a deployment row.
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
            FROM workers w
            INNER JOIN (
                SELECT worker_id FROM worker_deployments WHERE partner_agency_id = ?
                UNION
                SELECT DISTINCT worker_id FROM partner_agency_worker_document_shares WHERE partner_agency_id = ?
            ) link ON link.worker_id = w.id
            WHERE (w.status IS NULL OR w.status = '' OR w.status != 'deleted')
            ORDER BY worker_name ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$partnerAgencyId, $partnerAgencyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Workers deployed to this partner, with document slots matching the Worker profile (workers.*_file columns).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listDeploymentWorkersWithProfileDocuments(int $partnerAgencyId): array
    {
        if ($partnerAgencyId <= 0) {
            return [];
        }

        $stmt = $this->conn->prepare(
            'SELECT id, worker_id, document_type FROM partner_agency_worker_document_shares WHERE partner_agency_id = ?'
        );
        $stmt->execute([$partnerAgencyId]);
        $shareIdByKey = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $wid = (int) ($row['worker_id'] ?? 0);
            $dt = (string) ($row['document_type'] ?? '');
            if ($wid > 0 && $dt !== '') {
                $shareIdByKey[$wid . '|' . $dt] = (int) ($row['id'] ?? 0);
            }
        }

        $workers = $this->listDeploymentWorkers($partnerAgencyId);
        $out = [];
        foreach ($workers as $w) {
            $wid = (int) ($w['id'] ?? 0);
            if ($wid <= 0) {
                continue;
            }
            $workerRow = $this->fetchWorkerRow($wid);
            if (!$workerRow) {
                continue;
            }

            $documents = [];
            foreach (self::allowedDocumentTypes() as $dt) {
                $col = $dt . '_file';
                $hasFile = isset($workerRow[$col]) && trim((string) $workerRow[$col]) !== '';
                $key = $wid . '|' . $dt;
                $sid = $shareIdByKey[$key] ?? null;
                $documents[] = [
                    'type' => $dt,
                    'label' => self::documentTypeLabel($dt),
                    'has_file' => $hasFile,
                    'shared_on_portal' => $sid !== null && $sid > 0,
                    'share_id' => ($sid !== null && $sid > 0) ? $sid : null,
                ];
            }

            $out[] = [
                'id' => $wid,
                'worker_name' => $w['worker_name'] ?? '',
                'passport_number' => $w['passport_number'] ?? '',
                'documents' => $documents,
            ];
        }

        return $out;
    }

    /**
     * True when this partner has at least one shared document slot for the worker (portal CV access).
     */
    public function partnerHasShareForWorker(int $partnerAgencyId, int $workerId): bool
    {
        if ($partnerAgencyId <= 0 || $workerId <= 0) {
            return false;
        }
        $stmt = $this->conn->prepare(
            'SELECT 1 FROM partner_agency_worker_document_shares
             WHERE partner_agency_id = ? AND worker_id = ? LIMIT 1'
        );
        $stmt->execute([$partnerAgencyId, $workerId]);

        return (bool) $stmt->fetchColumn();
    }

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
     * Short label for partner/staff documents table (job title, else gender · nationality).
     *
     * @param array<string, mixed> $worker
     */
    private static function formatWorkerTypeForPortal(array $worker): string
    {
        $jt = isset($worker['job_title']) ? trim((string) $worker['job_title']) : '';
        if ($jt !== '') {
            $chunks = preg_split('/\s*[,;]\s*/u', $jt, -1, PREG_SPLIT_NO_EMPTY);
            if ($chunks === false || $chunks === []) {
                return '—';
            }
            $chunks = array_map('trim', $chunks);
            $chunks = array_filter($chunks, static function ($x) {
                return $x !== '';
            });

            return $chunks === [] ? '—' : implode(' · ', $chunks);
        }
        $g = isset($worker['gender']) ? trim((string) $worker['gender']) : '';
        $n = isset($worker['nationality']) ? trim((string) $worker['nationality']) : '';
        $parts = array_filter([$g, $n], static function ($x) {
            return $x !== '';
        });

        return $parts === [] ? '—' : implode(' · ', $parts);
    }

    /**
     * Resolve uploaded worker document on disk (same layout as partner-shared-worker-doc-download).
     *
     * @param array<string, mixed> $worker
     *
     * @return array{basename: string, bytes: int, mime: string}|null
     */
    private function workerDocumentFileMeta(int $workerId, string $documentType, array $worker): ?array
    {
        $documentType = strtolower(trim($documentType));
        if ($workerId <= 0 || !in_array($documentType, self::allowedDocumentTypes(), true)) {
            return null;
        }
        $col = $documentType . '_file';
        $fn = isset($worker[$col]) ? trim((string) $worker[$col]) : '';
        if ($fn === '') {
            return null;
        }
        $baseDir = realpath(__DIR__ . '/../../uploads/workers/' . $workerId . '/documents/' . $documentType);
        if ($baseDir === false) {
            return null;
        }
        $path = $baseDir . DIRECTORY_SEPARATOR . $fn;
        $real = realpath($path);
        if ($real === false || !is_file($real) || strpos($real, $baseDir) !== 0) {
            return null;
        }
        $bytes = @filesize($real);
        if ($bytes === false) {
            return null;
        }
        $mime = 'application/octet-stream';
        if (function_exists('mime_content_type')) {
            $m = (string) @mime_content_type($real);
            if ($m !== '') {
                $mime = $m;
            }
        }

        return [
            'basename' => basename($fn),
            'bytes' => (int) $bytes,
            'mime' => $mime,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSharesWithDetails(int $partnerAgencyId): array
    {
        if ($partnerAgencyId <= 0) {
            return [];
        }
        $sql = 'SELECT s.id, s.partner_agency_id, s.worker_id, s.document_type, s.created_at, s.display_status,
                (SELECT wd.status FROM worker_deployments wd
                 WHERE wd.worker_id = s.worker_id AND wd.partner_agency_id = s.partner_agency_id
                 LIMIT 1) AS deployment_status
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
            $meta = $this->workerDocumentFileMeta($wid, $dt, $worker);
            $hasFile = $meta !== null;
            $depRaw = isset($s['deployment_status']) ? trim((string) $s['deployment_status']) : '';
            $depStatus = $depRaw !== '' ? $depRaw : null;
            $override = self::portalDisplayOverrideFromDb($s['display_status'] ?? null);
            $portalStatus = $override !== null
                ? $override
                : self::computePortalDocumentStatus($hasFile, $depStatus);
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
                'worker_type' => self::formatWorkerTypeForPortal($worker),
                'passport_number' => trim((string) ($worker['passport_number'] ?? '')) ?: '—',
                'has_file' => $hasFile,
                'storage_filename' => $meta['basename'] ?? null,
                'file_size' => $meta !== null ? $meta['bytes'] : null,
                'mime_type' => $meta !== null ? $meta['mime'] : null,
                'deployment_status' => $depStatus,
                'display_status' => $override,
                'portal_status' => $portalStatus,
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

    public function updateShareDisplayStatus(int $shareId, int $partnerAgencyId, $displayStatus): void
    {
        if ($shareId <= 0 || $partnerAgencyId <= 0) {
            throw new InvalidArgumentException('Invalid share');
        }
        $toStore = self::parsePortalDisplayStatusForSave($displayStatus);
        $chk = $this->conn->prepare(
            'SELECT id FROM partner_agency_worker_document_shares WHERE id = ? AND partner_agency_id = ? LIMIT 1'
        );
        $chk->execute([$shareId, $partnerAgencyId]);
        if (!$chk->fetchColumn()) {
            throw new InvalidArgumentException('Share not found');
        }
        $stmt = $this->conn->prepare(
            'UPDATE partner_agency_worker_document_shares SET display_status = ? WHERE id = ? AND partner_agency_id = ?'
        );
        $stmt->execute([$toStore, $shareId, $partnerAgencyId]);
    }

    /**
     * Change which document type is shared (same worker + partner row).
     *
     * @return array<string, mixed>
     */
    public function updateShareDocumentType(int $shareId, int $partnerAgencyId, string $documentType): array
    {
        if ($shareId <= 0 || $partnerAgencyId <= 0) {
            throw new InvalidArgumentException('Invalid share');
        }
        $documentType = strtolower(trim($documentType));
        if (!in_array($documentType, self::allowedDocumentTypes(), true)) {
            throw new InvalidArgumentException('Invalid document type');
        }

        $stmt = $this->conn->prepare(
            'SELECT id, worker_id, document_type FROM partner_agency_worker_document_shares
             WHERE id = ? AND partner_agency_id = ? LIMIT 1'
        );
        $stmt->execute([$shareId, $partnerAgencyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new InvalidArgumentException('Share not found');
        }
        $workerId = (int) ($row['worker_id'] ?? 0);
        $current = (string) ($row['document_type'] ?? '');
        if ($current === $documentType) {
            return [
                'id' => $shareId,
                'partner_agency_id' => $partnerAgencyId,
                'worker_id' => $workerId,
                'document_type' => $documentType,
                'document_label' => self::documentTypeLabel($documentType),
            ];
        }

        $dup = $this->conn->prepare(
            'SELECT id FROM partner_agency_worker_document_shares
             WHERE partner_agency_id = ? AND worker_id = ? AND document_type = ? AND id != ? LIMIT 1'
        );
        $dup->execute([$partnerAgencyId, $workerId, $documentType, $shareId]);
        if ($dup->fetch(PDO::FETCH_ASSOC)) {
            throw new InvalidArgumentException('That document type is already shared for this worker');
        }

        $up = $this->conn->prepare(
            'UPDATE partner_agency_worker_document_shares SET document_type = ? WHERE id = ? AND partner_agency_id = ?'
        );
        $up->execute([$documentType, $shareId, $partnerAgencyId]);
        if ((int) $up->rowCount() === 0) {
            throw new InvalidArgumentException('Could not update share');
        }

        return [
            'id' => $shareId,
            'partner_agency_id' => $partnerAgencyId,
            'worker_id' => $workerId,
            'document_type' => $documentType,
            'document_label' => self::documentTypeLabel($documentType),
        ];
    }

    /**
     * Workers with at least one uploaded document file, plus readiness score and deployment partner ids.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listFullReadyWorkers(): array
    {
        $types = self::allowedDocumentTypes();
        $sql = "SELECT w.id, w.worker_name, w.passport_number
            FROM workers w
            WHERE (w.status IS NULL OR w.status = '' OR w.status != 'deleted')
            ORDER BY w.worker_name ASC";
        $stmt = $this->conn->query($sql);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        if ($rows === []) {
            return [];
        }
        $candidates = [];
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $worker = $this->fetchWorkerRow($id);
            if (!$worker) {
                continue;
            }
            $readyCount = 0;
            foreach ($types as $dt) {
                $col = $dt . '_file';
                if (isset($worker[$col]) && trim((string) $worker[$col]) !== '') {
                    $readyCount++;
                }
            }
            if ($readyCount <= 0) {
                continue;
            }
            $r['ready_docs_count'] = $readyCount;
            $r['total_docs'] = count($types);
            $candidates[] = $r;
        }
        if ($candidates === []) {
            return [];
        }
        $ids = array_map(static fn ($r) => (int) ($r['id'] ?? 0), $candidates);
        $ids = array_values(array_filter($ids, static fn ($id) => $id > 0));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $depStmt = $this->conn->prepare(
            "SELECT worker_id, partner_agency_id FROM worker_deployments WHERE worker_id IN ({$placeholders})"
        );
        $depStmt->execute($ids);
        $byWorker = [];
        foreach ($depStmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $wid = (int) ($d['worker_id'] ?? 0);
            $pid = (int) ($d['partner_agency_id'] ?? 0);
            if ($wid <= 0 || $pid <= 0) {
                continue;
            }
            if (!isset($byWorker[$wid])) {
                $byWorker[$wid] = [];
            }
            $byWorker[$wid][$pid] = true;
        }
        $out = [];
        foreach ($candidates as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $pids = isset($byWorker[$id]) ? array_keys($byWorker[$id]) : [];
            sort($pids);
            $out[] = [
                'id' => $id,
                'worker_name' => $r['worker_name'] ?? '',
                'passport_number' => trim((string) ($r['passport_number'] ?? '')),
                'ready_docs_count' => (int) ($r['ready_docs_count'] ?? 0),
                'total_docs' => (int) ($r['total_docs'] ?? count($types)),
                'partner_agency_ids' => $pids,
            ];
        }

        return $out;
    }

    /**
     * Create portal shares for every document type that has a file on the worker profile.
     * If a worker has no uploaded files in those slots, creates one passport share so the partner
     * Documents & CVs table and View CV still list the worker for selection.
     *
     * Does **not** create `worker_deployments` rows — placements stay separate until staff adds them
     * via the Deployments / placements flow.
     *
     * @param array<int> $workerIds
     *
     * @return array{added: int, skipped: int, failed: int, not_deployed: int} not_deployed counts workers not found (missing or deleted).
     */
    public function addAllFileSharesForWorkersToPartner(int $partnerAgencyId, array $workerIds): array
    {
        if ($partnerAgencyId <= 0) {
            throw new InvalidArgumentException('partner_agency_id is required');
        }
        $added = 0;
        $skipped = 0;
        $failed = 0;
        $notDeployed = 0;

        foreach ($workerIds as $widRaw) {
            $wid = (int) $widRaw;
            if ($wid <= 0) {
                continue;
            }
            $worker = $this->fetchWorkerRow($wid);
            if (!$worker) {
                $notDeployed++;

                continue;
            }
            $addedThisWorker = 0;
            foreach (self::allowedDocumentTypes() as $dt) {
                $col = $dt . '_file';
                $hasFile = isset($worker[$col]) && trim((string) $worker[$col]) !== '';
                if (!$hasFile) {
                    continue;
                }
                try {
                    $this->addShare($partnerAgencyId, $wid, $dt);
                    $added++;
                    $addedThisWorker++;
                } catch (InvalidArgumentException $e) {
                    $msg = $e->getMessage();
                    if (stripos($msg, 'already') !== false || stripos($msg, 'Duplicate') !== false) {
                        $skipped++;
                    } else {
                        $failed++;
                    }
                } catch (Throwable $e) {
                    $failed++;
                }
            }
            // Partner selection: ensure at least one portal row per worker so Documents & CVs / View CV work
            // even when no document files are uploaded yet (file column empty).
            if ($addedThisWorker === 0) {
                try {
                    $this->addShare($partnerAgencyId, $wid, 'passport');
                    $added++;
                } catch (InvalidArgumentException $e) {
                    $msg = $e->getMessage();
                    if (stripos($msg, 'already') !== false || stripos($msg, 'Duplicate') !== false) {
                        $skipped++;
                    } else {
                        $failed++;
                    }
                } catch (Throwable $e) {
                    $failed++;
                }
            }
        }

        return [
            'added' => $added,
            'skipped' => $skipped,
            'failed' => $failed,
            'not_deployed' => $notDeployed,
        ];
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

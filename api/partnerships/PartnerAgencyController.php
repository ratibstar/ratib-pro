<?php
/**
 * EN: Handles API endpoint/business logic in `api/partnerships/PartnerAgencyController.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/partnerships/PartnerAgencyController.php`.
 */

class PartnerAgencyController
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function index(): array
    {
        try {
            $stmt = $this->conn->query(
                "SELECT pa.id, pa.name, pa.country, pa.city, pa.contact_person, pa.email, pa.phone, pa.status, pa.created_at,
                        COALESCE(COUNT(DISTINCT wd.id), 0) AS workers_sent,
                        COALESCE(
                            GROUP_CONCAT(
                                DISTINCT CONCAT(
                                    COALESCE(w.worker_name, CONCAT('Worker #', wd.worker_id)),
                                    ' (',
                                    COALESCE(w.passport_number, '-'),
                                    ')'
                                )
                                ORDER BY wd.id DESC
                                SEPARATOR ' | '
                            ),
                            ''
                        ) AS workers_sent_details
                 FROM partner_agencies pa
                 LEFT JOIN worker_deployments wd ON wd.partner_agency_id = pa.id
                 LEFT JOIN workers w ON w.id = wd.worker_id
                 GROUP BY pa.id, pa.name, pa.country, pa.city, pa.contact_person, pa.email, pa.phone, pa.status, pa.created_at
                 ORDER BY pa.id DESC"
            );
            $agencies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->hydrateSentWorkers($agencies);
        } catch (Throwable $e) {
            // Fallback if deployment table/join is unavailable in this tenant DB.
            $stmt = $this->conn->query(
                "SELECT id, name, country, city, contact_person, email, phone, status, created_at, 0 AS workers_sent, '' AS workers_sent_details
                 FROM partner_agencies
                 ORDER BY id DESC"
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['sent_workers'] = [];
            }
            unset($r);

            return $rows;
        }
    }

    /**
     * Full deployment rows per partner agency (same shape as workersByAgency) so the UI modal
     * can use list payload only — avoids a second HTTP round-trip that may hit different routing/caching.
     *
     * @param array<int, array<string, mixed>> $agencies
     * @return array<int, array<string, mixed>>
     */
    private function hydrateSentWorkers(array $agencies): array
    {
        if ($agencies === []) {
            return $agencies;
        }

        $idList = [];
        foreach ($agencies as $a) {
            $id = (int) ($a['id'] ?? 0);
            if ($id > 0) {
                $idList[] = $id;
            }
        }
        if ($idList === []) {
            foreach ($agencies as &$a) {
                $a['sent_workers'] = [];
            }
            unset($a);

            return $agencies;
        }

        $idList = array_values(array_unique($idList, SORT_NUMERIC));

        $byAgency = [];
        foreach ($idList as $id) {
            $byAgency[$id] = [];
        }

        $placeholders = implode(',', array_fill(0, count($idList), '?'));

        try {
            // Match index() list preview: do not reference w.full_name (many DBs omit it — that used to
            // throw and fall into a deployment-only query that hardcoded '-' for passport).
            $sql = "SELECT wd.partner_agency_id, wd.id AS deployment_id, wd.worker_id,
                    COALESCE(NULLIF(TRIM(w.worker_name), ''), CONCAT('Worker #', wd.worker_id)) AS worker_name,
                    COALESCE(NULLIF(TRIM(w.passport_number), ''), '-') AS passport_number,
                    wd.status, wd.contract_start, wd.contract_end,
                    wd.country, wd.job_title, wd.salary,
                    pa.name AS partner_agency_name
             FROM worker_deployments wd
             LEFT JOIN workers w ON w.id = wd.worker_id
             LEFT JOIN partner_agencies pa ON pa.id = wd.partner_agency_id
             WHERE wd.partner_agency_id IN ($placeholders)
             ORDER BY wd.partner_agency_id ASC, wd.id DESC";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($idList);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $aid = (int) ($row['partner_agency_id'] ?? 0);
                if ($aid > 0) {
                    unset($row['partner_agency_id']);
                    $byAgency[$aid][] = $row;
                }
            }
        } catch (Throwable $e) {
            error_log('PartnerAgencyController::hydrateSentWorkers join failed: ' . $e->getMessage());
            foreach ($idList as $id) {
                $byAgency[$id] = [];
            }
            try {
                $sql = "SELECT wd.partner_agency_id, wd.id AS deployment_id, wd.worker_id,
                        COALESCE(NULLIF(TRIM(w.worker_name), ''), CONCAT('Worker #', wd.worker_id)) AS worker_name,
                        COALESCE(NULLIF(TRIM(w.passport_number), ''), '-') AS passport_number,
                        wd.status, wd.contract_start, wd.contract_end,
                        wd.country, wd.job_title, wd.salary,
                        pa.name AS partner_agency_name
                 FROM worker_deployments wd
                 LEFT JOIN workers w ON w.id = wd.worker_id
                 LEFT JOIN partner_agencies pa ON pa.id = wd.partner_agency_id
                 WHERE wd.partner_agency_id IN ($placeholders)
                 ORDER BY wd.partner_agency_id ASC, wd.id DESC";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute($idList);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $aid = (int) ($row['partner_agency_id'] ?? 0);
                    if ($aid > 0) {
                        unset($row['partner_agency_id']);
                        $byAgency[$aid][] = $row;
                    }
                }
            } catch (Throwable $e2) {
                error_log('PartnerAgencyController::hydrateSentWorkers fallback failed: ' . $e2->getMessage());
            }
        }

        foreach ($agencies as &$a) {
            $id = (int) ($a['id'] ?? 0);
            $a['sent_workers'] = $byAgency[$id] ?? [];
        }
        unset($a);

        return $agencies;
    }

    public function create(array $payload): array
    {
        $data = $this->validate($payload, false);
        $stmt = $this->conn->prepare(
            "INSERT INTO partner_agencies (name, country, city, contact_person, email, phone, status)
             VALUES (:name, :country, :city, :contact_person, :email, :phone, :status)"
        );
        $stmt->execute($data);
        $id = (int) $this->conn->lastInsertId();
        return $this->find($id);
    }

    public function update(int $id, array $payload): array
    {
        $this->assertExists($id);
        $data = $this->validate($payload, true);
        $data['id'] = $id;
        $stmt = $this->conn->prepare(
            "UPDATE partner_agencies
             SET name = :name, country = :country, city = :city, contact_person = :contact_person,
                 email = :email, phone = :phone, status = :status
             WHERE id = :id"
        );
        $stmt->execute($data);
        return $this->find($id);
    }

    public function delete(int $id): void
    {
        $this->assertExists($id);
        $stmt = $this->conn->prepare("DELETE FROM partner_agencies WHERE id = ?");
        $stmt->execute([$id]);
    }

    /**
     * Single agency for detail view: base fields plus hydrated sent_workers (deployments).
     */
    public function show(int $id): array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid agency id');
        }
        $agency = $this->find($id);
        $hydrated = $this->hydrateSentWorkers([$agency]);
        $row = $hydrated[0] ?? $agency;
        $sent = $row['sent_workers'] ?? [];
        $row['workers_sent'] = is_array($sent) ? count($sent) : 0;

        return $row;
    }

    public function stats(): array
    {
        $stmt = $this->conn->query(
            "SELECT
                COUNT(*) AS total_agencies,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_agencies,
                COUNT(DISTINCT country) AS countries_count
             FROM partner_agencies"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total_agencies' => (int) ($row['total_agencies'] ?? 0),
            'active_agencies' => (int) ($row['active_agencies'] ?? 0),
            'countries_count' => (int) ($row['countries_count'] ?? 0),
        ];
    }

    public function workersByAgency(int $agencyId): array
    {
        if ($agencyId <= 0) {
            return [];
        }
        try {
            $stmt = $this->conn->prepare(
                "SELECT wd.id AS deployment_id, wd.worker_id,
                        COALESCE(NULLIF(TRIM(w.worker_name), ''), CONCAT('Worker #', wd.worker_id)) AS worker_name,
                        COALESCE(NULLIF(TRIM(w.passport_number), ''), '-') AS passport_number,
                        wd.status, wd.contract_start, wd.contract_end,
                        wd.country, wd.job_title, wd.salary,
                        pa.name AS partner_agency_name
                 FROM worker_deployments wd
                 LEFT JOIN workers w ON w.id = wd.worker_id
                 LEFT JOIN partner_agencies pa ON pa.id = wd.partner_agency_id
                 WHERE wd.partner_agency_id = ?
                 ORDER BY wd.id DESC"
            );
            $stmt->execute([$agencyId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('PartnerAgencyController::workersByAgency join failed: ' . $e->getMessage());
            try {
                $stmt = $this->conn->prepare(
                    "SELECT wd.id AS deployment_id, wd.worker_id,
                            COALESCE(NULLIF(TRIM(w.worker_name), ''), CONCAT('Worker #', wd.worker_id)) AS worker_name,
                            COALESCE(NULLIF(TRIM(w.passport_number), ''), '-') AS passport_number,
                            wd.status, wd.contract_start, wd.contract_end,
                            wd.country, wd.job_title, wd.salary,
                            pa.name AS partner_agency_name
                     FROM worker_deployments wd
                     LEFT JOIN workers w ON w.id = wd.worker_id
                     LEFT JOIN partner_agencies pa ON pa.id = wd.partner_agency_id
                     WHERE wd.partner_agency_id = ?
                     ORDER BY wd.id DESC"
                );
                $stmt->execute([$agencyId]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e2) {
                error_log('PartnerAgencyController::workersByAgency fallback failed: ' . $e2->getMessage());
                return [];
            }
        }
    }

    private function find(int $id): array
    {
        $stmt = $this->conn->prepare(
            "SELECT id, name, country, city, contact_person, email, phone, status, created_at
             FROM partner_agencies WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Agency not found');
        }
        return $row;
    }

    private function assertExists(int $id): void
    {
        $stmt = $this->conn->prepare("SELECT id FROM partner_agencies WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new InvalidArgumentException('Agency not found');
        }
    }

    /**
     * Set status for many agencies in one statement.
     *
     * @param array<int|string> $ids
     * @return int Rows affected (may be 0 if ids missing or already that status)
     */
    public function bulkSetStatus(array $ids, string $status): int
    {
        $status = strtolower(trim($status));
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException('Status must be active or inactive');
        }

        $clean = [];
        foreach ($ids as $id) {
            $i = (int) $id;
            if ($i > 0) {
                $clean[$i] = true;
            }
        }
        $idList = array_keys($clean);
        if ($idList === []) {
            throw new InvalidArgumentException('At least one agency id is required');
        }

        $max = 500;
        if (count($idList) > $max) {
            throw new InvalidArgumentException("Too many ids (max {$max})");
        }

        $placeholders = implode(',', array_fill(0, count($idList), '?'));
        $sql = "UPDATE partner_agencies SET status = ? WHERE id IN ({$placeholders})";
        $params = array_merge([$status], $idList);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->rowCount();
    }

    private function validate(array $payload, bool $forUpdate): array
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $country = trim((string) ($payload['country'] ?? ''));
        if ($name === '' || $country === '') {
            throw new InvalidArgumentException('Name and country are required');
        }

        $status = strtolower(trim((string) ($payload['status'] ?? 'active')));
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        $email = trim((string) ($payload['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }

        return [
            'name' => mb_substr($name, 0, 255),
            'country' => mb_substr($country, 0, 100),
            'city' => mb_substr(trim((string) ($payload['city'] ?? '')), 0, 100),
            'contact_person' => mb_substr(trim((string) ($payload['contact_person'] ?? '')), 0, 255),
            'email' => $email === '' ? null : mb_substr($email, 0, 255),
            'phone' => mb_substr(trim((string) ($payload['phone'] ?? '')), 0, 50),
            'status' => $status,
        ];
    }
}


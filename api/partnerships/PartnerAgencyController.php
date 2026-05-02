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
            $stmt = $this->conn->query('SELECT * FROM partner_agencies ORDER BY id DESC');
            $agencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('PartnerAgencyController::index SELECT * failed: ' . $e->getMessage());
            $stmt = $this->conn->query(
                'SELECT id, name, country, city, contact_person, email, phone, status, created_at FROM partner_agencies ORDER BY id DESC'
            );
            $agencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $agencies = $this->hydrateSentWorkers($agencies);
        foreach ($agencies as &$a) {
            $sent = $a['sent_workers'] ?? [];
            $a['workers_sent'] = is_array($sent) ? count($sent) : 0;
            $parts = [];
            if (is_array($sent)) {
                foreach ($sent as $s) {
                    $parts[] = ($s['worker_name'] ?? '') . ' (' . ($s['passport_number'] ?? '-') . ')';
                }
            }
            $a['workers_sent_details'] = implode(' | ', $parts);
        }
        unset($a);

        return $agencies;
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
        $portalPw = trim((string) ($payload['portal_password'] ?? ''));

        $stmt = $this->conn->prepare(
            'INSERT INTO partner_agencies (
                name, name_ar, agency_code, country, city, city_ar, contact_person, email, phone, phone2, fax,
                address_ar, address_en, license, status,
                passport_no, passport_issue_place, passport_issue_date, sending_bank, account_number, mobile, license_owner, notes
            ) VALUES (
                :name, :name_ar, :agency_code, :country, :city, :city_ar, :contact_person, :email, :phone, :phone2, :fax,
                :address_ar, :address_en, :license, :status,
                :passport_no, :passport_issue_place, :passport_issue_date, :sending_bank, :account_number, :mobile, :license_owner, :notes
            )'
        );
        $stmt->execute($data);
        $id = (int) $this->conn->lastInsertId();

        if ($portalPw !== '') {
            $hash = password_hash($portalPw, PASSWORD_DEFAULT);
            $u = $this->conn->prepare('UPDATE partner_agencies SET portal_password_hash = ?, portal_enabled = 1 WHERE id = ?');
            $u->execute([$hash, $id]);
        }

        return $this->toPublicRow($this->find($id));
    }

    public function update(int $id, array $payload): array
    {
        $this->assertExists($id);
        $data = $this->validate($payload, true);
        $data['id'] = $id;
        $stmt = $this->conn->prepare(
            'UPDATE partner_agencies SET
                name = :name, name_ar = :name_ar, agency_code = :agency_code, country = :country, city = :city, city_ar = :city_ar,
                contact_person = :contact_person, email = :email, phone = :phone, phone2 = :phone2, fax = :fax,
                address_ar = :address_ar, address_en = :address_en, license = :license, status = :status,
                passport_no = :passport_no, passport_issue_place = :passport_issue_place, passport_issue_date = :passport_issue_date,
                sending_bank = :sending_bank, account_number = :account_number, mobile = :mobile, license_owner = :license_owner, notes = :notes
             WHERE id = :id'
        );
        $stmt->execute($data);

        if (array_key_exists('portal_password', $payload)) {
            $pp = trim((string) ($payload['portal_password'] ?? ''));
            if ($pp === '__CLEAR__') {
                $u = $this->conn->prepare('UPDATE partner_agencies SET portal_password_hash = NULL WHERE id = ?');
                $u->execute([$id]);
            } elseif ($pp !== '') {
                if (mb_strlen($pp) < 6) {
                    throw new InvalidArgumentException('Password must be at least 6 characters');
                }
                $hash = password_hash($pp, PASSWORD_DEFAULT);
                $u = $this->conn->prepare('UPDATE partner_agencies SET portal_password_hash = ?, portal_enabled = 1 WHERE id = ?');
                $u->execute([$hash, $id]);
            }
        }

        $magicTokenForLink = null;
        if (!empty($payload['regenerate_portal_token'])) {
            $magicTokenForLink = bin2hex(random_bytes(32));
            $u = $this->conn->prepare('UPDATE partner_agencies SET portal_access_token = ? WHERE id = ?');
            $u->execute([$magicTokenForLink, $id]);
        }

        if (array_key_exists('portal_enabled', $payload)) {
            $en = !empty($payload['portal_enabled']) ? 1 : 0;
            $u = $this->conn->prepare('UPDATE partner_agencies SET portal_enabled = ? WHERE id = ?');
            $u->execute([$en, $id]);
        }

        $row = $this->find($id);
        $public = $this->toPublicRow($row);
        if ($magicTokenForLink !== null && function_exists('ratib_partner_portal_magic_link_url')) {
            $public['portal_magic_link'] = ratib_partner_portal_magic_link_url($magicTokenForLink);
        }

        return $public;
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

        return $this->toPublicRow($row);
    }

    /**
     * Strip secrets; add booleans for UI. Keeps sent_workers and list fields.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function toPublicRow(array $row): array
    {
        $token = isset($row['portal_access_token']) ? (string) $row['portal_access_token'] : '';
        $hash = isset($row['portal_password_hash']) ? (string) $row['portal_password_hash'] : '';
        unset($row['portal_access_token'], $row['portal_password_hash']);
        $row['portal_enabled'] = !empty($row['portal_enabled']);
        $row['portal_has_token'] = $token !== '';
        $row['portal_has_password'] = $hash !== '';

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
        try {
            $stmt = $this->conn->prepare('SELECT * FROM partner_agencies WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
        } catch (Throwable $e) {
            $stmt = $this->conn->prepare(
                'SELECT id, name, country, city, contact_person, email, phone, status, created_at,
                        COALESCE(portal_enabled, 0) AS portal_enabled, portal_access_token, portal_password_hash
                 FROM partner_agencies WHERE id = ? LIMIT 1'
            );
            $stmt->execute([$id]);
        }
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
            throw new InvalidArgumentException('Agency name and country are required');
        }

        $email = trim((string) ($payload['email'] ?? ''));
        if ($email === '') {
            throw new InvalidArgumentException('Email is required for partner portal login');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }

        $portalPw = trim((string) ($payload['portal_password'] ?? ''));
        if (!$forUpdate) {
            if ($portalPw === '') {
                throw new InvalidArgumentException('Partner login password is required when adding an agency');
            }
            if (mb_strlen($portalPw) < 6) {
                throw new InvalidArgumentException('Partner login password must be at least 6 characters');
            }
        }

        $addressEn = trim((string) ($payload['address_en'] ?? ''));
        $city = trim((string) ($payload['city'] ?? ''));
        $license = trim((string) ($payload['license'] ?? ''));
        $phone = trim((string) ($payload['phone'] ?? ''));
        $phone2 = trim((string) ($payload['phone2'] ?? ''));
        $fax = trim((string) ($payload['fax'] ?? ''));

        if ($addressEn === '' || $city === '' || $license === '' || $phone === '' || $fax === '') {
            throw new InvalidArgumentException(
                'Address, city, license, primary phone, and fax are required'
            );
        }

        $status = strtolower(trim((string) ($payload['status'] ?? 'active')));
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        $agencyCode = trim((string) ($payload['agency_code'] ?? ''));
        $agencyCode = $agencyCode === '' ? null : mb_substr($agencyCode, 0, 64);

        $passportIssueRaw = trim((string) ($payload['passport_issue_date'] ?? ''));
        $passportIssueDate = null;
        if ($passportIssueRaw !== '') {
            $dt = \DateTime::createFromFormat('Y-m-d', $passportIssueRaw);
            if (!$dt || $dt->format('Y-m-d') !== $passportIssueRaw) {
                throw new InvalidArgumentException('Passport issue date must be YYYY-MM-DD or empty');
            }
            $passportIssueDate = $passportIssueRaw;
        }

        $phone2Val = $phone2 === '' ? null : mb_substr($phone2, 0, 50);

        return [
            'name' => mb_substr($name, 0, 255),
            'name_ar' => null,
            'agency_code' => $agencyCode,
            'country' => mb_substr($country, 0, 100),
            'city' => mb_substr($city, 0, 100),
            'city_ar' => null,
            'contact_person' => mb_substr(trim((string) ($payload['contact_person'] ?? '')), 0, 255),
            'email' => mb_substr($email, 0, 255),
            'phone' => mb_substr($phone, 0, 50),
            'phone2' => $phone2Val,
            'fax' => mb_substr($fax, 0, 50),
            'address_ar' => null,
            'address_en' => mb_substr($addressEn, 0, 500),
            'license' => mb_substr($license, 0, 255),
            'status' => $status,
            'passport_no' => mb_substr(trim((string) ($payload['passport_no'] ?? '')), 0, 80),
            'passport_issue_place' => mb_substr(trim((string) ($payload['passport_issue_place'] ?? '')), 0, 255),
            'passport_issue_date' => $passportIssueDate,
            'sending_bank' => mb_substr(trim((string) ($payload['sending_bank'] ?? '')), 0, 255),
            'account_number' => mb_substr(trim((string) ($payload['account_number'] ?? '')), 0, 100),
            'mobile' => mb_substr(trim((string) ($payload['mobile'] ?? '')), 0, 50),
            'license_owner' => mb_substr(trim((string) ($payload['license_owner'] ?? '')), 0, 255),
            'notes' => trim((string) ($payload['notes'] ?? '')) === '' ? null : mb_substr(trim((string) ($payload['notes'] ?? '')), 0, 65535),
        ];
    }
}


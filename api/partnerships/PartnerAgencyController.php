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
                passport_no, passport_issue_place, passport_issue_date, sending_bank, account_number, mobile, license_owner, notes,
                financial_account_id
            ) VALUES (
                :name, :name_ar, :agency_code, :country, :city, :city_ar, :contact_person, :email, :phone, :phone2, :fax,
                :address_ar, :address_en, :license, :status,
                :passport_no, :passport_issue_place, :passport_issue_date, :sending_bank, :account_number, :mobile, :license_owner, :notes,
                :financial_account_id
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
        if (!array_key_exists('financial_account_id', $payload)) {
            $cur = $this->find($id);
            $payload['financial_account_id'] = array_key_exists('financial_account_id', $cur)
                ? $cur['financial_account_id']
                : null;
        }
        $data = $this->validate($payload, true);
        $data['id'] = $id;
        $stmt = $this->conn->prepare(
            'UPDATE partner_agencies SET
                name = :name, name_ar = :name_ar, agency_code = :agency_code, country = :country, city = :city, city_ar = :city_ar,
                contact_person = :contact_person, email = :email, phone = :phone, phone2 = :phone2, fax = :fax,
                address_ar = :address_ar, address_en = :address_en, license = :license, status = :status,
                passport_no = :passport_no, passport_issue_place = :passport_issue_place, passport_issue_date = :passport_issue_date,
                sending_bank = :sending_bank, account_number = :account_number, mobile = :mobile, license_owner = :license_owner, notes = :notes,
                financial_account_id = :financial_account_id
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

    /**
     * Partner self-service: update contact/address fields only (portal session). Does not change name, license, status, or portal secrets.
     *
     * @param array<string, mixed> $payload
     */
    public function updatePartnerPortalProfile(int $id, array $payload): array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid agency id');
        }
        $this->assertExists($id);

        $allowed = [
            'contact_person' => 255,
            'email' => 255,
            'phone' => 80,
            'phone2' => 80,
            'fax' => 80,
            'mobile' => 80,
            'address_en' => 2000,
            'address_ar' => 2000,
        ];

        $sets = [];
        $params = [];
        foreach ($allowed as $col => $maxLen) {
            if (!array_key_exists($col, $payload)) {
                continue;
            }
            $v = trim((string) ($payload[$col] ?? ''));
            if (mb_strlen($v) > $maxLen) {
                throw new InvalidArgumentException("Field too long: {$col}");
            }
            $sets[] = '`' . str_replace('`', '', $col) . '` = ?';
            $params[] = $v;
        }

        if ($sets === []) {
            throw new InvalidArgumentException('No valid fields to update');
        }

        if (array_key_exists('email', $payload)) {
            $em = trim((string) ($payload['email'] ?? ''));
            if ($em !== '' && !filter_var($em, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Invalid email address');
            }
        }

        $params[] = $id;
        $sql = 'UPDATE partner_agencies SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $this->toPublicRow($this->find($id));
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
     * Resolved chart-of-accounts id for this partner: explicit financial_account_id, else entity-linked row.
     */
    public function resolveLinkedFinancialAccountId(int $agencyId): ?int
    {
        if ($agencyId <= 0) {
            return null;
        }
        try {
            $explicit = 0;
            try {
                $stmt = $this->conn->prepare(
                    'SELECT financial_account_id FROM partner_agencies WHERE id = ? LIMIT 1'
                );
                $stmt->execute([$agencyId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && array_key_exists('financial_account_id', $row)) {
                    $explicit = (int) $row['financial_account_id'];
                }
            } catch (Throwable $inner) {
                error_log('PartnerAgencyController::resolveLinkedFinancialAccountId explicit column: ' . $inner->getMessage());
            }

            if ($explicit > 0 && $this->financialAccountRowExists($explicit)) {
                return $explicit;
            }

            $stmt = $this->conn->prepare(
                "SELECT id FROM financial_accounts WHERE entity_type = 'partner_agency' AND entity_id = ? AND is_active = 1 LIMIT 1"
            );
            $stmt->execute([$agencyId]);
            $fa = $stmt->fetch(PDO::FETCH_ASSOC);

            return $fa ? (int) $fa['id'] : null;
        } catch (Throwable $e) {
            error_log('PartnerAgencyController::resolveLinkedFinancialAccountId: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Create a chart-of-accounts row for this agency (prefix 48 — same family as entity accounts) when missing.
     *
     * @return array{financial_account_id: int, created: bool, account_code?: string}
     */
    public function ensureFinancialAccount(int $agencyId): array
    {
        if ($agencyId <= 0) {
            throw new InvalidArgumentException('Invalid agency id');
        }
        $this->assertExists($agencyId);

        $existing = $this->resolveLinkedFinancialAccountId($agencyId);
        if ($existing !== null) {
            return ['financial_account_id' => $existing, 'created' => false];
        }

        if (!$this->financialAccountsTableExists()) {
            throw new RuntimeException('Chart of accounts is not available on this database.');
        }

        $this->ensureFinancialAccountsEntityColumns();

        $partner = $this->find($agencyId);
        $name = trim((string) ($partner['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Agency name is required to create a ledger account');
        }

        $prefix = '48';
        $sql = "SELECT COALESCE(MAX(CAST(SUBSTRING(account_code, 3) AS UNSIGNED)), 0) AS mx
                FROM financial_accounts WHERE account_code LIKE ? AND LENGTH(account_code) >= 4";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$prefix . '%']);
        $mxRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextNum = (int) ($mxRow['mx'] ?? 0) + 1;
        $accountCode = $prefix . str_pad((string) $nextNum, 2, '0', STR_PAD_LEFT);

        $accountType = 'Expense';
        $normalBalance = 'Debit';

        $ins = $this->conn->prepare(
            'INSERT INTO financial_accounts (account_code, account_name, account_type, normal_balance, opening_balance, current_balance, is_active, entity_type, entity_id)
             VALUES (?, ?, ?, ?, 0, 0, 1, ?, ?)'
        );
        $ins->execute([$accountCode, $name, $accountType, $normalBalance, 'partner_agency', $agencyId]);
        $newId = (int) $this->conn->lastInsertId();

        try {
            $u = $this->conn->prepare('UPDATE partner_agencies SET financial_account_id = ? WHERE id = ?');
            $u->execute([$newId, $agencyId]);
        } catch (Throwable $e) {
            error_log('PartnerAgencyController::ensureFinancialAccount partner_agencies update: ' . $e->getMessage());
        }

        return [
            'financial_account_id' => $newId,
            'created' => true,
            'account_code' => $accountCode,
        ];
    }

    /**
     * Minimal id + name for lightweight partner portal endpoints (no deployments hydration).
     *
     * @return array{id: int, name: string}
     */
    public function portalSummary(int $id): array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid agency id');
        }
        $stmt = $this->conn->prepare('SELECT id, name FROM partner_agencies WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Agency not found');
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => trim((string) ($row['name'] ?? '')),
        ];
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

        $aid = (int) ($row['id'] ?? 0);
        if ($aid > 0) {
            $linkedId = $this->resolveLinkedFinancialAccountId($aid);
            $row['linked_financial_account_id'] = $linkedId;
            $row['accounting_linked'] = $linkedId !== null;
            $row['linked_account_code'] = null;
            $row['linked_account_name'] = null;
            if ($linkedId !== null && $this->financialAccountsTableExists()) {
                try {
                    $st = $this->conn->prepare(
                        'SELECT account_code, account_name FROM financial_accounts WHERE id = ? LIMIT 1'
                    );
                    $st->execute([$linkedId]);
                    $fa = $st->fetch(PDO::FETCH_ASSOC);
                    if ($fa) {
                        $row['linked_account_code'] = $fa['account_code'] ?? null;
                        $row['linked_account_name'] = $fa['account_name'] ?? null;
                    }
                } catch (Throwable $e) {
                    error_log('PartnerAgencyController::toPublicRow FA meta: ' . $e->getMessage());
                }
            }
        }

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
            'financial_account_id' => $this->normalizeFinancialAccountId($payload['financial_account_id'] ?? null),
        ];
    }

    private function normalizeFinancialAccountId($raw): ?int
    {
        if ($raw === null || $raw === '' || $raw === false) {
            return null;
        }
        $id = (int) $raw;
        if ($id <= 0) {
            return null;
        }
        $this->assertFinancialAccountExists($id);

        return $id;
    }

    private function assertFinancialAccountExists(int $id): void
    {
        if (!$this->financialAccountsTableExists()) {
            throw new InvalidArgumentException('Chart of accounts is not available');
        }
        $stmt = $this->conn->prepare('SELECT id FROM financial_accounts WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new InvalidArgumentException('Financial account not found');
        }
    }

    private function financialAccountRowExists(int $id): bool
    {
        if (!$this->financialAccountsTableExists() || $id <= 0) {
            return false;
        }
        try {
            $stmt = $this->conn->prepare('SELECT id FROM financial_accounts WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);

            return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function financialAccountsTableExists(): bool
    {
        try {
            $stmt = $this->conn->query("SHOW TABLES LIKE 'financial_accounts'");

            return $stmt && $stmt->fetch() !== false;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function ensureFinancialAccountsEntityColumns(): void
    {
        try {
            $chk = $this->conn->query("SHOW COLUMNS FROM financial_accounts LIKE 'entity_type'");
            if (!$chk || !$chk->fetch()) {
                $this->conn->exec(
                    'ALTER TABLE financial_accounts ADD COLUMN entity_type VARCHAR(50) NULL DEFAULT NULL'
                );
            }
        } catch (Throwable $e) {
            error_log('PartnerAgencyController ensure entity_type: ' . $e->getMessage());
        }
        try {
            $chk = $this->conn->query("SHOW COLUMNS FROM financial_accounts LIKE 'entity_id'");
            if (!$chk || !$chk->fetch()) {
                $this->conn->exec(
                    'ALTER TABLE financial_accounts ADD COLUMN entity_id INT(11) NULL DEFAULT NULL'
                );
            }
        } catch (Throwable $e) {
            error_log('PartnerAgencyController ensure entity_id: ' . $e->getMessage());
        }
    }
}


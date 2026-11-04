<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Services;

use PDO;

/** Registry for Gulf markets, providers, rights, coverage claims, and gated fixtures. */
final class AutomotiveGulfFoundationService
{
    /** @var list<string> */
    public const MARKETS = ['SA', 'AE', 'KW', 'QA', 'BH', 'OM'];

    /** @var list<string> */
    public const PROVIDER_TYPES = ['data_provider', 'manufacturer', 'data_owner'];

    /** @var list<string> */
    public const PROVIDER_STATUSES = ['active', 'pending', 'suspended', 'rejected'];

    /** @var list<string> */
    public const CATEGORIES = ['vehicle_identity', 'oem_parts', 'diagrams', 'fitment', 'aftermarket', 'vin_decoding'];

    /** @var list<string> */
    public const RIGHTS = ['unknown', 'yes', 'no'];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function gulfMarkets(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, code, name_en, name_ar FROM rateb_auto_ref_markets WHERE code IN ('SA','AE','KW','QA','BH','OM') ORDER BY code"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param list<string> $marketCodes @param list<string> $categories */
    public function saveProvider(array $input, array $marketCodes, array $categories): int
    {
        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        $type = (string) ($input['provider_type'] ?? '');
        $nameEn = trim((string) ($input['name_en'] ?? ''));
        $nameAr = trim((string) ($input['name_ar'] ?? ''));
        $website = trim((string) ($input['website'] ?? ''));
        $contact = trim((string) ($input['contact'] ?? ''));
        $status = (string) ($input['status'] ?? 'pending');
        $source = trim((string) ($input['source_note'] ?? ''));
        $review = trim((string) ($input['review_note'] ?? ''));
        if (!preg_match('/^[A-Z0-9-]{2,40}$/', $code) || $nameEn === '' || $nameAr === '' || $source === '') {
            throw new \InvalidArgumentException('invalid_provider');
        }
        if (!in_array($type, self::PROVIDER_TYPES, true) || !in_array($status, self::PROVIDER_STATUSES, true)) {
            throw new \InvalidArgumentException('invalid_provider');
        }
        if ($website !== '' && preg_match('#^https://#', $website) !== 1) {
            throw new \InvalidArgumentException('invalid_provider');
        }
        $categories = array_values(array_unique($categories));
        if ($categories === [] || array_diff($categories, self::CATEGORIES) !== []) {
            throw new \InvalidArgumentException('invalid_provider');
        }
        $marketCodes = array_values(array_unique(array_map(static fn ($c) => strtoupper(trim((string) $c)), $marketCodes)));
        if ($marketCodes === [] || array_diff($marketCodes, self::MARKETS) !== []) {
            throw new \InvalidArgumentException('invalid_provider');
        }
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO rateb_auto_ref_data_providers
                (code, provider_type, name_en, name_ar, website, contact, categories, source_note, status, review_note)
                VALUES (:code, :type, :en, :ar, :web, :contact, :cat, :source, :status, :review)'
            );
            $stmt->execute([
                'code' => $code,
                'type' => $type,
                'en' => $nameEn,
                'ar' => $nameAr,
                'web' => $website !== '' ? $website : null,
                'contact' => $contact !== '' ? $contact : null,
                'cat' => implode(',', $categories),
                'source' => $source,
                'status' => $status,
                'review' => $review !== '' ? $review : null,
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $link = $this->pdo->prepare(
                'INSERT INTO rateb_auto_ref_provider_markets (provider_id, market_id)
                 SELECT :id, id FROM rateb_auto_ref_markets WHERE code = :code'
            );
            foreach ($marketCodes as $market) {
                $link->execute(['id' => $id, 'code' => $market]);
            }
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<string, string> $flags */
    public function saveRights(
        int $providerId,
        string $evidence,
        array $flags,
        string $territory,
        ?string $expiresOn,
        ?string $terminatedOn,
        ?string $effectiveOn = null,
        string $dataCategory = ''
    ): int
    {
        $evidence = trim($evidence);
        if ($providerId < 1 || $evidence === '') {
            throw new \InvalidArgumentException('invalid_rights');
        }
        $columns = ['store_local', 'process_internal', 'commercial_display', 'saas_multi', 'redistribute', 'diagrams'];
        $values = [];
        foreach ($columns as $column) {
            $value = $flags[$column] ?? 'unknown';
            if (!in_array($value, self::RIGHTS, true)) {
                throw new \InvalidArgumentException('invalid_rights');
            }
            $values[$column] = $value;
        }
        $access = $flags['access_mode'] ?? 'unknown';
        if (!in_array($access, ['unknown', 'api', 'bulk', 'both'], true)) {
            throw new \InvalidArgumentException('invalid_rights');
        }
        $territory = strtoupper(str_replace(' ', '', $territory));
        if ($territory !== '' && array_diff(explode(',', $territory), self::MARKETS) !== []) {
            throw new \InvalidArgumentException('invalid_rights');
        }
        $dataCategory = trim($dataCategory);
        if ($dataCategory !== '' && !in_array($dataCategory, self::CATEGORIES, true)) {
            throw new \InvalidArgumentException('invalid_rights');
        }
        foreach (['effective' => $effectiveOn, 'expires' => $expiresOn, 'terminated' => $terminatedOn] as $date) {
            if ($date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
                throw new \InvalidArgumentException('invalid_rights');
            }
        }
        if ($effectiveOn !== null && $expiresOn !== null && $effectiveOn > $expiresOn) {
            throw new \InvalidArgumentException('invalid_rights');
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO rateb_auto_ref_data_rights
            (provider_id, evidence, store_local, process_internal, commercial_display, saas_multi, redistribute, access_mode, diagrams, territory_codes, data_category, effective_on, expires_on, terminated_on)
            VALUES (:provider, :evidence, :store, :process, :display, :saas, :redistribute, :access, :diagrams, :territory, :category, :effective, :expires, :terminated)'
        );
        $stmt->execute([
            'provider' => $providerId,
            'evidence' => $evidence,
            'store' => $values['store_local'],
            'process' => $values['process_internal'],
            'display' => $values['commercial_display'],
            'saas' => $values['saas_multi'],
            'redistribute' => $values['redistribute'],
            'access' => $access,
            'diagrams' => $values['diagrams'],
            'territory' => $territory,
            'category' => $dataCategory,
            'effective' => $effectiveOn,
            'expires' => $expiresOn,
            'terminated' => $terminatedOn,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function commercialImportAllowed(
        int $rightsId,
        string $marketCode,
        string $today,
        string $category = 'vehicle_identity',
        bool $redistribute = false
    ): bool {
        try {
            $this->assertImport($rightsId, $marketCode, $today, $category, $redistribute);
            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    /** @param array<string, mixed> $claim */
    public function saveCoverage(array $claim): int
    {
        $market = strtoupper((string) ($claim['market_code'] ?? ''));
        $providerId = (int) ($claim['provider_id'] ?? 0);
        $category = (string) ($claim['data_category'] ?? '');
        $verification = (string) ($claim['verification'] ?? '');
        $license = (string) ($claim['license_status'] ?? '');
        if (!in_array($market, self::MARKETS, true) || $providerId < 1) {
            throw new \InvalidArgumentException('invalid_coverage');
        }
        if (!in_array($category, self::CATEGORIES, true)) {
            throw new \InvalidArgumentException('invalid_coverage');
        }
        if (!in_array($verification, ['verified', 'partial', 'unverified', 'unavailable'], true)) {
            throw new \InvalidArgumentException('invalid_coverage');
        }
        if (!in_array($license, ['sufficient', 'unknown', 'insufficient', 'expired'], true)) {
            throw new \InvalidArgumentException('invalid_coverage');
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO rateb_auto_ref_coverage_claims
            (market_id, provider_id, brand_name, model_name, year_from, year_to, vehicle_category, data_category, verification, license_status)
            SELECT id, :provider, :brand, :model, :year_from, :year_to, :vehicle, :category, :verification, :license
            FROM rateb_auto_ref_markets WHERE code = :market'
        );
        $stmt->execute([
            'provider' => $providerId,
            'brand' => $this->nullable($claim['brand_name'] ?? null),
            'model' => $this->nullable($claim['model_name'] ?? null),
            'year_from' => $claim['year_from'] ?? null,
            'year_to' => $claim['year_to'] ?? null,
            'vehicle' => $this->nullable($claim['vehicle_category'] ?? null),
            'category' => $category,
            'verification' => $verification,
            'license' => $license,
            'market' => $market,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new \InvalidArgumentException('invalid_coverage');
        }
        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function coverage(): array
    {
        $stmt = $this->pdo->query(
            'SELECT m.code AS market_code, c.brand_name, c.model_name, c.year_from, c.year_to,
                    c.data_category, c.verification, c.license_status, p.code AS provider_code
             FROM rateb_auto_ref_coverage_claims c
             JOIN rateb_auto_ref_markets m ON m.id = c.market_id
             JOIN rateb_auto_ref_data_providers p ON p.id = c.provider_id
             ORDER BY m.code, c.id'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array{inserted:int,skipped:int,rejected:int,failed:int,sha256:string,wrote:bool} */
    public function dryRun(
        int $rightsId,
        string $marketCode,
        string $payload,
        string $today,
        string $category = 'vehicle_identity',
        bool $redistribute = false,
        ?string $expectedSha = null
    ): array {
        $this->assertImport($rightsId, $marketCode, $today, $category, $redistribute);
        $sha = $this->checksum($payload, $expectedSha);
        $counts = $this->classify($payload);
        $counts['sha256'] = $sha;
        $counts['wrote'] = false;
        $counts['status'] = 'preview';
        return $counts;
    }

    /** @return array{inserted:int,skipped:int,rejected:int,failed:int,sha256:string,wrote:bool,status:string} */
    public function importFixture(
        int $rightsId,
        string $marketCode,
        string $payload,
        string $today,
        string $category = 'vehicle_identity',
        bool $redistribute = false,
        ?string $expectedSha = null
    ): array {
        $this->assertImport($rightsId, $marketCode, $today, $category, $redistribute);
        $sha = $this->checksum($payload, $expectedSha);
        $existing = $this->pdo->prepare(
            'SELECT status, inserted_count, skipped_count, rejected_count, failed_count
             FROM rateb_auto_ref_gulf_import_reports WHERE file_sha256 = :sha'
        );
        $existing->execute(['sha' => $sha]);
        $stored = $existing->fetch(PDO::FETCH_ASSOC);
        if ($stored) {
            return [
                'inserted' => (int) $stored['inserted_count'],
                'skipped' => (int) $stored['skipped_count'],
                'rejected' => (int) $stored['rejected_count'],
                'failed' => (int) $stored['failed_count'],
                'sha256' => $sha,
                'wrote' => false,
                'status' => (string) $stored['status'],
            ];
        }
        $rights = $this->rightsRow($rightsId);
        $parsed = $this->parse($payload);
        $this->pdo->beginTransaction();
        try {
            $report = $this->pdo->prepare(
                'INSERT INTO rateb_auto_ref_gulf_import_reports
                (provider_id, rights_id, file_sha256, status, inserted_count, skipped_count, rejected_count, failed_count)
                VALUES (:provider, :rights, :sha, :status, :inserted, :skipped, :rejected, 0)'
            );
            $report->execute([
                'provider' => (int) $rights['provider_id'],
                'rights' => $rightsId,
                'sha' => $sha,
                'status' => 'applied',
                'inserted' => count($parsed['rows']),
                'skipped' => $parsed['skipped'],
                'rejected' => $parsed['rejected'],
            ]);
            $reportId = (int) $this->pdo->lastInsertId();
            $row = $this->pdo->prepare(
                'INSERT INTO rateb_auto_ref_gulf_import_rows (report_id, identity_key, label) VALUES (:report, :key, :label)'
            );
            foreach ($parsed['rows'] as $item) {
                $row->execute(['report' => $reportId, 'key' => $item['key'], 'label' => $item['label']]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        return [
            'inserted' => count($parsed['rows']),
            'skipped' => $parsed['skipped'],
            'rejected' => $parsed['rejected'],
            'failed' => 0,
            'sha256' => $sha,
            'wrote' => true,
            'status' => 'applied',
        ];
    }

    /** Calendar dates are compared as Asia/Riyadh dates in Y-m-d form. */
    public static function businessDate(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Riyadh')))->format('Y-m-d');
    }

    /** @return list<array<string, mixed>> */
    public function providers(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, code, provider_type, name_en, name_ar, website, contact, categories, source_note, status, review_note
             FROM rateb_auto_ref_data_providers ORDER BY code'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function rights(): array
    {
        $stmt = $this->pdo->query(
            'SELECT r.id, p.code AS provider_code, r.evidence, r.commercial_display, r.redistribute, r.access_mode, r.territory_codes, r.expires_on
             FROM rateb_auto_ref_data_rights r
             JOIN rateb_auto_ref_data_providers p ON p.id = r.provider_id
             ORDER BY r.id'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed> */
    private function assertImport(int $rightsId, string $marketCode, string $today, string $category, bool $redistribute): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $today) !== 1 || !in_array($category, self::CATEGORIES, true)) {
            throw new \RuntimeException('rights_blocked');
        }
        $row = $this->rightsRow($rightsId);
        $marketCode = strtoupper($marketCode);
        $providerId = (int) $row['provider_id'];
        $status = $this->pdo->prepare('SELECT status FROM rateb_auto_ref_data_providers WHERE id = :id');
        $status->execute(['id' => $providerId]);
        if ((string) $status->fetchColumn() !== 'active') {
            throw new \RuntimeException('rights_blocked');
        }
        $covering = $this->coveringRights($providerId, $marketCode, $category, $today);
        $this->recordConflicts($providerId, $marketCode, $category, $today, $covering);
        if (!$this->rowPermits($row, $marketCode, $category, $today, $redistribute)) {
            throw new \RuntimeException('rights_blocked');
        }
        foreach ($covering as $other) {
            if ((int) $other['id'] === (int) $row['id']) {
                continue;
            }
            if (!$this->rowPermits($other, $marketCode, $category, $today, $redistribute) || !$this->sameDecision($row, $other)) {
                throw new \RuntimeException('rights_conflict');
            }
        }
    }

    /** @param list<array<string, mixed>> $covering */
    private function recordConflicts(int $providerId, string $marketCode, string $category, string $today, array $covering): void
    {
        $insert = $this->pdo->prepare(
            'INSERT IGNORE INTO rateb_auto_ref_rights_conflicts
            (provider_id, left_rights_id, right_rights_id, market_code, data_category, detected_on, evidence)
            VALUES (:provider, :left, :right, :market, :category, :detected, :evidence)'
        );
        $count = count($covering);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($this->sameDecision($covering[$i], $covering[$j])) {
                    continue;
                }
                $left = min((int) $covering[$i]['id'], (int) $covering[$j]['id']);
                $right = max((int) $covering[$i]['id'], (int) $covering[$j]['id']);
                $insert->execute([
                    'provider' => $providerId,
                    'left' => $left,
                    'right' => $right,
                    'market' => $marketCode,
                    'category' => $category,
                    'detected' => $today,
                    'evidence' => 'Overlapping rights disagree for ' . $marketCode . ' ' . $category,
                ]);
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function coveringRights(int $providerId, string $marketCode, string $category, string $today): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rateb_auto_ref_data_rights WHERE provider_id = :id ORDER BY id');
        $stmt->execute(['id' => $providerId]);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if ($this->covers($row, $marketCode, $category, $today)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /** @param array<string, mixed> $row */
    private function covers(array $row, string $marketCode, string $category, string $today): bool
    {
        $storedCategory = (string) ($row['data_category'] ?? '');
        if ($storedCategory !== '' && $storedCategory !== $category) {
            return false;
        }
        $territory = (string) ($row['territory_codes'] ?? '');
        if ($territory === '' || !in_array($marketCode, explode(',', $territory), true)) {
            return false;
        }
        $effective = $row['effective_on'] ?? null;
        if ($effective !== null && (string) $effective > $today) {
            return false;
        }
        $expires = $row['expires_on'] ?? null;
        if ($expires !== null && (string) $expires < $today) {
            return false;
        }
        $terminated = $row['terminated_on'] ?? null;
        return $terminated === null || (string) $terminated > $today;
    }

    /** @param array<string, mixed> $row */
    private function rowPermits(array $row, string $marketCode, string $category, string $today, bool $redistribute): bool
    {
        if (!$this->covers($row, $marketCode, $category, $today)) {
            return false;
        }
        foreach (['store_local', 'process_internal', 'commercial_display', 'saas_multi'] as $flag) {
            if (($row[$flag] ?? 'unknown') !== 'yes') {
                return false;
            }
        }
        if (!in_array((string) ($row['access_mode'] ?? ''), ['bulk', 'both'], true)) {
            return false;
        }
        if ($redistribute && ($row['redistribute'] ?? 'unknown') !== 'yes') {
            return false;
        }
        if ($category === 'diagrams' && ($row['diagrams'] ?? 'unknown') !== 'yes') {
            return false;
        }
        return true;
    }

    /** @param array<string, mixed> $left @param array<string, mixed> $right */
    private function sameDecision(array $left, array $right): bool
    {
        foreach (['store_local', 'process_internal', 'commercial_display', 'saas_multi', 'redistribute', 'diagrams', 'access_mode'] as $flag) {
            if ((string) ($left[$flag] ?? '') !== (string) ($right[$flag] ?? '')) {
                return false;
            }
        }
        return true;
    }

    private function checksum(string $payload, ?string $expected): string
    {
        $actual = hash('sha256', $payload);
        if ($expected === null || $expected === '') {
            return $actual;
        }
        if (preg_match('/^[a-f0-9]{64}$/', strtolower($expected)) !== 1 || strtolower($expected) !== $actual) {
            throw new \RuntimeException('checksum_mismatch');
        }
        return $actual;
    }

    /** @return array<string, mixed> */
    private function rightsRow(int $rightsId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rateb_auto_ref_data_rights WHERE id = :id');
        $stmt->execute(['id' => $rightsId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \InvalidArgumentException('invalid_rights');
        }
        return $row;
    }

    /** @return array{inserted:int,skipped:int,rejected:int,failed:int} */
    private function classify(string $payload): array
    {
        $parsed = $this->parse($payload);
        return [
            'inserted' => count($parsed['rows']),
            'skipped' => $parsed['skipped'],
            'rejected' => $parsed['rejected'],
            'failed' => 0,
        ];
    }

    /** @return array{rows:list<array{key:string,label:string}>,skipped:int,rejected:int} */
    private function parse(string $payload): array
    {
        if ($payload === '' || strlen($payload) > 200000) {
            throw new \InvalidArgumentException('invalid_fixture');
        }
        $lines = preg_split('/\r\n|\n|\r/', $payload) ?: [];
        if (trim((string) ($lines[0] ?? '')) !== 'identity,label') {
            throw new \InvalidArgumentException('invalid_fixture');
        }
        $rows = [];
        $seen = [];
        $skipped = 0;
        $rejected = 0;
        foreach (array_slice($lines, 1) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line, ',', '"', '\\');
            $key = strtoupper(trim((string) ($cols[0] ?? '')));
            $label = trim((string) ($cols[1] ?? ''));
            if (count($cols) !== 2 || !preg_match('/^[A-Z0-9-]{2,40}$/', $key) || $label === '') {
                $rejected++;
                continue;
            }
            if (isset($seen[$key])) {
                $skipped++;
                continue;
            }
            $seen[$key] = true;
            $rows[] = ['key' => $key, 'label' => $label];
        }
        return ['rows' => $rows, 'skipped' => $skipped, 'rejected' => $rejected];
    }

    private function nullable(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }
}

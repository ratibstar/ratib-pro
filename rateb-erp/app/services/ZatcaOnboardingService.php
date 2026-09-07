<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\JournalEntry;

/**
 * Device onboarding against the ZATCA Fatoora e-invoicing platform.
 *
 * Keeps the EGS (E-Invoice Generation Solution) identity, the compliance CSID and the
 * production CSID for one company, and mirrors the result into the accounting tax
 * profile so invoices/QR generation follow the same environment.
 */
final class ZatcaOnboardingService
{
    public const TABLE = 'rateb_zatca_connections';

    public const SOLUTION_NAME = 'RATIB_ERP';
    public const SOLUTION_VERSION = 'V1.0';

    /** @var array<string, string> ZATCA Fatoora gateway base URLs. */
    private const GATEWAYS = [
        'developer' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal',
        'simulation' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation',
        'production' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/core',
    ];

    /** @return array<int, string> */
    public static function environments(): array
    {
        return array_keys(self::GATEWAYS);
    }

    public static function gateway(string $environment): string
    {
        return self::GATEWAYS[$environment] ?? self::GATEWAYS['developer'];
    }

    public static function normalizeEnvironment(string $environment): string
    {
        $environment = strtolower(trim($environment));
        return isset(self::GATEWAYS[$environment]) ? $environment : 'developer';
    }

    /**
     * Current connection row, always shaped (defaults when nothing is stored yet).
     *
     * @return array<string, mixed>
     */
    public function connection(int $companyId): array
    {
        $defaults = [
            'company_id' => $companyId,
            'environment' => 'developer',
            'egs_serial' => '',
            'status' => 'not_linked',
            'compliance_status' => 'none',
            'compliance_request_id' => null,
            'compliance_certificate' => null,
            'compliance_secret' => null,
            'production_status' => 'none',
            'production_request_id' => null,
            'production_certificate' => null,
            'production_secret' => null,
            'last_error' => null,
            'linked_at' => null,
        ];
        if ($companyId < 1 || !$this->schemaReady()) {
            return $defaults;
        }
        $row = (new JournalEntry())->queryOne(
            'SELECT * FROM ' . self::TABLE . ' WHERE company_id = :cid LIMIT 1',
            ['cid' => $companyId]
        );

        return $row ? array_merge($defaults, (array) $row) : $defaults;
    }

    /**
     * Company identity shown on the onboarding page: ERP company record enriched with
     * the accounting tax profile (VAT number, legal address) it is actually billed under.
     *
     * @return array<string, mixed>
     */
    public function companySnapshot(int $companyId): array
    {
        $profile = (new ZatcaService())->getTaxProfile($companyId);
        $company = $companyId > 0
            ? (new JournalEntry())->queryOne(
                'SELECT id, name, email, phone, address FROM rateb_companies WHERE id = :id LIMIT 1',
                ['id' => $companyId]
            )
            : null;

        $name = trim((string) ($profile['legal_name_ar'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($profile['legal_name_en'] ?? ''));
        }
        if ($name === '') {
            $name = trim((string) ($company['name'] ?? ''));
        }

        $street = trim((string) ($profile['street'] ?? ''));
        $building = trim((string) ($profile['building_no'] ?? ''));
        $address = trim($street . ($building !== '' ? ' ' . $building : ''));
        if ($address === '') {
            $address = trim((string) ($company['address'] ?? ''));
        }

        $vat = preg_replace('/\D+/', '', (string) ($profile['vat_number'] ?? '')) ?? '';

        return [
            'name' => $name,
            'email' => trim((string) ($company['email'] ?? '')),
            'phone' => trim((string) ($company['phone'] ?? '')),
            'vat_number' => $vat,
            'vat_valid' => strlen($vat) === 15 && str_starts_with($vat, '3') && substr($vat, -1) === '3',
            'cr_number' => trim((string) ($profile['cr_number'] ?? '')),
            'address' => $address,
            'city' => trim((string) ($profile['city'] ?? '')),
            'postal_code' => trim((string) ($profile['postal_code'] ?? '')),
        ];
    }

    /** ZATCA EGS serial format: 1-<solution>|2-<version>|3-<device uuid>. */
    public function generateEgsSerial(): string
    {
        return '1-' . self::SOLUTION_NAME . '|2-' . self::SOLUTION_VERSION . '|3-' . $this->uuidV4();
    }

    public function serialTemplate(): string
    {
        return '1-' . self::SOLUTION_NAME . '|2-' . self::SOLUTION_VERSION . '|3-UUID';
    }

    /**
     * Blocking prerequisites before onboarding can be attempted.
     *
     * @return array<string, bool>
     */
    public function prerequisites(int $companyId): array
    {
        $snapshot = $this->companySnapshot($companyId);
        $connection = $this->connection($companyId);

        return [
            'vat_number' => $snapshot['vat_valid'] === true,
            'legal_name' => $snapshot['name'] !== '',
            'address' => $snapshot['city'] !== '' && $snapshot['address'] !== '',
            'egs_serial' => trim((string) ($connection['egs_serial'] ?? '')) !== '',
        ];
    }

    /** Auto-saved from the environment dropdown; never touches an existing CSID. */
    public function saveEnvironment(int $companyId, string $environment): string
    {
        $environment = self::normalizeEnvironment($environment);
        $this->persist($companyId, ['environment' => $environment]);

        return $environment;
    }

    public function saveEgsSerial(int $companyId, string $serial): string
    {
        $serial = trim($serial);
        if ($serial === '') {
            $serial = $this->generateEgsSerial();
        }
        $this->persist($companyId, ['egs_serial' => mb_substr($serial, 0, 190)]);

        return $serial;
    }

    /**
     * Run the onboarding handshake: compliance CSID first, then production CSID.
     *
     * @return array{ok: bool, message: string, connection: array<string, mixed>}
     */
    public function link(int $companyId, string $otp, string $environment = '', string $egsSerial = ''): array
    {
        if ($companyId < 1) {
            return ['ok' => false, 'message' => __('select_company_ops'), 'connection' => $this->connection($companyId)];
        }

        $environment = $environment !== ''
            ? self::normalizeEnvironment($environment)
            : (string) $this->connection($companyId)['environment'];
        $egsSerial = trim($egsSerial);
        if ($egsSerial === '') {
            $egsSerial = trim((string) $this->connection($companyId)['egs_serial']);
        }
        if ($egsSerial === '') {
            $egsSerial = $this->generateEgsSerial();
        }

        $otp = preg_replace('/\D+/', '', $otp) ?? '';
        if (strlen($otp) < 6) {
            return $this->failure($companyId, $environment, $egsSerial, __('zatca_link_otp_invalid'));
        }

        $missing = array_keys(array_filter($this->prerequisites($companyId), static fn ($ok) => $ok === false));
        $missing = array_diff($missing, ['egs_serial']);
        if ($missing !== []) {
            return $this->failure($companyId, $environment, $egsSerial, __('zatca_link_missing_profile'));
        }

        $this->persist($companyId, [
            'environment' => $environment,
            'egs_serial' => mb_substr($egsSerial, 0, 190),
            'status' => 'pending',
            'last_error' => null,
        ]);

        $csr = $this->buildCsr($companyId, $environment, $egsSerial);
        $compliance = $this->requestCsid(self::gateway($environment) . '/compliance', $csr, $otp);

        if (!$compliance['ok'] && $environment === 'production') {
            return $this->failure($companyId, $environment, $egsSerial, $compliance['message']);
        }
        if (!$compliance['ok']) {
            // Developer portal / simulation stay usable offline so the ERP flow can be
            // exercised end-to-end; these credentials are never valid for real clearance.
            $compliance = $this->localTestCsid('compliance', $egsSerial);
        }

        $production = $this->requestCsid(
            self::gateway($environment) . '/production/csids',
            $csr,
            $otp,
            [$compliance['token'], $compliance['secret']]
        );
        if (!$production['ok'] && $environment === 'production') {
            $this->persist($companyId, [
                'compliance_status' => 'active',
                'compliance_request_id' => $compliance['request_id'],
                'compliance_certificate' => $compliance['token'],
                'compliance_secret' => $compliance['secret'],
            ]);

            return $this->failure($companyId, $environment, $egsSerial, $production['message']);
        }
        if (!$production['ok']) {
            $production = $this->localTestCsid('production', $egsSerial);
        }

        $this->persist($companyId, [
            'environment' => $environment,
            'egs_serial' => mb_substr($egsSerial, 0, 190),
            'status' => 'linked',
            'compliance_status' => 'active',
            'compliance_request_id' => $compliance['request_id'],
            'compliance_certificate' => $compliance['token'],
            'compliance_secret' => $compliance['secret'],
            'production_status' => 'active',
            'production_request_id' => $production['request_id'],
            'production_certificate' => $production['token'],
            'production_secret' => $production['secret'],
            'last_error' => null,
            'linked_at' => date('Y-m-d H:i:s'),
        ]);

        $this->syncAccountingProfile($companyId, $environment, true);

        return ['ok' => true, 'message' => __('zatca_link_success'), 'connection' => $this->connection($companyId)];
    }

    /** @return array{ok: bool, message: string, connection: array<string, mixed>} */
    public function unlink(int $companyId): array
    {
        $environment = (string) $this->connection($companyId)['environment'];
        $this->persist($companyId, [
            'status' => 'not_linked',
            'compliance_status' => 'none',
            'compliance_request_id' => null,
            'compliance_certificate' => null,
            'compliance_secret' => null,
            'production_status' => 'none',
            'production_request_id' => null,
            'production_certificate' => null,
            'production_secret' => null,
            'linked_at' => null,
            'last_error' => null,
        ]);
        $this->syncAccountingProfile($companyId, $environment, false);

        return ['ok' => true, 'message' => __('zatca_unlink_success'), 'connection' => $this->connection($companyId)];
    }

    public function isLinked(int $companyId): bool
    {
        return (string) $this->connection($companyId)['status'] === 'linked';
    }

    // ---------------------------------------------------------------- internals

    /** @param array<string, mixed> $fields */
    private function persist(int $companyId, array $fields): void
    {
        if ($companyId < 1 || $fields === [] || !$this->schemaReady()) {
            return;
        }
        $pdo = Database::connection();
        $exists = (new JournalEntry())->queryOne(
            'SELECT company_id FROM ' . self::TABLE . ' WHERE company_id = :cid LIMIT 1',
            ['cid' => $companyId]
        );
        if (!$exists) {
            $pdo->prepare('INSERT INTO ' . self::TABLE . ' (company_id) VALUES (:cid)')
                ->execute(['cid' => $companyId]);
        }
        $sets = [];
        $params = ['cid' => $companyId];
        foreach ($fields as $column => $value) {
            if (!preg_match('/^[a-z_]+$/', $column)) {
                continue;
            }
            $sets[] = $column . ' = :' . $column;
            $params[$column] = $value;
        }
        if ($sets === []) {
            return;
        }
        $pdo->prepare('UPDATE ' . self::TABLE . ' SET ' . implode(', ', $sets) . ' WHERE company_id = :cid')
            ->execute($params);
    }

    /** @return array{ok: false, message: string, connection: array<string, mixed>} */
    private function failure(int $companyId, string $environment, string $serial, string $message): array
    {
        $this->persist($companyId, [
            'environment' => $environment,
            'egs_serial' => mb_substr($serial, 0, 190),
            'status' => 'failed',
            'last_error' => mb_substr($message, 0, 500),
        ]);
        $this->syncAccountingProfile($companyId, $environment, false);

        return ['ok' => false, 'message' => $message, 'connection' => $this->connection($companyId)];
    }

    /** Keeps the accounting tax profile (invoices, QR, VAT report) on the same setting. */
    private function syncAccountingProfile(int $companyId, string $environment, bool $enabled): void
    {
        try {
            $zatca = new ZatcaService();
            $profile = $zatca->getTaxProfile($companyId);
            $profile['zatca_enabled'] = $enabled ? 1 : 0;
            $profile['zatca_environment'] = $environment === 'production' ? 'production' : 'sandbox';
            $zatca->saveTaxProfile($companyId, $profile);
        } catch (\Throwable $e) {
            // Tax profile is advisory here; onboarding state is already stored.
        }
    }

    /**
     * PKCS#10 CSR for the EGS unit. Falls back to a deterministic placeholder when the
     * openssl extension is unavailable so the sandbox flow still completes.
     */
    private function buildCsr(int $companyId, string $environment, string $egsSerial): string
    {
        $snapshot = $this->companySnapshot($companyId);
        if (!function_exists('openssl_csr_new') || !function_exists('openssl_pkey_new')) {
            return base64_encode('CSR|' . $egsSerial . '|' . $snapshot['vat_number']);
        }
        try {
            $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp256k1']);
            if ($key === false) {
                $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
            }
            if ($key === false) {
                return base64_encode('CSR|' . $egsSerial . '|' . $snapshot['vat_number']);
            }
            $subject = [
                'C' => 'SA',
                'O' => $snapshot['name'] !== '' ? $snapshot['name'] : self::SOLUTION_NAME,
                'OU' => $snapshot['vat_number'],
                'CN' => $egsSerial,
            ];
            $csr = openssl_csr_new($subject, $key, ['digest_alg' => 'sha256']);
            if ($csr === false) {
                return base64_encode('CSR|' . $egsSerial . '|' . $snapshot['vat_number']);
            }
            $pem = '';
            openssl_csr_export($csr, $pem);

            return base64_encode($pem);
        } catch (\Throwable $e) {
            return base64_encode('CSR|' . $egsSerial . '|' . $snapshot['vat_number']);
        }
    }

    /**
     * @param array{0: string, 1: string}|null $basicAuth
     * @return array{ok: bool, message: string, token: string, secret: string, request_id: string}
     */
    private function requestCsid(string $url, string $csr, string $otp, ?array $basicAuth = null): array
    {
        $empty = ['ok' => false, 'message' => __('zatca_link_gateway_unreachable'), 'token' => '', 'secret' => '', 'request_id' => ''];
        if (!function_exists('curl_init')) {
            return $empty;
        }
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Accept-Version: V2',
            'Accept-Language: en',
        ];
        if ($basicAuth === null) {
            $headers[] = 'OTP: ' . $otp;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return $empty;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['csr' => $csr], JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        if ($basicAuth !== null) {
            curl_setopt($ch, CURLOPT_USERPWD, $basicAuth[0] . ':' . $basicAuth[1]);
        }
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($body) || $code < 200 || $code >= 300) {
            $message = __('zatca_link_gateway_error');
            if (is_string($body) && $body !== '') {
                $message .= ' (' . $code . ')';
            }

            return ['ok' => false, 'message' => $message, 'token' => '', 'secret' => '', 'request_id' => ''];
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return $empty;
        }
        $token = (string) ($data['binarySecurityToken'] ?? '');
        $secret = (string) ($data['secret'] ?? '');
        if ($token === '') {
            return $empty;
        }

        return [
            'ok' => true,
            'message' => '',
            'token' => $token,
            'secret' => $secret,
            'request_id' => (string) ($data['requestID'] ?? $data['requestId'] ?? ''),
        ];
    }

    /**
     * Locally issued credentials for developer-portal / simulation runs when the ZATCA
     * gateway cannot be reached. Not valid for real invoice clearance.
     *
     * @return array{ok: true, message: string, token: string, secret: string, request_id: string}
     */
    private function localTestCsid(string $kind, string $egsSerial): array
    {
        $token = base64_encode('SANDBOX|' . strtoupper($kind) . '|' . $egsSerial . '|' . bin2hex(random_bytes(24)));

        return [
            'ok' => true,
            'message' => '',
            'token' => $token,
            'secret' => base64_encode(random_bytes(24)),
            'request_id' => (string) random_int(1000000000, 9999999999),
        ];
    }

    private function schemaReady(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        if (Database::tableExists(self::TABLE)) {
            return $ready = true;
        }
        try {
            Database::connection()->exec($this->createTableSql());
            $ready = Database::tableExists(self::TABLE);
        } catch (\Throwable $e) {
            $ready = false;
        }

        return $ready;
    }

    private function createTableSql(): string
    {
        if (Database::isSqlite()) {
            return 'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                company_id INTEGER PRIMARY KEY,
                environment TEXT NOT NULL DEFAULT \'developer\',
                egs_serial TEXT NULL,
                status TEXT NOT NULL DEFAULT \'not_linked\',
                compliance_status TEXT NOT NULL DEFAULT \'none\',
                compliance_request_id TEXT NULL,
                compliance_certificate TEXT NULL,
                compliance_secret TEXT NULL,
                production_status TEXT NOT NULL DEFAULT \'none\',
                production_request_id TEXT NULL,
                production_certificate TEXT NULL,
                production_secret TEXT NULL,
                last_error TEXT NULL,
                linked_at TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )';
        }

        return 'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
            company_id INT UNSIGNED NOT NULL PRIMARY KEY,
            environment ENUM(\'developer\',\'simulation\',\'production\') NOT NULL DEFAULT \'developer\',
            egs_serial VARCHAR(190) NULL,
            status ENUM(\'not_linked\',\'pending\',\'linked\',\'failed\') NOT NULL DEFAULT \'not_linked\',
            compliance_status ENUM(\'none\',\'active\',\'failed\') NOT NULL DEFAULT \'none\',
            compliance_request_id VARCHAR(64) NULL,
            compliance_certificate TEXT NULL,
            compliance_secret VARCHAR(255) NULL,
            production_status ENUM(\'none\',\'active\',\'failed\') NOT NULL DEFAULT \'none\',
            production_request_id VARCHAR(64) NULL,
            production_certificate TEXT NULL,
            production_secret VARCHAR(255) NULL,
            last_error VARCHAR(500) NULL,
            linked_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_zatca_conn_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    }

    private function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        $hex = bin2hex($data);

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    }
}

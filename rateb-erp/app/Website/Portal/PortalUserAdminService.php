<?php
declare(strict_types=1);

namespace Rateb\App\Website\Portal;

use Rateb\App\CustomerPortal\CustomerPortalAuthService;
use Rateb\App\CustomerPortal\CustomerPortalTokenService;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\Logger;

/**
 * ERP admin management of website / Customer Portal accounts for one tenant company.
 * The company is fixed at construction (WebsiteContext); input can never select another company.
 * App access is only ever granted by approveAppAccess(); no path issues tokens or deletes accounts.
 */
final class PortalUserAdminService
{
    public const MIN_PASSWORD_LENGTH = 8;
    public const MAX_PASSWORD_LENGTH = 1024;
    public const LIST_LIMIT = 500;
    public const AUDIT_ENTITY = 'website_portal_user';

    public const STATE_SUSPENDED = 'suspended';
    public const STATE_PENDING = 'pending';
    public const STATE_NOT_APPROVED = 'not_approved';
    public const STATE_APPROVED = 'approved';

    private const PROFILE_FIELDS = ['portal_type', 'email', 'full_name', 'phone', 'organization_name', 'crm_company_id', 'erp_customer_id'];

    private int $companyId;
    private PortalUserAdminStore $store;
    private CustomerPortalTokenService $tokens;
    /** @var callable(string, int, int, array<string, mixed>): void */
    private $audit;
    /** @var callable(): int */
    private $clock;
    /** @var callable(string, array<string, mixed>): void */
    private $errorLog;

    /**
     * @param (callable(string, int, int, array<string, mixed>): void)|null $audit action, company_id, portal_user_id, payload
     * @param (callable(): int)|null $clock
     * @param (callable(string, array<string, mixed>): void)|null $errorLog server error log (storage/logs + PHP error_log)
     */
    public function __construct(
        int $companyId,
        ?PortalUserAdminStore $store = null,
        ?CustomerPortalTokenService $tokens = null,
        ?callable $audit = null,
        ?callable $clock = null,
        ?callable $errorLog = null
    ) {
        if ($companyId < 1) {
            throw new \InvalidArgumentException('company_context_required');
        }
        $this->companyId = $companyId;
        $this->store = $store ?? new PdoPortalUserAdminStore();
        $this->tokens = $tokens ?? new CustomerPortalTokenService();
        $this->audit = $audit ?? static function (string $action, int $companyId, int $entityId, array $payload): void {
            (new AuditService())->log($action, self::AUDIT_ENTITY, $entityId, $payload, $companyId);
        };
        $this->clock = $clock ?? static fn (): int => time();
        $this->errorLog = $errorLog ?? static function (string $message, array $context): void {
            Logger::error($message, $context);
        };
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    /** @return list<string> */
    public static function types(): array
    {
        return CustomerPortalAuthService::PORTAL_TYPES;
    }

    /** @param array<string, mixed> $row */
    public static function appAccessState(array $row): string
    {
        $status = (string) ($row['status'] ?? '');
        if ($status === 'suspended') {
            return self::STATE_SUSPENDED;
        }
        if ($status !== 'active') {
            return self::STATE_PENDING;
        }

        return trim((string) ($row['app_access_approved_at'] ?? '')) !== '' ? self::STATE_APPROVED : self::STATE_NOT_APPROVED;
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $rows = [];
        foreach ($this->store->listForCompany($this->companyId, self::LIST_LIMIT) as $row) {
            if ((int) ($row['company_id'] ?? 0) === $this->companyId) {
                $rows[] = $row;
            }
        }
        $stats = $rows === [] ? [] : $this->store->tokenStats(
            $this->companyId,
            array_map(static fn (array $r): int => (int) $r['id'], $rows),
            $this->now()
        );

        return array_map(fn (array $r): array => $this->decorate($r, $stats[(int) $r['id']] ?? null), $rows);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $row = $this->account($id);
        if ($row === null) {
            return null;
        }
        $stats = $this->store->tokenStats($this->companyId, [$id], $this->now());

        return $this->decorate($row, $stats[$id] ?? null);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, id?: int, errors?: array<string, string>}
     */
    public function create(array $input): array
    {
        [$fields, $errors] = $this->validateProfile($input);
        $passwordError = self::passwordError((string) ($input['password'] ?? ''), (string) ($input['password_confirmation'] ?? ''));
        if ($passwordError !== null) {
            $errors['password'] = $passwordError;
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }
        if ($this->store->findIdByEmail($this->companyId, $fields['portal_type'], $fields['email']) !== null) {
            return ['ok' => false, 'errors' => ['email' => 'email_taken']];
        }
        $fields['password_hash'] = password_hash((string) $input['password'], PASSWORD_DEFAULT);
        try {
            $id = $this->store->insert($this->companyId, $fields);
        } catch (\PDOException $e) {
            if (self::isIntegrityViolation($e)) {
                return ['ok' => false, 'errors' => ['email' => 'email_taken']];
            }
            throw $e;
        }
        $this->record('portal_user.created', $id, [
            'portal_type' => $fields['portal_type'],
            'status' => 'active',
            'app_access' => self::STATE_NOT_APPROVED,
        ]);

        return ['ok' => true, 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, changed?: list<string>, sessions_revoked?: int, errors?: array<string, string>}
     */
    public function update(int $id, array $input): array
    {
        $existing = $this->account($id);
        if ($existing === null) {
            return self::notFound();
        }
        [$fields, $errors] = $this->validateProfile($input);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }
        $identityChanged = $fields['email'] !== strtolower((string) $existing['email'])
            || $fields['portal_type'] !== (string) $existing['portal_type'];
        if ($identityChanged) {
            $other = $this->store->findIdByEmail($this->companyId, $fields['portal_type'], $fields['email']);
            if ($other !== null && $other !== $id) {
                return ['ok' => false, 'errors' => ['email' => 'email_taken']];
            }
        }
        $changed = [];
        foreach ($fields as $column => $value) {
            $old = $existing[$column] ?? null;
            if (self::normalize($old) !== self::normalize($value)) {
                $changed[$column] = $value;
            }
        }
        if ($changed === []) {
            return ['ok' => true, 'changed' => [], 'sessions_revoked' => 0];
        }
        try {
            $this->store->update($this->companyId, $id, $changed);
        } catch (\PDOException $e) {
            if (self::isIntegrityViolation($e)) {
                return ['ok' => false, 'errors' => ['email' => 'email_taken']];
            }
            throw $e;
        }
        $revoked = $identityChanged ? $this->revokeTokens($id, CustomerPortalTokenService::REVOKE_ACCOUNT_DISABLED) : 0;
        $payload = ['changed_fields' => array_keys($changed), 'sessions_revoked' => $revoked];
        if (isset($changed['portal_type'])) {
            $payload['portal_type_from'] = (string) $existing['portal_type'];
            $payload['portal_type_to'] = $fields['portal_type'];
        }
        $this->record('portal_user.updated', $id, $payload);

        return ['ok' => true, 'changed' => array_keys($changed), 'sessions_revoked' => $revoked];
    }

    /** @return array{ok: bool, sessions_revoked?: int, errors?: array<string, string>} */
    public function setPassword(int $id, string $password, string $confirmation): array
    {
        if ($this->account($id) === null) {
            return self::notFound();
        }
        $error = self::passwordError($password, $confirmation);
        if ($error !== null) {
            return ['ok' => false, 'errors' => ['password' => $error]];
        }
        $this->store->update($this->companyId, $id, ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        $revoked = $this->revokeTokens($id, CustomerPortalTokenService::REVOKE_PASSWORD_CHANGE);
        $this->record('portal_user.password_reset', $id, ['sessions_revoked' => $revoked]);

        return ['ok' => true, 'sessions_revoked' => $revoked];
    }

    /** @return array{ok: bool, sessions_revoked?: int, errors?: array<string, string>} */
    public function suspend(int $id): array
    {
        $existing = $this->account($id);
        if ($existing === null) {
            return self::notFound();
        }
        if ((string) $existing['status'] !== 'suspended') {
            $this->store->update($this->companyId, $id, ['status' => 'suspended']);
        }
        $revoked = $this->revokeTokens($id, CustomerPortalTokenService::REVOKE_ACCOUNT_DISABLED);
        $this->record('portal_user.suspended', $id, [
            'status_from' => (string) $existing['status'],
            'sessions_revoked' => $revoked,
        ]);

        return ['ok' => true, 'sessions_revoked' => $revoked];
    }

    /** @return array{ok: bool, errors?: array<string, string>} */
    public function activate(int $id): array
    {
        $existing = $this->account($id);
        if ($existing === null) {
            return self::notFound();
        }
        if ((string) $existing['status'] === 'active') {
            return ['ok' => true];
        }
        $this->store->update($this->companyId, $id, ['status' => 'active']);
        $this->record('portal_user.activated', $id, ['status_from' => (string) $existing['status']]);

        return ['ok' => true];
    }

    /** @return array{ok: bool, approved_at?: string, errors?: array<string, string>} */
    public function approveAppAccess(int $id): array
    {
        $existing = $this->account($id);
        if ($existing === null) {
            return self::notFound();
        }
        $current = trim((string) ($existing['app_access_approved_at'] ?? ''));
        if ($current !== '') {
            return ['ok' => true, 'approved_at' => $current];
        }
        $approvedAt = $this->now();
        $this->store->update($this->companyId, $id, ['app_access_approved_at' => $approvedAt]);
        $this->record('portal_user.app_access_approved', $id, ['approved_at' => $approvedAt]);

        return ['ok' => true, 'approved_at' => $approvedAt];
    }

    /** @return array{ok: bool, sessions_revoked?: int, errors?: array<string, string>} */
    public function revokeAppAccess(int $id): array
    {
        $existing = $this->account($id);
        if ($existing === null) {
            return self::notFound();
        }
        $wasApproved = trim((string) ($existing['app_access_approved_at'] ?? '')) !== '';
        if ($wasApproved) {
            $this->store->update($this->companyId, $id, ['app_access_approved_at' => null]);
        }
        $revoked = $this->revokeTokens($id, CustomerPortalTokenService::REVOKE_ACCOUNT_DISABLED);
        $this->record('portal_user.app_access_revoked', $id, [
            'was_approved' => $wasApproved,
            'sessions_revoked' => $revoked,
        ]);

        return ['ok' => true, 'sessions_revoked' => $revoked];
    }

    /** @return array{ok: bool, sessions_revoked?: int, errors?: array<string, string>} */
    public function revokeSessions(int $id): array
    {
        if ($this->account($id) === null) {
            return self::notFound();
        }
        $revoked = $this->revokeTokens($id, CustomerPortalTokenService::REVOKE_LOGOUT);
        $this->record('portal_user.sessions_revoked', $id, ['sessions_revoked' => $revoked]);

        return ['ok' => true, 'sessions_revoked' => $revoked];
    }

    /** @return array<string, mixed>|null */
    private function account(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $row = $this->store->findById($this->companyId, $id);
        if ($row === null || (int) ($row['company_id'] ?? 0) !== $this->companyId) {
            return null;
        }
        unset($row['password_hash']);

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @param array{active: int, last_used_at: ?string}|null $stats
     * @return array<string, mixed>
     */
    private function decorate(array $row, ?array $stats): array
    {
        unset($row['password_hash']);
        $row['app_access_state'] = self::appAccessState($row);
        $row['active_sessions'] = (int) ($stats['active'] ?? 0);
        $row['sessions_last_used_at'] = $stats['last_used_at'] ?? null;

        return $row;
    }

    /**
     * Whitelisted profile fields only; company_id, status, approval and hashes are never read from input.
     *
     * @param array<string, mixed> $input
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function validateProfile(array $input): array
    {
        $errors = [];
        $portalType = strtolower(trim((string) ($input['portal_type'] ?? '')));
        if (!in_array($portalType, self::types(), true)) {
            $errors['portal_type'] = 'invalid_portal_type';
        }
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if ($email === '' || strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'invalid_email';
        }
        $fullName = trim((string) ($input['full_name'] ?? ''));
        if ($fullName === '') {
            $errors['full_name'] = 'full_name_required';
        } elseif (mb_strlen($fullName) > 190) {
            $errors['full_name'] = 'too_long';
        }
        $phone = trim((string) ($input['phone'] ?? ''));
        if (mb_strlen($phone) > 40) {
            $errors['phone'] = 'too_long';
        }
        $organization = trim((string) ($input['organization_name'] ?? ''));
        if (mb_strlen($organization) > 190) {
            $errors['organization_name'] = 'too_long';
        }
        $crm = $this->optionalLink($input, 'crm_company_id', fn (int $v): bool => $this->store->crmCompanyExists($this->companyId, $v));
        if ($crm === false) {
            $errors['crm_company_id'] = 'invalid_link';
        }
        $customer = $this->optionalLink($input, 'erp_customer_id', fn (int $v): bool => $this->store->customerExists($this->companyId, $v));
        if ($customer === false) {
            $errors['erp_customer_id'] = 'invalid_link';
        }

        $fields = [
            'portal_type' => $portalType,
            'email' => $email,
            'full_name' => $fullName,
            'phone' => $phone !== '' ? $phone : null,
            'organization_name' => $organization !== '' ? $organization : null,
            'crm_company_id' => is_int($crm) ? $crm : null,
            'erp_customer_id' => is_int($customer) ? $customer : null,
        ];

        return [array_intersect_key($fields, array_flip(self::PROFILE_FIELDS)), $errors];
    }

    /**
     * @param array<string, mixed> $input
     * @param callable(int): bool $exists
     * @return int|null|false null = not linked, false = invalid or belongs to another company
     */
    private function optionalLink(array $input, string $key, callable $exists): int|null|false
    {
        $raw = trim((string) ($input[$key] ?? ''));
        if ($raw === '' || $raw === '0') {
            return null;
        }
        if (!ctype_digit($raw) || strlen($raw) > 10) {
            return false;
        }
        $id = (int) $raw;

        return $id > 0 && $exists($id) ? $id : false;
    }

    private static function passwordError(string $password, string $confirmation): ?string
    {
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return 'password_min';
        }
        if (strlen($password) > self::MAX_PASSWORD_LENGTH) {
            return 'password_max';
        }
        if (!hash_equals($password, $confirmation)) {
            return 'password_mismatch';
        }

        return null;
    }

    private function revokeTokens(int $id, string $reason): int
    {
        return $this->tokens->revokeAllForAccount($this->companyId, $id, $reason, ($this->clock)());
    }

    /** @param array<string, mixed> $payload */
    private function record(string $action, int $id, array $payload): void
    {
        foreach (array_keys($payload) as $key) {
            if (preg_match('/password|secret|hash|^token/i', (string) $key)) {
                unset($payload[$key]);
            }
        }
        try {
            ($this->audit)($action, $this->companyId, $id, $payload);
        } catch (\Throwable $e) {
            $context = [
                'action' => $action,
                'company_id' => $this->companyId,
                'portal_user_id' => $id,
                'exception' => get_class($e),
                'error' => mb_substr($e->getMessage(), 0, 300),
            ];
            try {
                ($this->errorLog)('portal_user_audit_failed', $context);
            } catch (\Throwable $logError) {
                error_log('[RATEB ERP] portal_user_audit_failed ' . $action . ' company=' . $this->companyId . ' portal_user=' . $id . ' ' . get_class($e));
            }
        }
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s', ($this->clock)());
    }

    private static function normalize(mixed $value): string
    {
        return $value === null ? '' : (string) $value;
    }

    private static function isIntegrityViolation(\PDOException $e): bool
    {
        return (string) $e->getCode() === '23000' || (string) ($e->errorInfo[0] ?? '') === '23000';
    }

    /** @return array{ok: false, errors: array<string, string>} */
    private static function notFound(): array
    {
        return ['ok' => false, 'errors' => ['account' => 'not_found']];
    }
}

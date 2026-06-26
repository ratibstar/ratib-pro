<?php
declare(strict_types=1);

/**
 * Read-only QA manifest ID resolver — exact slug/email lookup with QA-prefix guard.
 * Used by Safe QA automation; never returns non-QA records.
 */
final class QaManifestResolver
{
    /** @var list<string> */
    private const COMPANY_SLUG_PREFIXES = ['QA-COMPANY-'];

    /** @var list<string> */
    private const ROLE_SLUG_PREFIXES = ['QA-ROLE-'];

    /** @var list<string> */
    private const USER_EMAIL_PREFIXES = ['QA-USER-'];

    /** @var list<string> */
    private const BRANCH_CODE_PREFIXES = ['QA-BRANCH-'];

    public function __construct(private readonly \PDO $db)
    {
    }

    /** @return array{ok:bool, id?:int, error?:string, meta?:array<string,mixed>} */
    public function resolveCompanyBySlug(string $slug): array
    {
        if (!$this->hasPrefix($slug, self::COMPANY_SLUG_PREFIXES)) {
            return ['ok' => false, 'error' => 'slug_not_qa_prefixed'];
        }
        $stmt = $this->db->prepare(
            'SELECT id, slug, name, status FROM rateb_companies WHERE slug = :slug LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        return [
            'ok' => true,
            'id' => (int) $row['id'],
            'meta' => [
                'slug' => (string) $row['slug'],
                'name' => (string) ($row['name'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
            ],
        ];
    }

    /** @return array{ok:bool, id?:int, error?:string, meta?:array<string,mixed>} */
    public function resolveUserByEmail(string $email): array
    {
        if (!$this->hasPrefix($email, self::USER_EMAIL_PREFIXES)) {
            return ['ok' => false, 'error' => 'email_not_qa_prefixed'];
        }
        $stmt = $this->db->prepare(
            'SELECT id, email, name, status, is_super_admin, company_id FROM rateb_users WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        if ((int) ($row['is_super_admin'] ?? 0) === 1) {
            return ['ok' => false, 'error' => 'super_admin_protected'];
        }
        return [
            'ok' => true,
            'id' => (int) $row['id'],
            'meta' => [
                'email' => (string) $row['email'],
                'name' => (string) ($row['name'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'company_id' => $row['company_id'] !== null ? (int) $row['company_id'] : null,
            ],
        ];
    }

    /** @return array{ok:bool, id?:int, error?:string, meta?:array<string,mixed>} */
    public function resolveRoleBySlug(string $slug): array
    {
        if (!$this->hasPrefix($slug, self::ROLE_SLUG_PREFIXES)) {
            return ['ok' => false, 'error' => 'slug_not_qa_prefixed'];
        }
        $stmt = $this->db->prepare(
            'SELECT id, slug, name FROM rateb_roles WHERE slug = :slug LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        return [
            'ok' => true,
            'id' => (int) $row['id'],
            'meta' => [
                'slug' => (string) $row['slug'],
                'name' => (string) ($row['name'] ?? ''),
            ],
        ];
    }

    /** @return array{ok:bool, id?:int, error?:string, meta?:array<string,mixed>} */
    public function resolveBranchByCode(int $companyId, string $code): array
    {
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'invalid_company_id'];
        }
        if (!$this->hasPrefix($code, self::BRANCH_CODE_PREFIXES)) {
            return ['ok' => false, 'error' => 'code_not_qa_prefixed'];
        }
        $stmt = $this->db->prepare(
            'SELECT id, code, name, company_id, status FROM rateb_branches
             WHERE company_id = :cid AND code = :code LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'code' => $code]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        return [
            'ok' => true,
            'id' => (int) $row['id'],
            'meta' => [
                'code' => (string) $row['code'],
                'name' => (string) ($row['name'] ?? ''),
                'company_id' => (int) $row['company_id'],
                'status' => (string) ($row['status'] ?? ''),
            ],
        ];
    }

    /** @return array{ok:bool, id?:int, error?:string, meta?:array<string,mixed>} */
    public function resolveSubscriptionByCompanyId(int $companyId): array
    {
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'invalid_company_id'];
        }
        $co = $this->db->prepare('SELECT id, slug FROM rateb_companies WHERE id = :id LIMIT 1');
        $co->execute(['id' => $companyId]);
        $company = $co->fetch(\PDO::FETCH_ASSOC);
        if (!$company || !$this->hasPrefix((string) $company['slug'], self::COMPANY_SLUG_PREFIXES)) {
            return ['ok' => false, 'error' => 'company_not_qa_prefixed'];
        }
        $stmt = $this->db->prepare(
            'SELECT id, company_id, plan_id, status FROM rateb_subscriptions WHERE company_id = :cid ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        return [
            'ok' => true,
            'id' => (int) $row['id'],
            'meta' => [
                'company_id' => (int) $row['company_id'],
                'plan_id' => (int) ($row['plan_id'] ?? 0),
                'status' => (string) ($row['status'] ?? ''),
            ],
        ];
    }

    /** @var list<string> */
    private const TICKET_NO_PREFIXES = ['QA-TICKET-'];

    /** @return array{ok:bool, id?:int, error?:string, meta?:array<string,mixed>} */
    public function resolveSupportTicketByTicketNo(string $ticketNo): array
    {
        if (!$this->hasPrefix($ticketNo, self::TICKET_NO_PREFIXES)) {
            return ['ok' => false, 'error' => 'ticket_not_qa_prefixed'];
        }
        $stmt = $this->db->prepare(
            'SELECT id, ticket_no, subject, status FROM rateb_support_tickets WHERE ticket_no = :tn LIMIT 1'
        );
        $stmt->execute(['tn' => $ticketNo]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        return [
            'ok' => true,
            'id' => (int) $row['id'],
            'meta' => [
                'ticket_no' => (string) $row['ticket_no'],
                'subject' => (string) ($row['subject'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
            ],
        ];
    }

    /** @param list<string> $prefixes */
    private function hasPrefix(string $value, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return true;
            }
        }
        return false;
    }
}

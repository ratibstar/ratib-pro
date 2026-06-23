<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Core\Security;

use Ratib\ContactCenter\App\Core\Database;

/**
 * Maps Control Panel session (username / email) to RCC users and agents.
 */
final class ControlPanelIdentityResolver
{
    /** @return list<string> */
    public static function candidateEmailsFromSession(): array
    {
        $out = [];
        foreach (['control_user_email', 'control_email'] as $key) {
            $v = trim((string) ($_SESSION[$key] ?? ''));
            if ($v !== '' && filter_var($v, FILTER_VALIDATE_EMAIL)) {
                $out[] = strtolower($v);
            }
        }

        $username = trim((string) ($_SESSION['control_username'] ?? ''));
        if ($username === '') {
            return array_values(array_unique($out));
        }

        if (strpos($username, '@') !== false && filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $out[] = strtolower($username);
            return array_values(array_unique($out));
        }

        $domain = self::agentEmailDomain();
        $out[] = strtolower($username . '@' . $domain);

        return array_values(array_unique($out));
    }

    public static function agentEmailDomain(): string
    {
        $fromEnv = getenv('RCC_CP_AGENT_EMAIL_DOMAIN');
        if ($fromEnv !== false && trim((string) $fromEnv) !== '') {
            return strtolower(trim((string) $fromEnv));
        }

        foreach (['CONTROL_SITE_URL', 'APP_URL', 'SITE_URL'] as $const) {
            if (defined($const)) {
                $host = self::hostFromUrl((string) constant($const));
                if ($host !== '') {
                    return $host;
                }
            }
        }

        $host = self::hostFromUrl((string) (getenv('APP_URL') ?: getenv('CONTROL_SITE_URL') ?: ''));
        return $host !== '' ? $host : 'rateb.sa';
    }

    public static function resolveAgentId(int $tenantId): int
    {
        $sessionAgentId = (int) ($_SESSION['rcc_agent_id'] ?? 0);
        if ($sessionAgentId > 0) {
            return $sessionAgentId;
        }

        $emails = self::candidateEmailsFromSession();
        if ($emails === []) {
            return 0;
        }

        try {
            return self::queryAgentId($tenantId, $emails);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function resolveUserId(int $tenantId): ?int
    {
        $emails = self::candidateEmailsFromSession();
        if ($emails === []) {
            return null;
        }

        try {
            $pdo = Database::connection();
            $placeholders = implode(',', array_fill(0, count($emails), '?'));
            $stmt = $pdo->prepare(
                "SELECT id FROM rcc_users
                 WHERE tenant_id = ? AND status = 'active' AND LOWER(email) IN ({$placeholders})
                 LIMIT 1"
            );
            $params = array_merge([$tenantId], $emails);
            $stmt->execute($params);
            $id = $stmt->fetchColumn();

            return $id !== false ? (int) $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @param list<string> $emails */
    private static function queryAgentId(int $tenantId, array $emails): int
    {
        $pdo = Database::connection();
        $placeholders = implode(',', array_fill(0, count($emails), '?'));
        $activeSql = "(a.status = 'active' OR TRIM(COALESCE(a.status, '')) = '')";
        $stmt = $pdo->prepare(
            "SELECT a.id FROM rcc_agents a
             INNER JOIN rcc_users u ON u.id = a.user_id AND u.tenant_id = a.tenant_id
             WHERE a.tenant_id = ? AND {$activeSql}
               AND (LOWER(u.email) IN ({$placeholders}) OR LOWER(COALESCE(a.email, '')) IN ({$placeholders}))
             ORDER BY a.id ASC
             LIMIT 1"
        );
        $params = array_merge([$tenantId], $emails, $emails);
        $stmt->execute($params);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : 0;
    }

    private static function hostFromUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        $host = (string) parse_url($url, PHP_URL_HOST);
        return strtolower(trim($host));
    }
}

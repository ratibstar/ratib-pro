<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Mirror agency/dedicated ERP support tickets into the main platform ERP DB (rateb.sa)
 * so platform Super Admins get instant flash alerts and list visibility.
 */
final class SupportTicketPlatformMirrorService
{
    public const MARKER_PREFIX = '[rateb_agency_ticket:';

    /**
     * @param array<string, mixed> $ticket
     */
    public function mirrorNewTicketFromAgency(int $localTicketId, array $ticket): void
    {
        if ($localTicketId < 1) {
            return;
        }
        if (!function_exists('rateb_is_agency_erp_host') || !rateb_is_agency_erp_host()) {
            return;
        }
        if (function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()) {
            return;
        }

        try {
            $agencySvc = new AgencyErpMigrationService();
            $platformCfg = $agencySvc->platformErpDatabaseConfig();
            $platformDb = trim((string) ($platformCfg['db'] ?? ''));
            $localDb = defined('RATEB_DB_NAME') ? trim((string) RATEB_DB_NAME) : '';
            if ($platformDb === '' || ($localDb !== '' && strcasecmp($platformDb, $localDb) === 0)) {
                return;
            }

            $pdo = $agencySvc->pdoFromConfig($platformCfg);
            $agencyId = defined('RATEB_ERP_AGENCY_ID') ? (int) RATEB_ERP_AGENCY_ID : 0;
            $ticket['id'] = $localTicketId;
            $this->upsertMirroredTicketInto($pdo, $agencyId, $this->agencyLabel($agencyId), $ticket, true);
        } catch (\Throwable $e) {
            error_log('SupportTicketPlatformMirrorService: ' . $e->getMessage());
        }
    }

    /**
     * Platform Super Admin: pull open tickets from agency ERP DBs into rateb.sa.
     */
    public function pullOpenTicketsFromAgencies(int $perAgency = 25): int
    {
        if (function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host()) {
            return 0;
        }
        if (!function_exists('rateb_is_platform_oversight_host') || !rateb_is_platform_oversight_host()) {
            return 0;
        }
        if (!function_exists('rateb_is_super_admin') || !rateb_is_super_admin()) {
            return 0;
        }

        static $ran = false;
        if ($ran) {
            return 0;
        }
        $ran = true;

        $imported = 0;
        try {
            $agencySvc = new AgencyErpMigrationService();
            $platformPdo = \Rateb\App\Core\Database::connection();
            $limit = max(5, min(50, $perAgency));
            foreach ($agencySvc->listAgencies(false) as $agency) {
                $agencyId = (int) ($agency['id'] ?? 0);
                if ($agencyId < 1) {
                    continue;
                }
                try {
                    $cfg = $agencySvc->agencyDatabaseConfig($agency);
                    if (trim((string) ($cfg['db'] ?? '')) === '') {
                        continue;
                    }
                    $agencyPdo = $agencySvc->pdoFromConfig($cfg);
                    $stmt = $agencyPdo->query(
                        'SELECT * FROM rateb_support_tickets
                         WHERE status IN (\'open\', \'in_progress\')
                         ORDER BY id DESC LIMIT ' . $limit
                    );
                    if ($stmt === false) {
                        continue;
                    }
                    $agencyLabel = trim((string) ($agency['name'] ?? ''));
                    if ($agencyLabel === '') {
                        $site = trim((string) ($agency['site_url'] ?? ''));
                        $agencyLabel = $site !== '' ? $site : ('agency#' . $agencyId);
                    }
                    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                        if (!is_array($row)) {
                            continue;
                        }
                        $row['company_name'] = $agencyLabel;
                        $row['platform_company_hint'] = (int) ($agency['erp_company_id'] ?? 0);
                        $newId = $this->upsertMirroredTicketInto($platformPdo, $agencyId, $agencyLabel, $row, true);
                        if ($newId > 0) {
                            $imported++;
                        }
                    }
                } catch (\Throwable $eAgency) {
                    error_log('support ticket agency pull #' . $agencyId . ': ' . $eAgency->getMessage());
                }
            }
        } catch (\Throwable $e) {
            error_log('SupportTicketPlatformMirrorService pull: ' . $e->getMessage());
        }

        return $imported;
    }

    /**
     * Insert mirrored agency ticket into target PDO (platform). Returns new platform id, or 0 if exists/skip.
     *
     * @param array<string, mixed> $ticket
     */
    private function upsertMirroredTicketInto(
        \PDO $pdo,
        int $agencyId,
        string $agencyLabel,
        array $ticket,
        bool $notify
    ): int {
        $localTicketId = (int) ($ticket['id'] ?? 0);
        if ($localTicketId < 1) {
            return 0;
        }
        $marker = self::MARKER_PREFIX . $agencyId . ':' . $localTicketId . ']';

        $exists = $pdo->prepare(
            'SELECT id FROM rateb_support_tickets WHERE message LIKE :m LIMIT 1'
        );
        $exists->execute(['m' => '%' . $marker . '%']);
        if ($exists->fetch(\PDO::FETCH_ASSOC)) {
            return 0;
        }

        $companyId = $this->resolvePlatformCompanyId($pdo, $ticket, $agencyId);
        $ticketNo = $this->allocatePlatformTicketNo($pdo, $agencyId, (string) ($ticket['ticket_no'] ?? ''));
        $subject = trim((string) ($ticket['subject'] ?? ''));
        if ($subject === '') {
            $subject = 'Support ticket';
        }
        $body = trim((string) ($ticket['message'] ?? ''));
        $companyName = trim((string) ($ticket['company_name'] ?? ''));
        if ($companyName === '' && (int) ($ticket['company_id'] ?? 0) > 0) {
            try {
                $local = (new \Rateb\App\Models\SupportTicket())->queryOne(
                    'SELECT name FROM rateb_companies WHERE id = :id LIMIT 1',
                    ['id' => (int) $ticket['company_id']]
                );
                // Only works when ticket is from same DB — ignore on platform pull.
                $companyName = trim((string) ($local['name'] ?? ''));
            } catch (\Throwable $e) {
                $companyName = '';
            }
        }
        $header = trim(
            ($agencyLabel !== '' ? ('الوكالة: ' . $agencyLabel) : '')
            . ($companyName !== '' ? (($agencyLabel !== '' ? ' — ' : '') . 'الشركة: ' . $companyName) : '')
        );
        $message = ($header !== '' ? $header . "\n\n" : '')
            . ($body !== '' ? $body : '—')
            . "\n\n" . $marker;

        $priority = (string) ($ticket['priority'] ?? 'medium');
        if (!in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
            $priority = 'medium';
        }
        $status = (string) ($ticket['status'] ?? 'open');
        if (!in_array($status, ['open', 'in_progress', 'resolved', 'closed'], true)) {
            $status = 'open';
        }

        $createdAt = trim((string) ($ticket['created_at'] ?? ''));
        if ($createdAt === '' || !preg_match('/^\d{4}-\d{2}-\d{2}/', $createdAt)) {
            $createdAt = date('Y-m-d H:i:s');
        }

        $ins = $pdo->prepare(
            'INSERT INTO rateb_support_tickets
                (company_id, user_id, ticket_no, subject, priority, status, message, created_at)
             VALUES
                (:cid, NULL, :tno, :subj, :pri, :st, :msg, :ca)'
        );
        $ins->execute([
            'cid' => $companyId > 0 ? $companyId : null,
            'tno' => $ticketNo,
            'subj' => mb_substr($subject, 0, 255),
            'pri' => $priority,
            'st' => $status,
            'msg' => $message,
            'ca' => $createdAt,
        ]);
        $platformTicketId = (int) $pdo->lastInsertId();
        if ($platformTicketId < 1) {
            return 0;
        }

        if ($notify) {
            $this->notifyPlatformSuperAdmins(
                $pdo,
                $platformTicketId,
                $ticketNo,
                $companyId,
                $companyName !== '' ? $companyName : $agencyLabel,
                $subject
            );
        }

        return $platformTicketId;
    }

    /**
     * @param array<string, mixed> $ticket
     */
    private function resolvePlatformCompanyId(\PDO $pdo, array $ticket, int $agencyId): int
    {
        $hint = (int) ($ticket['platform_company_hint'] ?? 0);
        if ($hint > 0) {
            try {
                $v = $pdo->prepare('SELECT id FROM rateb_companies WHERE id = :id LIMIT 1');
                $v->execute(['id' => $hint]);
                if ($v->fetch(\PDO::FETCH_ASSOC)) {
                    return $hint;
                }
            } catch (\Throwable $e) {
                // fall through
            }
        }
        if ($agencyId > 0 && function_exists('rateb_agency_lookup_connection')) {
            try {
                $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
                $lookup = dirname($root) . '/config/env/agency_lookup.php';
                if (is_file($lookup)) {
                    require_once $lookup;
                }
                if (function_exists('rateb_agency_lookup_connection')) {
                    $conn = rateb_agency_lookup_connection();
                    if ($conn instanceof \mysqli) {
                        $chk = @$conn->query("SHOW COLUMNS FROM control_agencies LIKE 'erp_company_id'");
                        if ($chk && $chk->num_rows > 0) {
                            $stmt = $conn->prepare('SELECT erp_company_id FROM control_agencies WHERE id = ? LIMIT 1');
                            if ($stmt) {
                                $stmt->bind_param('i', $agencyId);
                                $stmt->execute();
                                $res = $stmt->get_result();
                                $row = $res ? $res->fetch_assoc() : null;
                                $linked = (int) ($row['erp_company_id'] ?? 0);
                                $stmt->close();
                                if ($linked > 0) {
                                    $v = $pdo->prepare('SELECT id FROM rateb_companies WHERE id = :id LIMIT 1');
                                    $v->execute(['id' => $linked]);
                                    if ($v->fetch(\PDO::FETCH_ASSOC)) {
                                        return $linked;
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // fall through
            }
        }

        $name = trim((string) ($ticket['company_name'] ?? ''));
        if ($name === '' && (int) ($ticket['company_id'] ?? 0) > 0) {
            try {
                $local = (new \Rateb\App\Models\SupportTicket())->queryOne(
                    'SELECT name FROM rateb_companies WHERE id = :id LIMIT 1',
                    ['id' => (int) $ticket['company_id']]
                );
                $name = trim((string) ($local['name'] ?? ''));
            } catch (\Throwable $e) {
                $name = '';
            }
        }
        if ($name !== '') {
            try {
                $stmt = $pdo->prepare('SELECT id FROM rateb_companies WHERE name = :n LIMIT 1');
                $stmt->execute(['n' => $name]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    return $id;
                }
            } catch (\Throwable $e) {
                // fall through
            }
        }

        return 0;
    }

    private function agencyLabel(int $agencyId): string
    {
        $host = function_exists('rateb_normalize_http_host')
            ? rateb_normalize_http_host((string) ($_SERVER['HTTP_HOST'] ?? ''))
            : (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host !== '') {
            return $host;
        }

        return $agencyId > 0 ? ('agency#' . $agencyId) : 'agency';
    }

    private function allocatePlatformTicketNo(\PDO $pdo, int $agencyId, string $original): string
    {
        $original = trim($original);
        $preferred = $agencyId > 0
            ? ('A' . $agencyId . '-' . ($original !== '' ? $original : ('ST-' . time())))
            : ($original !== '' ? $original : ('ST-' . time()));
        $preferred = mb_substr($preferred, 0, 50);

        try {
            $chk = $pdo->prepare('SELECT id FROM rateb_support_tickets WHERE ticket_no = :no LIMIT 1');
            $chk->execute(['no' => $preferred]);
            if (!$chk->fetch(\PDO::FETCH_ASSOC)) {
                return $preferred;
            }
        } catch (\Throwable $e) {
            return $preferred;
        }

        return mb_substr($preferred . '-' . substr((string) time(), -4), 0, 50);
    }

    private function notifyPlatformSuperAdmins(
        \PDO $pdo,
        int $platformTicketId,
        string $ticketNo,
        int $companyId,
        string $companyName,
        string $subject
    ): void {
        $title = __('support_ticket_alert_new_title', [
            'ticket' => $ticketNo,
            'company' => $companyName !== '' ? $companyName : '—',
        ]);
        $message = __('support_ticket_alert_new_body', [
            'ticket' => $ticketNo,
            'company' => $companyName !== '' ? $companyName : '—',
            'subject' => $subject,
        ]);

        try {
            $admins = $pdo->query(
                "SELECT id FROM rateb_users WHERE is_super_admin = 1 AND status = 'active' ORDER BY id ASC LIMIT 50"
            );
            if ($admins === false) {
                return;
            }
            $ins = $pdo->prepare(
                'INSERT INTO rateb_notifications
                    (company_id, user_id, title, message, type, trigger_type, entity_type, entity_id, is_read, created_at)
                 VALUES
                    (:cid, :uid, :title, :msg, :type, :tt, :et, :eid, 0, NOW())'
            );
            $insSimple = null;
            while ($row = $admins->fetch(\PDO::FETCH_ASSOC)) {
                $uid = (int) ($row['id'] ?? 0);
                if ($uid < 1) {
                    continue;
                }
                $params = [
                    'cid' => $companyId > 0 ? $companyId : null,
                    'uid' => $uid,
                    'title' => $title,
                    'msg' => $message,
                    'type' => 'warning',
                    'tt' => SupportTicketAlertService::TRIGGER_OPEN,
                    'et' => SupportTicketAlertService::ENTITY,
                    'eid' => $platformTicketId,
                ];
                try {
                    $ins->execute($params);
                } catch (\Throwable $e) {
                    if ($insSimple === null) {
                        $insSimple = $pdo->prepare(
                            'INSERT INTO rateb_notifications
                                (company_id, user_id, title, message, type, is_read, created_at)
                             VALUES
                                (:cid, :uid, :title, :msg, :type, 0, NOW())'
                        );
                    }
                    $insSimple->execute([
                        'cid' => $params['cid'],
                        'uid' => $params['uid'],
                        'title' => $params['title'],
                        'msg' => $params['msg'],
                        'type' => $params['type'],
                    ]);
                }
            }
        } catch (\Throwable $e) {
            error_log('SupportTicketPlatformMirrorService notify: ' . $e->getMessage());
        }
    }
}

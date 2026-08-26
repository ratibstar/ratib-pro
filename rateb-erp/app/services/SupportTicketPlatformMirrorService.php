<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Bidirectional support-ticket sync between agency ERP DBs and platform rateb.sa.
 *
 * Agency → platform: mirrorNewTicketFromAgency / pullOpenTicketsFromAgencies
 * Platform → agency: pushStaffReplyToAgency / pushTicketFieldsToAgency
 */
final class SupportTicketPlatformMirrorService
{
    public const MARKER_PREFIX = '[rateb_agency_ticket:';
    public const REPLY_MARKER_PREFIX = '[rateb_platform_reply:';

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
     * Platform Super Admin reply → write into the originating agency ticket thread.
     *
     * @param array<string, mixed> $platformTicket
     */
    public function pushStaffReplyToAgency(
        int $platformTicketId,
        array $platformTicket,
        string $body,
        int $platformReplyId = 0
    ): bool {
        $body = trim($body);
        if ($platformTicketId < 1 || $body === '') {
            return false;
        }
        if (!$this->isPlatformPushContext()) {
            return false;
        }

        $link = $this->resolveAgencyLinkFromTicket($platformTicket);
        if ($link === null) {
            return false;
        }

        try {
            $agencyPdo = $link['pdo'];
            $localTicketId = $link['local_ticket_id'];
            $local = $this->fetchAgencyTicket($agencyPdo, $localTicketId);
            if ($local === null) {
                return false;
            }

            $marker = self::REPLY_MARKER_PREFIX . max(0, $platformReplyId) . ':' . $platformTicketId . ']';
            if ($platformReplyId > 0) {
                $dup = $agencyPdo->prepare(
                    'SELECT id FROM rateb_support_ticket_replies
                     WHERE ticket_id = :tid AND body LIKE :m LIMIT 1'
                );
                $dup->execute(['tid' => $localTicketId, 'm' => '%' . $marker . '%']);
                if ($dup->fetch(\PDO::FETCH_ASSOC)) {
                    $this->syncAgencyTicketFields($agencyPdo, $localTicketId, [
                        'status' => (string) ($platformTicket['status'] ?? $local['status'] ?? 'in_progress'),
                        'priority' => (string) ($platformTicket['priority'] ?? $local['priority'] ?? ''),
                        'subject' => (string) ($platformTicket['subject'] ?? $local['subject'] ?? ''),
                    ]);

                    return true;
                }
            }

            $replyBody = $body . "\n\n" . $marker;
            $companyId = (int) ($local['company_id'] ?? 0);
            $ins = $agencyPdo->prepare(
                'INSERT INTO rateb_support_ticket_replies
                    (ticket_id, company_id, user_id, is_staff, body, created_at)
                 VALUES
                    (:tid, :cid, NULL, 1, :body, NOW())'
            );
            $ins->execute([
                'tid' => $localTicketId,
                'cid' => $companyId > 0 ? $companyId : null,
                'body' => $replyBody,
            ]);

            $status = (string) ($platformTicket['status'] ?? '');
            if ($status === '' || $status === 'open') {
                $status = 'in_progress';
            }
            $this->syncAgencyTicketFields($agencyPdo, $localTicketId, [
                'status' => $status,
                'priority' => (string) ($platformTicket['priority'] ?? $local['priority'] ?? ''),
                'subject' => (string) ($platformTicket['subject'] ?? $local['subject'] ?? ''),
            ]);

            $this->notifyAgencyTicketWatchers(
                $agencyPdo,
                $local,
                (string) ($local['ticket_no'] ?? ('#' . $localTicketId)),
                $body,
                $localTicketId
            );

            return true;
        } catch (\Throwable $e) {
            error_log('SupportTicketPlatformMirrorService push reply: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Platform Super Admin edits (status/priority/subject) → agency ticket row.
     *
     * @param array<string, mixed> $platformTicket
     * @param array<string, mixed> $fields
     */
    public function pushTicketFieldsToAgency(int $platformTicketId, array $platformTicket, array $fields = []): bool
    {
        if ($platformTicketId < 1 || !$this->isPlatformPushContext()) {
            return false;
        }

        $link = $this->resolveAgencyLinkFromTicket($platformTicket);
        if ($link === null) {
            return false;
        }

        $payload = [
            'status' => (string) ($fields['status'] ?? $platformTicket['status'] ?? ''),
            'priority' => (string) ($fields['priority'] ?? $platformTicket['priority'] ?? ''),
            'subject' => (string) ($fields['subject'] ?? $platformTicket['subject'] ?? ''),
        ];

        try {
            return $this->syncAgencyTicketFields($link['pdo'], $link['local_ticket_id'], $payload);
        } catch (\Throwable $e) {
            error_log('SupportTicketPlatformMirrorService push fields: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Agency host: pull Super Admin replies/status from the platform mirror into this local ticket.
     * Heals tickets that were answered on rateb.sa before push-back existed.
     */
    public function pullPlatformUpdatesIntoAgencyTicket(int $localTicketId): int
    {
        if ($localTicketId < 1) {
            return 0;
        }
        if (!function_exists('rateb_is_agency_erp_host') || !rateb_is_agency_erp_host()) {
            return 0;
        }
        if (function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()) {
            return 0;
        }

        $agencyId = $this->resolveLocalAgencyId();

        try {
            $agencySvc = new AgencyErpMigrationService();
            $platformCfg = $agencySvc->platformErpDatabaseConfig();
            $platformDb = trim((string) ($platformCfg['db'] ?? ''));
            $localDb = defined('RATEB_DB_NAME') ? trim((string) RATEB_DB_NAME) : '';
            if ($platformDb === '' || ($localDb !== '' && strcasecmp($platformDb, $localDb) === 0)) {
                return 0;
            }

            $localPdo = \Rateb\App\Core\Database::connection();
            $local = $this->fetchAgencyTicket($localPdo, $localTicketId);
            if ($local === null) {
                return 0;
            }

            $platformPdo = $agencySvc->pdoFromConfig($platformCfg);
            $platformTicket = $this->findPlatformMirrorForLocalTicket(
                $platformPdo,
                $agencyId,
                $localTicketId,
                $local
            );
            if (!is_array($platformTicket)) {
                return 0;
            }

            $platformTicketId = (int) ($platformTicket['id'] ?? 0);
            $imported = 0;
            $this->syncAgencyTicketFields($localPdo, $localTicketId, [
                'status' => (string) ($platformTicket['status'] ?? ''),
                'priority' => (string) ($platformTicket['priority'] ?? ''),
                'subject' => (string) ($platformTicket['subject'] ?? ''),
            ]);

            $replies = $platformPdo->prepare(
                'SELECT id, body, created_at FROM rateb_support_ticket_replies
                 WHERE ticket_id = :tid AND is_staff = 1 ORDER BY id ASC'
            );
            $replies->execute(['tid' => $platformTicketId]);
            while ($reply = $replies->fetch(\PDO::FETCH_ASSOC)) {
                if (!is_array($reply)) {
                    continue;
                }
                $platformReplyId = (int) ($reply['id'] ?? 0);
                $body = trim((string) ($reply['body'] ?? ''));
                if ($body === '') {
                    continue;
                }
                $replyMarker = self::REPLY_MARKER_PREFIX . $platformReplyId . ':' . $platformTicketId . ']';
                $dup = $localPdo->prepare(
                    'SELECT id FROM rateb_support_ticket_replies
                     WHERE ticket_id = :tid AND body LIKE :m LIMIT 1'
                );
                $dup->execute(['tid' => $localTicketId, 'm' => '%' . $replyMarker . '%']);
                if ($dup->fetch(\PDO::FETCH_ASSOC)) {
                    continue;
                }
                $cleanBody = trim(preg_replace(
                    '/\n*\s*' . preg_quote(self::REPLY_MARKER_PREFIX, '/') . '\d+:\d+\]\s*$/u',
                    '',
                    $body
                ) ?? $body);
                // Only skip if an identical *platform-synced* body exists (marker present).
                // Do not skip just because a local agent typed the same short text.
                $dupMarked = $localPdo->prepare(
                    'SELECT id FROM rateb_support_ticket_replies
                     WHERE ticket_id = :tid AND is_staff = 1
                       AND body LIKE :m LIMIT 1'
                );
                $dupMarked->execute([
                    'tid' => $localTicketId,
                    'm' => '%' . self::REPLY_MARKER_PREFIX . '%',
                ]);
                // Fall through — insert even if a plain local "قيد" exists.
                unset($dupMarked);

                $companyId = (int) ($local['company_id'] ?? 0);
                $ins = $localPdo->prepare(
                    'INSERT INTO rateb_support_ticket_replies
                        (ticket_id, company_id, user_id, is_staff, body, created_at)
                     VALUES
                        (:tid, :cid, NULL, 1, :body, :ca)'
                );
                $createdAt = trim((string) ($reply['created_at'] ?? ''));
                if ($createdAt === '' || !preg_match('/^\d{4}-\d{2}-\d{2}/', $createdAt)) {
                    $createdAt = date('Y-m-d H:i:s');
                }
                $ins->execute([
                    'tid' => $localTicketId,
                    'cid' => $companyId > 0 ? $companyId : null,
                    'body' => $cleanBody . "\n\n" . $replyMarker,
                    'ca' => $createdAt,
                ]);
                $imported++;
            }

            return $imported;
        } catch (\Throwable $e) {
            error_log('SupportTicketPlatformMirrorService pull into agency: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Agency staff reply → append onto the platform mirror ticket so Super Admin sees the thread.
     *
     * @param array<string, mixed> $localTicket
     */
    public function mirrorAgencyReplyToPlatform(
        int $localTicketId,
        int $localReplyId,
        string $body,
        array $localTicket
    ): bool {
        $body = trim($body);
        if ($localTicketId < 1 || $body === '') {
            return false;
        }
        if (!function_exists('rateb_is_agency_erp_host') || !rateb_is_agency_erp_host()) {
            return false;
        }

        $agencyId = $this->resolveLocalAgencyId();
        if ($agencyId < 1) {
            return false;
        }

        try {
            $agencySvc = new AgencyErpMigrationService();
            $platformCfg = $agencySvc->platformErpDatabaseConfig();
            $platformDb = trim((string) ($platformCfg['db'] ?? ''));
            $localDb = defined('RATEB_DB_NAME') ? trim((string) RATEB_DB_NAME) : '';
            if ($platformDb === '' || ($localDb !== '' && strcasecmp($platformDb, $localDb) === 0)) {
                return false;
            }

            $platformPdo = $agencySvc->pdoFromConfig($platformCfg);
            $platformTicket = $this->findPlatformMirrorForLocalTicket(
                $platformPdo,
                $agencyId,
                $localTicketId,
                $localTicket
            );
            if (!is_array($platformTicket)) {
                // Ensure mirror exists then retry.
                $this->mirrorNewTicketFromAgency($localTicketId, $localTicket);
                $platformTicket = $this->findPlatformMirrorForLocalTicket(
                    $platformPdo,
                    $agencyId,
                    $localTicketId,
                    $localTicket
                );
            }
            if (!is_array($platformTicket)) {
                return false;
            }

            $platformTicketId = (int) ($platformTicket['id'] ?? 0);
            $marker = '[rateb_agency_reply:' . $agencyId . ':' . max(0, $localReplyId) . ']';
            $dup = $platformPdo->prepare(
                'SELECT id FROM rateb_support_ticket_replies
                 WHERE ticket_id = :tid AND body LIKE :m LIMIT 1'
            );
            $dup->execute(['tid' => $platformTicketId, 'm' => '%' . $marker . '%']);
            if ($dup->fetch(\PDO::FETCH_ASSOC)) {
                return true;
            }

            $companyId = (int) ($platformTicket['company_id'] ?? 0);
            $ins = $platformPdo->prepare(
                'INSERT INTO rateb_support_ticket_replies
                    (ticket_id, company_id, user_id, is_staff, body, created_at)
                 VALUES
                    (:tid, :cid, NULL, 0, :body, NOW())'
            );
            $ins->execute([
                'tid' => $platformTicketId,
                'cid' => $companyId > 0 ? $companyId : null,
                'body' => $body . "\n\n" . $marker,
            ]);

            // Keep platform status in sync with agency when agency replies.
            $status = (string) ($localTicket['status'] ?? '');
            if ($status !== '') {
                $this->refreshMirroredTicketFields($platformPdo, $agencyId, array_merge($localTicket, [
                    'id' => $localTicketId,
                    'status' => $status,
                ]));
            }

            return true;
        } catch (\Throwable $e) {
            error_log('SupportTicketPlatformMirrorService mirror agency reply: ' . $e->getMessage());

            return false;
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

        // Once per HTTP request (index + alerts poll are separate requests).
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
                        } else {
                            // Keep existing mirror status/priority in sync with agency.
                            $this->refreshMirroredTicketFields($platformPdo, $agencyId, $row);
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

    private function isPlatformPushContext(): bool
    {
        if (function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host()) {
            return false;
        }
        // rateb.sa Super Admin (oversight host) — primary path
        if (function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()) {
            return true;
        }
        // Fallback: Super Admin session on non-agency host
        return function_exists('rateb_is_super_admin') && rateb_is_super_admin();
    }

    /**
     * @param array<string, mixed> $platformTicket
     * @return array{agency_id:int,local_ticket_id:int,pdo:\PDO}|null
     */
    private function resolveAgencyLinkFromTicket(array $platformTicket): ?array
    {
        $platformTicketId = (int) ($platformTicket['id'] ?? 0);
        $message = (string) ($platformTicket['message'] ?? '');
        $ticketNo = trim((string) ($platformTicket['ticket_no'] ?? ''));
        if (($message === '' || $ticketNo === '') && $platformTicketId > 0) {
            try {
                $stmt = \Rateb\App\Core\Database::connection()->prepare(
                    'SELECT message, ticket_no, status, priority, subject
                     FROM rateb_support_tickets WHERE id = :id LIMIT 1'
                );
                $stmt->execute(['id' => $platformTicketId]);
                $fetched = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (is_array($fetched)) {
                    if ($message === '') {
                        $message = (string) ($fetched['message'] ?? '');
                    }
                    if ($ticketNo === '') {
                        $ticketNo = trim((string) ($fetched['ticket_no'] ?? ''));
                    }
                    $platformTicket = array_merge($fetched, $platformTicket);
                }
            } catch (\Throwable $e) {
                // continue with what we have
            }
        }

        $agencyId = 0;
        $localTicketId = 0;
        if (preg_match('/' . preg_quote(self::MARKER_PREFIX, '/') . '(\d+):(\d+)\]/', $message, $m)) {
            $agencyId = (int) ($m[1] ?? 0);
            $localTicketId = (int) ($m[2] ?? 0);
        }

        // Fallback: platform ticket_no like A34-ST-0001
        $localTicketNo = '';
        if (($agencyId < 1 || $localTicketId < 1) && preg_match('/^A(\d+)-(.+)$/i', $ticketNo, $tm)) {
            $agencyId = (int) ($tm[1] ?? 0);
            $localTicketNo = trim((string) ($tm[2] ?? ''));
        }

        if ($agencyId < 1) {
            error_log('SupportTicketPlatformMirrorService: cannot resolve agency from ticket #' . $platformTicketId . ' no=' . $ticketNo);

            return null;
        }

        try {
            $agencySvc = new AgencyErpMigrationService();
            $agency = $this->findAgencyRow($agencySvc, $agencyId);
            if ($agency === null) {
                error_log('SupportTicketPlatformMirrorService: agency #' . $agencyId . ' not in control_agencies');

                return null;
            }
            $cfg = $agencySvc->agencyDatabaseConfig($agency);
            if (trim((string) ($cfg['db'] ?? '')) === '') {
                error_log('SupportTicketPlatformMirrorService: agency #' . $agencyId . ' has empty ERP db');

                return null;
            }
            $pdo = $agencySvc->pdoFromConfig($cfg);

            if ($localTicketId < 1 && $localTicketNo !== '') {
                $found = $pdo->prepare(
                    'SELECT id FROM rateb_support_tickets WHERE ticket_no = :no ORDER BY id DESC LIMIT 1'
                );
                $found->execute(['no' => $localTicketNo]);
                $row = $found->fetch(\PDO::FETCH_ASSOC);
                $localTicketId = (int) ($row['id'] ?? 0);
            }

            if ($localTicketId < 1) {
                error_log('SupportTicketPlatformMirrorService: local ticket not found for agency #' . $agencyId . ' no=' . $localTicketNo);

                return null;
            }

            return [
                'agency_id' => $agencyId,
                'local_ticket_id' => $localTicketId,
                'pdo' => $pdo,
            ];
        } catch (\Throwable $e) {
            error_log('SupportTicketPlatformMirrorService resolve agency: ' . $e->getMessage());

            return null;
        }
    }

    /** @return array<string, mixed>|null */
    private function findAgencyRow(AgencyErpMigrationService $agencySvc, int $agencyId): ?array
    {
        foreach ($agencySvc->listAgencies(false) as $row) {
            if ((int) ($row['id'] ?? 0) === $agencyId) {
                return $row;
            }
        }
        foreach ($agencySvc->listControlAgencies(false) as $row) {
            if ((int) ($row['id'] ?? 0) === $agencyId) {
                return $row;
            }
        }

        return null;
    }

    private function resolveLocalAgencyId(): int
    {
        if (defined('RATEB_ERP_AGENCY_ID') && (int) RATEB_ERP_AGENCY_ID > 0) {
            return (int) RATEB_ERP_AGENCY_ID;
        }
        try {
            $lookupFile = dirname(defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2)) . '/config/env/agency_lookup.php';
            if (is_file($lookupFile)) {
                require_once $lookupFile;
            }
            if (function_exists('rateb_agency_erp_binding_for_request_host')) {
                $binding = rateb_agency_erp_binding_for_request_host();
                if (is_array($binding)) {
                    $id = (int) ($binding['agency_id'] ?? $binding['id'] ?? 0);
                    if ($id > 0) {
                        return $id;
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return 0;
    }

    /**
     * @param array<string, mixed>|null $localTicket
     * @return array<string, mixed>|null
     */
    private function findPlatformMirrorForLocalTicket(
        \PDO $platformPdo,
        int $agencyId,
        int $localTicketId,
        ?array $localTicket = null
    ): ?array {
        if ($agencyId > 0 && $localTicketId > 0) {
            $marker = self::MARKER_PREFIX . $agencyId . ':' . $localTicketId . ']';
            $stmt = $platformPdo->prepare(
                'SELECT * FROM rateb_support_tickets WHERE message LIKE :m ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute(['m' => '%' . $marker . '%']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return $row;
            }
        }

        // Any agency marker ending with :localId]
        if ($localTicketId > 0) {
            $stmt = $platformPdo->prepare(
                'SELECT * FROM rateb_support_tickets
                 WHERE message LIKE :m ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute(['m' => '%' . self::MARKER_PREFIX . '%:' . $localTicketId . ']%']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return $row;
            }
        }

        $localNo = trim((string) ($localTicket['ticket_no'] ?? ''));
        if ($localNo !== '' && $agencyId > 0) {
            $platformNo = 'A' . $agencyId . '-' . $localNo;
            $stmt = $platformPdo->prepare(
                'SELECT * FROM rateb_support_tickets WHERE ticket_no = :no ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute(['no' => $platformNo]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return $row;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function fetchAgencyTicket(\PDO $pdo, int $localTicketId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM rateb_support_tickets WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $localTicketId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function syncAgencyTicketFields(\PDO $pdo, int $localTicketId, array $fields): bool
    {
        if ($localTicketId < 1) {
            return false;
        }
        $sets = [];
        $params = ['id' => $localTicketId];
        $status = trim((string) ($fields['status'] ?? ''));
        if ($status !== '' && in_array($status, ['open', 'in_progress', 'resolved', 'closed'], true)) {
            $sets[] = 'status = :status';
            $params['status'] = $status;
        }
        $priority = trim((string) ($fields['priority'] ?? ''));
        if ($priority !== '' && in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
            $sets[] = 'priority = :priority';
            $params['priority'] = $priority;
        }
        $subject = trim((string) ($fields['subject'] ?? ''));
        if ($subject !== '') {
            $sets[] = 'subject = :subject';
            $params['subject'] = mb_substr($subject, 0, 255);
        }
        if ($sets === []) {
            return false;
        }
        $params['id'] = $localTicketId;
        $base = 'UPDATE rateb_support_tickets SET ' . implode(', ', $sets);
        try {
            $pdo->prepare($base . ', updated_at = NOW() WHERE id = :id')->execute($params);
        } catch (\Throwable $e) {
            $pdo->prepare($base . ' WHERE id = :id')->execute($params);
        }

        return true;
    }

    /**
     * @param array<string, mixed> $localTicket
     */
    private function notifyAgencyTicketWatchers(
        \PDO $pdo,
        array $localTicket,
        string $ticketNo,
        string $replyBody,
        int $localTicketId
    ): void {
        $preview = mb_strlen($replyBody) > 120 ? (mb_substr($replyBody, 0, 117) . '…') : $replyBody;
        $title = __('support_ticket_alert_reply_title', ['ticket' => $ticketNo]);
        $message = __('support_ticket_alert_reply_body', [
            'ticket' => $ticketNo,
            'company' => '',
            'preview' => $preview,
        ]);
        $companyId = (int) ($localTicket['company_id'] ?? 0);
        $creatorId = (int) ($localTicket['user_id'] ?? 0);

        $userIds = [];
        if ($creatorId > 0) {
            $userIds[$creatorId] = $creatorId;
        }
        if ($companyId > 0) {
            try {
                $stmt = $pdo->prepare(
                    "SELECT id FROM rateb_users
                     WHERE company_id = :cid AND status = 'active'
                     ORDER BY id ASC LIMIT 40"
                );
                $stmt->execute(['cid' => $companyId]);
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $uid = (int) ($row['id'] ?? 0);
                    if ($uid > 0) {
                        $userIds[$uid] = $uid;
                    }
                }
            } catch (\Throwable $e) {
                // best-effort
            }
        }
        if ($userIds === []) {
            try {
                $admins = $pdo->query(
                    "SELECT id FROM rateb_users
                     WHERE is_super_admin = 1 AND status = 'active'
                     ORDER BY id ASC LIMIT 20"
                );
                if ($admins !== false) {
                    while ($row = $admins->fetch(\PDO::FETCH_ASSOC)) {
                        $uid = (int) ($row['id'] ?? 0);
                        if ($uid > 0) {
                            $userIds[$uid] = $uid;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // best-effort
            }
        }

        $ins = null;
        $insSimple = null;
        foreach ($userIds as $uid) {
            $params = [
                'cid' => $companyId > 0 ? $companyId : null,
                'uid' => $uid,
                'title' => $title,
                'msg' => $message,
                'type' => 'info',
                'tt' => SupportTicketAlertService::TRIGGER_REPLY,
                'et' => SupportTicketAlertService::ENTITY,
                'eid' => $localTicketId,
            ];
            try {
                if ($ins === null) {
                    $ins = $pdo->prepare(
                        'INSERT INTO rateb_notifications
                            (company_id, user_id, title, message, type, trigger_type, entity_type, entity_id, is_read, created_at)
                         VALUES
                            (:cid, :uid, :title, :msg, :type, :tt, :et, :eid, 0, NOW())'
                    );
                }
                $ins->execute($params);
            } catch (\Throwable $e) {
                try {
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
                } catch (\Throwable $e2) {
                    // ignore
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $agencyTicket
     */
    private function refreshMirroredTicketFields(\PDO $platformPdo, int $agencyId, array $agencyTicket): void
    {
        $localId = (int) ($agencyTicket['id'] ?? 0);
        if ($localId < 1 || $agencyId < 1) {
            return;
        }
        $marker = self::MARKER_PREFIX . $agencyId . ':' . $localId . ']';
        $status = (string) ($agencyTicket['status'] ?? '');
        $priority = (string) ($agencyTicket['priority'] ?? '');
        $subject = trim((string) ($agencyTicket['subject'] ?? ''));
        $sets = [];
        $params = ['m' => '%' . $marker . '%'];
        if (in_array($status, ['open', 'in_progress', 'resolved', 'closed'], true)) {
            $sets[] = 'status = :status';
            $params['status'] = $status;
        }
        if (in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
            $sets[] = 'priority = :priority';
            $params['priority'] = $priority;
        }
        if ($subject !== '') {
            $sets[] = 'subject = :subject';
            $params['subject'] = mb_substr($subject, 0, 255);
        }
        if ($sets === []) {
            return;
        }
        try {
            $platformPdo->prepare(
                'UPDATE rateb_support_tickets SET ' . implode(', ', $sets)
                . ' WHERE message LIKE :m LIMIT 1'
            )->execute($params);
        } catch (\Throwable $e) {
            // best-effort
        }
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

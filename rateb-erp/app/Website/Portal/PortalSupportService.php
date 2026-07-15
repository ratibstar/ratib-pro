<?php
declare(strict_types=1);

namespace Rateb\App\Website\Portal;

use Rateb\App\Core\TenantContext;
use Rateb\App\Website\TenantWebsiteRepository;

/**
 * Phase WEBSITE-07 — Support tickets via existing SupportTicket model (no duplicate logic).
 */
final class PortalSupportService
{
    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, ticket_id?: int, error?: string}
     */
    public function createTicket(array $portalUser, array $data): array
    {
        $subject = trim((string) ($data['subject'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));
        if ($subject === '' || $message === '') {
            return ['ok' => false, 'error' => 'subject_message_required'];
        }
        $priority = (string) ($data['priority'] ?? 'normal');
        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            $priority = 'normal';
        }

        TenantContext::setCompanyId($this->repo->companyId());
        $ticketId = 0;
        try {
            if (class_exists(\Rateb\App\Models\SupportTicket::class)) {
                $ticketNo = 'WP-' . strtoupper(bin2hex(random_bytes(3)));
                $ticketId = (new \Rateb\App\Models\SupportTicket())->create([
                    'company_id' => $this->repo->companyId(),
                    'ticket_no' => $ticketNo,
                    'subject' => $subject,
                    'priority' => $priority,
                    'status' => 'open',
                    'message' => $message . "\n\n[portal:" . ($portalUser['portal_type'] ?? '') . ' user:' . ($portalUser['email'] ?? '') . ']',
                ]);
            } else {
                return ['ok' => false, 'error' => 'support_unavailable'];
            }
        } catch (\Throwable $e) {
            error_log('PortalSupportService: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'ticket_failed'];
        }

        $this->repo->execute(
            'INSERT INTO rateb_website_portal_ticket_links (company_id, portal_user_id, support_ticket_id)
             VALUES (:cid, :uid, :tid)',
            [
                'cid' => $this->repo->companyId(),
                'uid' => (int) $portalUser['id'],
                'tid' => $ticketId,
            ]
        );

        return ['ok' => true, 'ticket_id' => $ticketId];
    }

    /** @return list<array<string, mixed>> */
    public function ticketsForUser(int $portalUserId): array
    {
        [$where, $params] = $this->repo->companyWhere('l');
        $params['uid'] = $portalUserId;

        return $this->repo->fetchAll(
            "SELECT t.*
             FROM rateb_website_portal_ticket_links l
             INNER JOIN rateb_support_tickets t ON t.id = l.support_ticket_id
             WHERE {$where} AND l.portal_user_id = :uid
             ORDER BY t.id DESC LIMIT 100",
            $params
        );
    }

    /** @return array{ok: bool, error?: string} */
    public function addReply(array $portalUser, int $ticketId, string $body, ?array $file = null): array
    {
        $body = trim($body);
        if ($body === '') {
            return ['ok' => false, 'error' => 'message_required'];
        }
        if (!$this->userOwnsTicket((int) $portalUser['id'], $ticketId)) {
            return ['ok' => false, 'error' => 'ticket_not_found'];
        }
        $mediaId = null;
        $path = null;
        if ($file !== null && !empty($file['tmp_name'])) {
            $upload = (new \Rateb\App\Website\TenantMediaService($this->repo))->upload($file);
            if (($upload['ok'] ?? false) === true) {
                $mediaId = (int) ($upload['id'] ?? 0) ?: null;
                $path = (string) ($upload['path'] ?? '');
            }
        }
        $this->repo->execute(
            'INSERT INTO rateb_website_portal_ticket_replies
             (company_id, portal_user_id, support_ticket_id, body, attachment_media_id, attachment_path)
             VALUES (:cid, :uid, :tid, :body, :mid, :path)',
            [
                'cid' => $this->repo->companyId(),
                'uid' => (int) $portalUser['id'],
                'tid' => $ticketId,
                'body' => $body,
                'mid' => $mediaId,
                'path' => $path,
            ]
        );

        return ['ok' => true];
    }

    /** @return list<array<string, mixed>> */
    public function repliesForTicket(int $portalUserId, int $ticketId): array
    {
        if (!$this->userOwnsTicket($portalUserId, $ticketId)) {
            return [];
        }
        [$where, $params] = $this->repo->companyWhere();
        $params['tid'] = $ticketId;

        return $this->repo->fetchAll(
            "SELECT * FROM rateb_website_portal_ticket_replies WHERE {$where} AND support_ticket_id = :tid ORDER BY id ASC",
            $params
        );
    }

    private function userOwnsTicket(int $portalUserId, int $ticketId): bool
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['uid'] = $portalUserId;
        $params['tid'] = $ticketId;
        $row = $this->repo->fetchOne(
            "SELECT id FROM rateb_website_portal_ticket_links
             WHERE {$where} AND portal_user_id = :uid AND support_ticket_id = :tid LIMIT 1",
            $params
        );

        return $row !== null;
    }
}

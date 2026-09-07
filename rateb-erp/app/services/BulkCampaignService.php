<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use PDO;

/**
 * Bulk email campaigns on top of the existing mail stack.
 *
 * HTTP only materialises the recipient list (rateb_cms_campaign_recipients).
 * Delivery always goes through rateb_notification_queue + QueueWorkerService,
 * driven by bin/erp-cron.php — nothing is sent from a web request.
 */
final class BulkCampaignService
{
    public const AUDIENCES = ['subscribers', 'companies', 'crm', 'companies_crm'];

    private const RECIPIENTS = 'rateb_cms_campaign_recipients';
    private const CAMPAIGNS = 'rateb_cms_newsletter_campaigns';

    /**
     * Build the recipient list for a campaign and mark it as sending.
     * Safe to call twice: INSERT IGNORE + unique (campaign_id, email).
     *
     * @return array{recipients:int, added:int}
     */
    public function queueCampaign(int $campaignId): array
    {
        $campaign = $this->campaign($campaignId);
        if ($campaign === null) {
            throw new \RuntimeException('Campaign not found');
        }
        $db = Database::connection();
        $audience = $this->normalizeAudience((string) ($campaign['audience'] ?? 'subscribers'));
        $added = 0;
        foreach ($this->audienceStatements($audience, (string) ($campaign['segment_slug'] ?? 'general')) as [$sql, $params]) {
            $stmt = $db->prepare($sql);
            $stmt->execute($params + ['cid' => $campaignId, 'cid2' => $campaignId]);
            $added += $stmt->rowCount();
        }

        $total = $this->countRecipients($campaignId);
        $db->prepare(
            'UPDATE ' . self::CAMPAIGNS . ' SET status = :st, recipient_count = :n WHERE id = :id'
        )->execute(['st' => 'sending', 'n' => $total, 'id' => $campaignId]);

        Logger::info('Campaign recipients built', [
            'campaign_id' => $campaignId,
            'audience' => $audience,
            'added' => $added,
            'total' => $total,
        ]);

        return ['recipients' => $total, 'added' => $added];
    }

    /**
     * Cron step 1 — move pending recipients into the notification queue,
     * capped by the campaign_batch_size setting.
     */
    public function processSending(int $maxPerRun = 0): int
    {
        $budget = $maxPerRun > 0 ? $maxPerRun : $this->settingInt('campaign_batch_size', 200, 1, 5000);
        $db = Database::connection();
        $stmt = $db->query(
            "SELECT * FROM " . self::CAMPAIGNS . " WHERE status = 'sending' ORDER BY id ASC LIMIT 5"
        );
        $campaigns = $stmt !== false ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        $queued = 0;
        foreach ($campaigns as $campaign) {
            if ($budget < 1) {
                break;
            }
            $queued += $this->enqueueBatch($campaign, $budget);
        }

        return $queued;
    }

    /**
     * Cron step 2 — reflect queue outcomes back onto the recipient log and
     * close campaigns whose recipients are all resolved.
     */
    public function syncStatuses(): int
    {
        $db = Database::connection();
        // A failed queue row is only final once the worker dead-lettered it;
        // earlier failures are still eligible for retry.
        $updated = $db->exec(
            'UPDATE ' . self::RECIPIENTS . ' r
             JOIN rateb_notification_queue q ON q.id = r.queue_id
             SET r.status = CASE
                     WHEN q.status = \'sent\' THEN \'sent\'
                     WHEN q.error_code = \'smtp_rcpt\' THEN \'bounced\'
                     ELSE \'failed\'
                 END,
                 r.sent_at = COALESCE(q.sent_at, r.sent_at),
                 r.error_message = LEFT(COALESCE(q.error_code, \'\'), 255)
             WHERE r.status = \'queued\'
               AND (q.status = \'sent\' OR (q.status = \'failed\' AND q.dead_letter_at IS NOT NULL))'
        );

        $stmt = $db->query(
            "SELECT id FROM " . self::CAMPAIGNS . " WHERE status = 'sending' ORDER BY id ASC LIMIT 20"
        );
        foreach ($stmt !== false ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [] as $row) {
            $this->refreshCounters((int) ($row['id'] ?? 0));
        }

        return (int) $updated;
    }

    /** @return array{total:int,pending:int,queued:int,sent:int,failed:int,bounced:int,unsubscribed:int} */
    public function stats(int $campaignId): array
    {
        $out = ['total' => 0, 'pending' => 0, 'queued' => 0, 'sent' => 0, 'failed' => 0, 'bounced' => 0, 'unsubscribed' => 0];
        if ($campaignId < 1) {
            return $out;
        }
        $stmt = Database::connection()->prepare(
            'SELECT status, COUNT(*) AS c FROM ' . self::RECIPIENTS . ' WHERE campaign_id = :id GROUP BY status'
        );
        $stmt->execute(['id' => $campaignId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $status = (string) ($row['status'] ?? '');
            $count = (int) ($row['c'] ?? 0);
            if (isset($out[$status])) {
                $out[$status] = $count;
            }
            $out['total'] += $count;
        }

        return $out;
    }

    /** Suppress an address for all future campaigns. Returns the email when the token is valid. */
    public function unsubscribeByToken(string $token): ?string
    {
        $token = preg_replace('/[^a-f0-9]/i', '', $token) ?? '';
        if (strlen($token) !== 40) {
            return null;
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT campaign_id, email FROM ' . self::RECIPIENTS . ' WHERE unsubscribe_token = :t LIMIT 1'
        );
        $stmt->execute(['t' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $email = strtolower(trim((string) $row['email']));
        $campaignId = (int) ($row['campaign_id'] ?? 0);

        $db->prepare(
            'INSERT INTO rateb_email_unsubscribes (email, campaign_id, source) VALUES (:e, :c, :s)
             ON DUPLICATE KEY UPDATE campaign_id = VALUES(campaign_id)'
        )->execute(['e' => $email, 'c' => $campaignId > 0 ? $campaignId : null, 's' => 'link']);

        // Stop any campaign that has not reached the queue yet.
        $db->prepare(
            'UPDATE ' . self::RECIPIENTS . " SET status = 'unsubscribed' WHERE email = :e AND status = 'pending'"
        )->execute(['e' => $email]);

        // Keep the CMS newsletter list consistent with the suppression list.
        $db->prepare(
            "UPDATE rateb_cms_newsletter_subscribers SET status = 'unsubscribed' WHERE email = :e"
        )->execute(['e' => $email]);

        return $email;
    }

    public static function unsubscribeUrl(string $token): string
    {
        $base = function_exists('rateb_public_url') ? rateb_public_url('site/unsubscribe') : '';
        // Cron runs in CLI where the host is unknown; the link must still be absolute.
        if (preg_match('#^https?://#i', $base) !== 1) {
            $base = 'https://rateb.sa/rateb-erp/public/site/unsubscribe';
        }

        return $base . (strpos($base, '?') === false ? '?' : '&') . 't=' . rawurlencode($token);
    }

    public function normalizeAudience(string $audience): string
    {
        $audience = strtolower(trim($audience));

        return in_array($audience, self::AUDIENCES, true) ? $audience : 'subscribers';
    }

    /** @param array<string,mixed> $campaign */
    private function enqueueBatch(array $campaign, int &$budget): int
    {
        $campaignId = (int) ($campaign['id'] ?? 0);
        if ($campaignId < 1) {
            return 0;
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT id, email, name, unsubscribe_token FROM ' . self::RECIPIENTS . "
             WHERE campaign_id = :id AND status = 'pending'
             ORDER BY id ASC LIMIT " . max(1, $budget)
        );
        $stmt->execute(['id' => $campaignId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            $this->refreshCounters($campaignId);

            return 0;
        }

        $subject = trim((string) ($campaign['subject_ar'] ?? ''));
        if ($subject === '') {
            $subject = trim((string) ($campaign['subject_en'] ?? '')) ?: 'RATEB ERP';
        }
        $baseBody = (string) ($campaign['body_html_ar'] ?? '');
        if (trim($baseBody) === '') {
            $baseBody = (string) ($campaign['body_html_en'] ?? '');
        }

        $insert = $db->prepare(
            'INSERT INTO rateb_notification_queue
                (company_id, channel, recipient, subject, body, status, next_retry_at, unsubscribe_url)
             VALUES (NULL, :ch, :to, :sub, :body, :st, NOW(), :unsub)'
        );
        $mark = $db->prepare(
            'UPDATE ' . self::RECIPIENTS . " SET status = 'queued', queue_id = :qid, queued_at = NOW() WHERE id = :id"
        );

        $queued = 0;
        foreach ($rows as $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $token = (string) ($row['unsubscribe_token'] ?? '');
            if ($email === '' || $token === '') {
                continue;
            }
            $unsubUrl = self::unsubscribeUrl($token);
            $body = $this->personalize($baseBody, (string) ($row['name'] ?? ''), $unsubUrl);
            $insert->execute([
                'ch' => 'email',
                'to' => $email,
                'sub' => mb_substr($subject, 0, 255),
                'body' => mb_strcut($body, 0, 60000),
                'st' => 'pending',
                'unsub' => mb_substr($unsubUrl, 0, 255),
            ]);
            $mark->execute(['qid' => (int) $db->lastInsertId(), 'id' => (int) $row['id']]);
            $queued++;
            $budget--;
        }
        $this->refreshCounters($campaignId);

        return $queued;
    }

    private function personalize(string $body, string $name, string $unsubscribeUrl): string
    {
        $name = trim($name);
        if ($name !== '') {
            $body = '<p>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</p>' . $body;
        }
        $label = (string) __('campaign_unsubscribe_link');
        if ($label === '' || $label === 'campaign_unsubscribe_link') {
            $label = 'إلغاء الاشتراك';
        }
        $safeUrl = htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8');

        return $body
            . '<hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0">'
            . '<p style="font-size:12px;color:#6b7280;margin:0">'
            . '<a href="' . $safeUrl . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a></p>';
    }

    private function refreshCounters(int $campaignId): void
    {
        if ($campaignId < 1) {
            return;
        }
        $stats = $this->stats($campaignId);
        $open = $stats['pending'] + $stats['queued'];
        $db = Database::connection();
        if ($open > 0) {
            $db->prepare(
                'UPDATE ' . self::CAMPAIGNS . '
                 SET recipient_count = :n, sent_count = :s, failed_count = :f, bounced_count = :b
                 WHERE id = :id'
            )->execute([
                'n' => $stats['total'],
                's' => $stats['sent'],
                'f' => $stats['failed'],
                'b' => $stats['bounced'],
                'id' => $campaignId,
            ]);

            return;
        }
        $db->prepare(
            'UPDATE ' . self::CAMPAIGNS . '
             SET recipient_count = :n, sent_count = :s, failed_count = :f, bounced_count = :b,
                 status = :st, sent_at = COALESCE(sent_at, NOW())
             WHERE id = :id'
        )->execute([
            'n' => $stats['total'],
            's' => $stats['sent'],
            'f' => $stats['failed'],
            'b' => $stats['bounced'],
            'st' => ($stats['sent'] === 0 && $stats['total'] > 0) ? 'failed' : 'sent',
            'id' => $campaignId,
        ]);
    }

    private function countRecipients(int $campaignId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS c FROM ' . self::RECIPIENTS . ' WHERE campaign_id = :id'
        );
        $stmt->execute(['id' => $campaignId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['c'] ?? 0);
    }

    /** @return array<string,mixed>|null */
    private function campaign(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $stmt = Database::connection()->prepare('SELECT * FROM ' . self::CAMPAIGNS . ' WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * One INSERT … SELECT per source. Empty / malformed / unsubscribed addresses are
     * filtered in SQL; duplicates are absorbed by uq_campaign_recipient_email.
     *
     * @return list<array{0:string,1:array<string,mixed>}>
     */
    private function audienceStatements(string $audience, string $segment): array
    {
        $sources = [];
        if ($audience === 'subscribers') {
            $sources[] = ['rateb_cms_newsletter_subscribers', 'subscriber', "status = 'active'"];
        }
        if ($audience === 'companies' || $audience === 'companies_crm') {
            $sources[] = ['rateb_companies', 'company', '1 = 1'];
        }
        if ($audience === 'crm' || $audience === 'companies_crm') {
            $sources[] = ['rateb_crm_companies', 'crm_company', '1 = 1'];
            $sources[] = ['rateb_crm_contacts', 'crm_contact', '1 = 1'];
            $sources[] = ['rateb_crm_leads', 'crm_lead', '1 = 1'];
        }

        $out = [];
        foreach ($sources as [$table, $source, $extra]) {
            if (!$this->tableExists($table)) {
                continue;
            }
            $nameExpr = $this->columnExists($table, 'name') ? 's.name' : 'NULL';
            $where = $extra;
            if ($table === 'rateb_cms_newsletter_subscribers' && $segment !== '' && $segment !== 'all') {
                $where .= ' AND s.segment = :segment';
            }
            $sql = 'INSERT IGNORE INTO ' . self::RECIPIENTS . '
                        (campaign_id, email, name, source, source_id, unsubscribe_token, status)
                    SELECT :cid, LOWER(TRIM(s.email)), ' . $nameExpr . ", '" . $source . "', s.id,
                           LEFT(SHA2(CONCAT(:cid2, '|', LOWER(TRIM(s.email)), '|', UUID()), 256), 40), 'pending'
                    FROM " . $table . ' s
                    WHERE ' . $where . "
                      AND s.email IS NOT NULL
                      AND TRIM(s.email) <> ''
                      AND s.email LIKE '%_@_%.__%'
                      AND NOT EXISTS (
                          SELECT 1 FROM rateb_email_unsubscribes u WHERE u.email = LOWER(TRIM(s.email))
                      )";
            $params = [];
            if (strpos($sql, ':segment') !== false) {
                $params['segment'] = $segment;
            }
            $out[] = [$sql, $params];
        }

        return $out;
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }
        try {
            $stmt = Database::connection()->query("SHOW TABLES LIKE '" . str_replace('`', '', $table) . "'");
            $cache[$table] = $stmt !== false && $stmt->fetch() !== false;
        } catch (\Throwable $e) {
            $cache[$table] = false;
        }

        return $cache[$table];
    }

    private function columnExists(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        try {
            $stmt = Database::connection()->query(
                'SHOW COLUMNS FROM `' . str_replace('`', '', $table) . "` LIKE '" . $column . "'"
            );
            $cache[$key] = $stmt !== false && $stmt->fetch() !== false;
        } catch (\Throwable $e) {
            $cache[$key] = false;
        }

        return $cache[$key];
    }

    private function settingInt(string $key, int $default, int $min, int $max): int
    {
        return self::readSettingInt($key, $default, $min, $max);
    }

    /** Shared with QueueWorkerService so throttle settings have one reader. */
    public static function readSettingInt(string $key, int $default, int $min, int $max): int
    {
        try {
            $raw = (new \Rateb\App\Models\SystemSetting())->get($key);
        } catch (\Throwable $e) {
            return $default;
        }
        if ($raw === null || trim($raw) === '' || !ctype_digit(trim($raw))) {
            return $default;
        }

        return max($min, min($max, (int) trim($raw)));
    }
}

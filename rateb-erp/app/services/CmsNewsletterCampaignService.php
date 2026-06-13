<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\CmsNewsletterCampaign;
use Rateb\App\Models\CmsNewsletterSubscriber;
use PDO;

final class CmsNewsletterCampaignService
{
    /** @return array{imported:int, skipped:int} */
    public function importCsv(string $csvContent): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $csvContent) ?: [];
        $model = new CmsNewsletterSubscriber();
        $imported = 0;
        $skipped = 0;
        $header = true;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if ($header && stripos($line, 'email') !== false) {
                $header = false;
                continue;
            }
            $header = false;
            $parts = str_getcsv($line);
            $email = trim((string) ($parts[0] ?? ''));
            $name = trim((string) ($parts[1] ?? ''));
            $segment = trim((string) ($parts[2] ?? 'general')) ?: 'general';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }
            if ($model->findByEmail($email) !== null) {
                $skipped++;
                continue;
            }
            $model->create([
                'email' => $email,
                'name' => $name,
                'segment' => $segment,
                'status' => 'active',
            ]);
            $imported++;
        }
        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /** @return array{sent:int, failed:int} */
    public function dispatchCampaign(int $campaignId): array
    {
        $campaign = (new CmsNewsletterCampaign())->find($campaignId);
        if ($campaign === null) {
            throw new \RuntimeException('Campaign not found');
        }
        $segment = trim((string) ($campaign['segment_slug'] ?? 'general')) ?: 'general';
        $pdo = Database::connection();
        if ($segment === 'all') {
            $stmt = $pdo->query("SELECT email, name FROM rateb_cms_newsletter_subscribers WHERE status = 'active'");
        } else {
            $stmt = $pdo->prepare(
                "SELECT email, name FROM rateb_cms_newsletter_subscribers WHERE status = 'active' AND segment = :s"
            );
            $stmt->execute(['s' => $segment]);
        }
        $subs = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

        $mail = new MailService();
        $sent = 0;
        $failed = 0;
        $localeDefault = 'ar';
        foreach ($subs as $sub) {
            $subject = trim((string) ($campaign['subject_' . $localeDefault] ?? ''));
            if ($subject === '') {
                $subject = (string) ($campaign['subject_en'] ?? 'RATEB ERP Newsletter');
            }
            $body = (string) ($campaign['body_html_' . $localeDefault] ?? $campaign['body_html_en'] ?? '');
            $name = trim((string) ($sub['name'] ?? ''));
            if ($name !== '') {
                $body = '<p>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</p>' . $body;
            }
            $ok = $mail->queue((string) $sub['email'], $subject, $body);
            if ($ok) {
                $sent++;
            } else {
                $failed++;
            }
        }

        (new CmsNewsletterCampaign())->update($campaignId, [
            'status' => $failed > 0 && $sent === 0 ? 'failed' : 'sent',
            'sent_at' => date('Y-m-d H:i:s'),
            'sent_count' => $sent,
        ]);

        return ['sent' => $sent, 'failed' => $failed];
    }
}

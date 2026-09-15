<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CmsNewsletterSubscriber;

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
            // Never re-import an address that asked to unsubscribe.
            if ($this->isSuppressed($email)) {
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

    private function isSuppressed(string $email): bool
    {
        $stmt = \Rateb\App\Core\Database::connection()->prepare(
            'SELECT 1 FROM rateb_email_unsubscribes WHERE email = :e LIMIT 1'
        );
        $stmt->execute(['e' => strtolower(trim($email))]);

        return $stmt->fetch() !== false;
    }
}

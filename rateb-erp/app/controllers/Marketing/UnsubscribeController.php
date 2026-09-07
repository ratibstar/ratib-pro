<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Marketing;

use Rateb\App\Core\Controller;
use Rateb\App\Services\BulkCampaignService;

/**
 * Public unsubscribe endpoint for bulk campaigns.
 *
 * POST is the RFC 8058 One-Click target referenced by the List-Unsubscribe-Post
 * header, so it deliberately has no CSRF check — the 40-char token is the secret.
 */
final class UnsubscribeController extends Controller
{
    public function show(): void
    {
        $token = (string) ($_GET['t'] ?? '');
        $email = (new BulkCampaignService())->unsubscribeByToken($token);
        $this->render($email);
    }

    public function submit(): void
    {
        $token = (string) ($_POST['t'] ?? $_GET['t'] ?? '');
        $email = (new BulkCampaignService())->unsubscribeByToken($token);
        $this->render($email);
    }

    private function render(?string $email): void
    {
        if (!headers_sent()) {
            http_response_code($email === null ? 404 : 200);
            header('Content-Type: text/html; charset=UTF-8');
        }
        $message = $email === null
            ? (string) __('campaign_unsubscribe_invalid')
            : (string) __('campaign_unsubscribe_done', ['email' => $email]);
        echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . htmlspecialchars((string) __('campaign_unsubscribe_link'), ENT_QUOTES, 'UTF-8') . '</title></head>'
            . '<body style="font-family:Tajawal,Arial,sans-serif;padding:40px;text-align:center;color:#111827">'
            . '<p style="font-size:18px">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
            . '</body></html>';
        exit;
    }
}

<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Database;
use Rateb\App\Core\Response;
use Rateb\App\Services\AgentAppsOpsService;
use Rateb\App\Services\MobileAppApkService;

/**
 * Public, read-only published content + offers for one mobile app.
 * Serves apps that do not hold an ERP token (Customer app). Returns only rows the
 * platform published (is_active) — no user, credential or tenant-private data.
 */
final class MobileAppContentController extends Controller
{
    public function show(): void
    {
        $app = MobileAppApkService::normalizeApp((string) ($_GET['app'] ?? 'customer'));
        $slug = strtolower(trim((string) ($_GET['company'] ?? '')));
        $slug = (string) preg_replace('/[^a-z0-9\-]/', '', $slug);

        $companyId = 0;
        $companyEnabled = null;
        if ($slug !== '' && $slug !== 'platform') {
            $company = $this->companyBySlug($slug);
            if ($company !== null) {
                $companyEnabled = (new MobileAppApkService())->isEnabled($app, $company);
                if ($companyEnabled) {
                    $companyId = (int) $company['id'];
                }
            }
        }

        $ops = new AgentAppsOpsService();
        header('Cache-Control: public, max-age=300');
        Response::json([
            'success' => true,
            'app' => $app,
            'company' => $companyId > 0 ? $slug : null,
            'company_enabled' => $companyEnabled,
            'content' => $ops->publishedContent($companyId, $app),
            'offers' => $ops->publishedOffers($companyId, $app),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function companyBySlug(string $slug): ?array
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT id, slug, settings FROM rateb_companies WHERE slug = :slug LIMIT 1');
            $stmt->execute(['slug' => $slug]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            // Companies without a slug get "company-<id>" in their build command.
            if (!is_array($row) && preg_match('/^company-(\d+)$/', $slug, $m)) {
                $stmt = $pdo->prepare('SELECT id, slug, settings FROM rateb_companies WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => (int) $m[1]]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            }

            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            error_log('MobileAppContentController::companyBySlug: ' . $e->getMessage());

            return null;
        }
    }
}

<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

use Rateb\App\Core\Middleware\MiddlewareInterface;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;

/**
 * Applies SubscriptionEnforcementGate when SUBSCRIPTION_ENFORCEMENT_ENABLED=true.
 * Default OFF — no redirects / no behavior change.
 *
 * Tenant-level: no bypass for admin/owner/API roles of the suspended company.
 */
final class SubscriptionEnforcementMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        if (!SubscriptionEnforcementGate::isEnabled()) {
            return true;
        }

        $companyId = TenantContext::companyId();
        $companyIdInt = $companyId !== null ? (int) $companyId : 0;
        if ($companyIdInt < 1) {
            return true;
        }

        try {
            SubscriptionBootstrap::bindForCompany($companyIdInt);
            $context = SubscriptionRuntime::get() ?? SubscriptionContext::absent($companyIdInt);

            $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
            $gate = new SubscriptionEnforcementGate();
            $decision = $gate->decide($context, $uri);

            if ($decision->allowed()) {
                return true;
            }

            $gate->logDecision($decision);

            $path = class_exists(\Rateb\App\Helpers\Request::class)
                ? \Rateb\App\Helpers\Request::resolvePath()
                : (string) (parse_url($uri, PHP_URL_PATH) ?? '');
            $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
            $wantsJson = str_contains($path, '/api/')
                || str_contains($accept, 'application/json')
                || isset($_SERVER['HTTP_X_CSRF_TOKEN']);

            if ($wantsJson) {
                Response::json([
                    'success' => false,
                    'message' => 'Subscription expired',
                    'code' => 'subscription_enforcement',
                    'redirect' => SubscriptionEnforcementGate::renewUrl(),
                ], 403);
                return false;
            }

            $target = SubscriptionEnforcementGate::renewUrl();
            Response::redirect($target);
            return false;
        } catch (\Throwable $e) {
            // Fail open if enforcement itself errors — never brick ERP on gate bugs.
            error_log('RATEB subscription enforcement middleware error: ' . $e->getMessage());
            return true;
        }
    }
}

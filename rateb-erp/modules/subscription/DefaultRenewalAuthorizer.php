<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

use Rateb\App\Core\SessionManager;

/**
 * Default renewal auth: super-admin session or subscriptions.manage / billing.manage.
 * Never trusts client-supplied role claims.
 */
final class DefaultRenewalAuthorizer implements RenewalAuthorizer
{
    public function canRenew(int $actorId): bool
    {
        return self::actorMayRenew($actorId);
    }

    public static function actorMayRenew(int $actorId): bool
    {
        if ($actorId < 1) {
            return false;
        }

        $sessionUserId = 0;
        if (class_exists(SessionManager::class)) {
            $sessionUserId = (int) SessionManager::get('rateb_user_id', 0);
        }
        // Actor must match authenticated session when a web session exists.
        if ($sessionUserId > 0 && $sessionUserId !== $actorId) {
            return false;
        }

        if (class_exists(SessionManager::class) && SessionManager::get('rateb_is_super_admin')) {
            return true;
        }

        if (function_exists('rateb_can')) {
            if (rateb_can('subscriptions.manage') || rateb_can('billing.manage')) {
                return true;
            }
        }

        // CLI / service callers without session: reject by default (inject RenewalAuthorizer in tests).
        if (PHP_SAPI === 'cli' && $sessionUserId < 1) {
            return false;
        }

        return false;
    }
}

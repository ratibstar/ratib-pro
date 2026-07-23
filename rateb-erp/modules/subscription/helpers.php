<?php
declare(strict_types=1);

/**
 * Public read-only accessors for the Subscription Engine request snapshot.
 *
 * Examples:
 *   subscription()
 *   subscription()->status()
 *   subscription()->daysRemaining()
 *   subscription()->isExpired()
 *   subscription()->isInGrace()
 *   subscription()->canAccessERP()
 *
 * Phase 2: exposure only — never used to redirect, block, or notify.
 */

use Rateb\App\Subscription\SubscriptionBootstrap;
use Rateb\App\Subscription\SubscriptionContext;
use Rateb\App\Subscription\SubscriptionRuntime;

if (!function_exists('subscription')) {
    function subscription(): ?SubscriptionContext
    {
        SubscriptionBootstrap::ensureFromTenantContext();
        return SubscriptionRuntime::get();
    }
}

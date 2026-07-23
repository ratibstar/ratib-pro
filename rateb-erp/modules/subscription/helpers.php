<?php
declare(strict_types=1);

/**
 * Public read-only accessors for the Subscription Engine request snapshot.
 *
 * Examples:
 *   subscription()
 *   subscription()->status()
 *   subscription_alert()
 *
 * Phase 2/5: exposure / display only — never used to redirect or block.
 */

use Rateb\App\Subscription\SubscriptionAlertService;
use Rateb\App\Subscription\SubscriptionAlertViewModel;
use Rateb\App\Subscription\SubscriptionBootstrap;
use Rateb\App\Subscription\SubscriptionContext;
use Rateb\App\Subscription\SubscriptionEnforcementGate;
use Rateb\App\Subscription\SubscriptionRuntime;

if (!function_exists('subscription')) {
    function subscription(): ?SubscriptionContext
    {
        SubscriptionBootstrap::ensureFromTenantContext();
        return SubscriptionRuntime::get();
    }
}

if (!function_exists('subscription_alert')) {
    function subscription_alert(): ?SubscriptionAlertViewModel
    {
        return (new SubscriptionAlertService())->current();
    }
}

if (!function_exists('rateb_subscription_enforcement_enabled')) {
    /** Feature flag SUBSCRIPTION_ENFORCEMENT_ENABLED (default false). */
    function rateb_subscription_enforcement_enabled(): bool
    {
        return SubscriptionEnforcementGate::isEnabled();
    }
}

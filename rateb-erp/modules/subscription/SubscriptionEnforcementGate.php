<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Feature-flagged subscription suspension enforcement.
 *
 * Flag OFF (default): always ALLOW — ERP behavior unchanged.
 * Flag ON: deny when SuspensionEngine says eligible or tenant already suspended.
 *
 * Instantly reversible via SUBSCRIPTION_ENFORCEMENT_ENABLED.
 */
final class SubscriptionEnforcementGate
{
    public const FLAG_NAME = 'SUBSCRIPTION_ENFORCEMENT_ENABLED';
    public const RENEW_PATH = 'subscription/renew';

    /** Paths allowed while suspended (suffix / route fragment match). */
    private const ALLOW_FRAGMENTS = [
        'subscription/renew',
        'subscription/invoices',
        'subscription/payment-status',
        'subscription/support',
        'support',
        'logout',
    ];

    private SuspensionEngine $suspensionEngine;

    public function __construct(?SuspensionEngine $suspensionEngine = null)
    {
        $this->suspensionEngine = $suspensionEngine ?? new SuspensionEngine();
    }

    public static function isEnabled(): bool
    {
        if (defined(self::FLAG_NAME)) {
            return (bool) constant(self::FLAG_NAME);
        }
        $env = getenv(self::FLAG_NAME);
        if ($env === false || $env === '') {
            $env = $_ENV[self::FLAG_NAME] ?? '';
        }
        if ($env === '' || $env === false) {
            return false;
        }

        return in_array(strtolower(trim((string) $env)), ['1', 'true', 'yes', 'on'], true);
    }

    public static function renewUrl(): string
    {
        $path = self::RENEW_PATH;
        if (function_exists('rateb_app_route')) {
            $path = rateb_app_route($path);
        } else {
            $path = 'admin/' . $path;
        }
        if (function_exists('rateb_url')) {
            return rateb_url($path);
        }
        return (defined('RATEB_BASE_URL') ? RATEB_BASE_URL : '') . '/' . ltrim($path, '/');
    }

    public function decide(
        SubscriptionContext $context,
        string $requestUri,
        ?string $todayYmd = null
    ): SubscriptionAccessDecision
    {
        $companyId = $context->companyId();
        $uri = $requestUri !== '' ? $requestUri : '/';

        if (!self::isEnabled()) {
            return SubscriptionAccessDecision::allow($companyId, $uri, 'enforcement_flag_off');
        }

        if ($companyId < 1 || !$context->hasRecord()) {
            return SubscriptionAccessDecision::allow($companyId, $uri, 'no_tenant_subscription_context');
        }

        if ($this->isAllowListed($uri)) {
            return SubscriptionAccessDecision::allow($companyId, $uri, 'allow_listed_path');
        }

// Better: block when context already reflects post-grace / suspended state
        // (avoids re-deriving "today" mismatches in tests and clock skew).
        $requiresBlock = $context->isSuspended()
            || $context->isSuspensionPending()
            || $this->suspensionEngine->shouldSuspend($context, $todayYmd);

        if (!$requiresBlock) {
            return SubscriptionAccessDecision::allow($companyId, $uri, 'subscription_access_ok');
        }

        $reason = $context->isSuspended()
            ? 'tenant_suspended'
            : 'grace_expired_suspension_eligible';

        return SubscriptionAccessDecision::deny(
            $companyId,
            $uri,
            $reason,
            self::RENEW_PATH
        );
    }

    public function isAllowListed(string $requestUri): bool
    {
        $normalized = strtolower(str_replace('\\', '/', $requestUri));
        $route = '';
        if (function_exists('rateb_current_erp_route')) {
            $route = strtolower((string) rateb_current_erp_route());
        }
        $haystacks = [$normalized, $route];

        foreach ($haystacks as $hay) {
            if ($hay === '') {
                continue;
            }
            foreach (self::ALLOW_FRAGMENTS as $frag) {
                if ($hay === $frag
                    || str_ends_with($hay, '/' . $frag)
                    || str_contains($hay, '/' . $frag)
                    || str_starts_with($hay, $frag)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Log enforcement decision (always for DENY; optional for ALLOW when debugging).
     */
    public function logDecision(SubscriptionAccessDecision $decision): void
    {
        if ($decision->allowed() && $decision->reason() === 'enforcement_flag_off') {
            return;
        }
        if ($decision->allowed() && $decision->reason() === 'subscription_access_ok') {
            return;
        }
        error_log(sprintf(
            'RATEB subscription enforcement: company_id=%d request_uri=%s decision=%s reason=%s timestamp=%s',
            $decision->companyId(),
            $decision->requestUri(),
            $decision->decision(),
            $decision->reason(),
            gmdate('c')
        ));
    }
}

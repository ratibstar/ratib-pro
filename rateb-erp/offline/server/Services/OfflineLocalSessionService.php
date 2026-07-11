<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

/**
 * PHP policy for client-side cold local sessions.
 * Local session state lives in the browser vault — this class never creates PHP sessions.
 */
final class OfflineLocalSessionService
{
    /** @return array<string, int> */
    public function sessionPolicy(): array
    {
        return (new ErpOfflineIdentitySessionPolicy())->snapshot();
    }

    /**
     * Documented rules for client local-session restore.
     * Server must not create or resume a PHP session from cold unlock.
     *
     * @return array{
     *   ok: bool,
     *   creates_php_session: false,
     *   server_authz_bypass: false,
     *   rules: list<string>
     * }
     */
    public function assertLocalSessionRules(): array
    {
        return [
            'ok' => true,
            'creates_php_session' => false,
            'server_authz_bypass' => false,
            'rules' => [
                'no_php_session_creation',
                'ui_restore_only',
                'server_authz_required_when_online',
            ],
        ];
    }

    /**
     * Default destroy policy for cold local session (vault wipe preference).
     *
     * @return array{clear_vault: bool, policy: string}
     */
    public function destroyPolicy(): array
    {
        $policy = (new ErpOfflineAuthPolicy())->logoutVaultPolicy();

        return [
            'clear_vault' => $policy === ErpOfflineAuthPolicy::LOGOUT_CLEAR_VAULT,
            'policy' => $policy,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

/**
 * Cold boot configuration for offline-shell entry (no server session).
 */
final class OfflineBootstrapManager
{
    /**
     * @return array{
     *   entry: string,
     *   requires_prior_enroll: true,
     *   flags: array{cold: bool, auth: bool},
     *   restore: array{
     *     nav: true,
     *     language: true,
     *     theme: true,
     *     permissions: true,
     *     company: true,
     *     branch: true,
     *     offline_banner: true
     *   },
     *   creates_server_session: false
     * }
     */
    public function coldBootConfig(): array
    {
        $flags = new OfflineFeatureFlagService();

        return [
            'entry' => 'offline-shell.html',
            'requires_prior_enroll' => true,
            'flags' => [
                'cold' => $flags->isColdIdentityEnabled(),
                'auth' => $flags->isAuthUnlockEnabled(),
            ],
            'restore' => [
                'nav' => true,
                'language' => true,
                'theme' => true,
                'permissions' => true,
                'company' => true,
                'branch' => true,
                'offline_banner' => true,
            ],
            'creates_server_session' => false,
        ];
    }
}

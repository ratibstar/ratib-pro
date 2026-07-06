<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Adapters;

use Rateb\App\Core\Auth;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\User;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CashierPortInterface;

/** Cashier identity from ERP session or Bearer API context (read-only). */
final class ErpCashierAdapter implements PosV2CashierPortInterface
{
    public function userId(): int
    {
        $sessionUserId = (int) SessionManager::get('rateb_user_id', 0);
        if ($sessionUserId > 0) {
            return $sessionUserId;
        }

        return (int) (TenantContext::apiUserId() ?? 0);
    }

    public function displayName(): string
    {
        Auth::bootstrapFromSession();
        $user = Auth::user();
        if (is_array($user)) {
            $name = trim((string) ($user['name'] ?? $user['full_name'] ?? $user['username'] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        $sessionName = trim((string) SessionManager::get('rateb_user_display_name', ''));
        if ($sessionName !== '') {
            return $sessionName;
        }

        $userId = $this->userId();
        if ($userId > 0) {
            try {
                $row = (new User())->find($userId);
                if (is_array($row)) {
                    $name = trim((string) ($row['name'] ?? $row['full_name'] ?? $row['username'] ?? ''));
                    if ($name !== '') {
                        return $name;
                    }
                }
            } catch (\Throwable) {
                // fall through to generic label
            }

            return 'User #' . $userId;
        }

        return '';
    }
}

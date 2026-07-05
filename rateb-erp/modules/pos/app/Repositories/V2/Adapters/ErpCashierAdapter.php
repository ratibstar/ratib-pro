<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Adapters;

use Rateb\App\Core\Auth;
use Rateb\App\Core\SessionManager;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CashierPortInterface;

/** Cashier identity from ERP session (read-only). */
final class ErpCashierAdapter implements PosV2CashierPortInterface
{
    public function userId(): int
    {
        return (int) SessionManager::get('rateb_user_id', 0);
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

        return $userId > 0 ? 'User #' . $userId : '';
    }
}

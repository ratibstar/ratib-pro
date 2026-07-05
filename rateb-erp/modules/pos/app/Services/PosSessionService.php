<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Models\PosSession;

/**
 * POS register session — cart, customer and terminal/shift context in PHP session
 * with optional rateb_pos_sessions row for audit binding.
 */
final class PosSessionService
{
    private const SESSION_KEY = 'rateb_pos_session';

    /** @param array<string, mixed> $data */
    public function start(array $data): void
    {
        SessionManager::set(self::SESSION_KEY, array_merge($this->defaults(), $this->current(), $data, [
            'started_at' => date('c'),
            'status' => 'active',
            'updated_at' => date('c'),
        ]));
    }

    public function end(): void
    {
        $current = $this->current();
        if (!empty($current['db_session_id'])) {
            $this->endDbSession((int) $current['db_session_id']);
        }
        SessionManager::set(self::SESSION_KEY, null);
    }

    /** @return array<string, mixed> */
    public function current(): array
    {
        $raw = SessionManager::get(self::SESSION_KEY);
        if (!is_array($raw)) {
            return $this->defaults();
        }
        return array_merge($this->defaults(), $raw);
    }

    public function hasActiveSession(): bool
    {
        return ($this->current()['status'] ?? '') === 'active';
    }

    /** @return array<int, array<string, mixed>> */
    public function getCartLines(): array
    {
        $cart = $this->current()['cart'] ?? [];
        return is_array($cart['lines'] ?? null) ? $cart['lines'] : [];
    }

    /** @param array<int, array<string, mixed>> $lines */
    public function setCartLines(array $lines): void
    {
        $this->patch([
            'cart' => [
                'lines' => $lines,
                'updated_at' => date('c'),
            ],
        ]);
    }

    /** @return array<string, mixed>|null */
    public function getCustomer(): ?array
    {
        $customer = $this->current()['customer'] ?? null;
        return is_array($customer) && !empty($customer['id']) ? $customer : null;
    }

    /** @param array<string, mixed>|null $customer */
    public function setCustomer(?array $customer): void
    {
        $this->patch(['customer' => $customer]);
    }

    /** @param array<string, mixed> $data */
    public function patch(array $data): void
    {
        SessionManager::set(self::SESSION_KEY, array_merge($this->current(), $data, [
            'status' => 'active',
            'updated_at' => date('c'),
        ]));
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $session = $this->current();
        $lines = $this->getCartLines();
        return [
            'status' => $session['status'] ?? 'inactive',
            'terminal_id' => (int) ($session['terminal_id'] ?? 0),
            'shift_id' => (int) ($session['shift_id'] ?? 0),
            'branch_id' => (int) ($session['branch_id'] ?? 0),
            'warehouse_id' => (int) ($session['warehouse_id'] ?? 0),
            'user_id' => (int) ($session['user_id'] ?? 0),
            'db_session_id' => (int) ($session['db_session_id'] ?? 0),
            'customer' => $this->getCustomer(),
            'cart' => [
                'lines' => $lines,
                'updated_at' => $session['cart']['updated_at'] ?? null,
            ],
            'started_at' => $session['started_at'] ?? null,
            'updated_at' => $session['updated_at'] ?? null,
        ];
    }

    /** @param array<string, mixed> $payload */
    public function loadSnapshot(array $payload): void
    {
        $lines = [];
        if (isset($payload['cart']) && is_array($payload['cart'])) {
            $lines = is_array($payload['cart']['lines'] ?? null) ? $payload['cart']['lines'] : [];
        } elseif (isset($payload['lines']) && is_array($payload['lines'])) {
            $lines = $payload['lines'];
        }

        $customer = null;
        if (isset($payload['customer']) && is_array($payload['customer']) && !empty($payload['customer']['id'])) {
            $customer = $payload['customer'];
        }

        $this->patch([
            'cart' => ['lines' => $lines, 'updated_at' => date('c')],
            'customer' => $customer,
            'terminal_id' => (int) ($payload['terminal_id'] ?? $this->current()['terminal_id'] ?? 0),
            'shift_id' => (int) ($payload['shift_id'] ?? $this->current()['shift_id'] ?? 0),
            'branch_id' => (int) ($payload['branch_id'] ?? $this->current()['branch_id'] ?? 0),
            'warehouse_id' => (int) ($payload['warehouse_id'] ?? $this->current()['warehouse_id'] ?? 0),
        ]);
    }

    public function bindRegisterContext(
        int $companyId,
        int $userId,
        int $terminalId,
        int $shiftId,
        int $branchId,
        ?int $warehouseId = null
    ): void {
        if ($companyId < 1 || $userId < 1) {
            return;
        }
        $this->start([
            'company_id' => $companyId,
            'user_id' => $userId,
            'terminal_id' => $terminalId,
            'shift_id' => $shiftId,
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId ?? 0,
        ]);
        if ($terminalId > 0 && $shiftId > 0) {
            $dbId = $this->ensureDbSession($companyId, $userId, $terminalId, $shiftId, $branchId);
            if ($dbId > 0) {
                $this->patch(['db_session_id' => $dbId]);
            }
        }
    }

    public function ensureDbSession(
        int $companyId,
        int $userId,
        int $terminalId,
        int $shiftId,
        int $branchId
    ): int {
        if ($companyId < 1 || $userId < 1 || $terminalId < 1 || $shiftId < 1) {
            return 0;
        }
        $existing = (int) ($this->current()['db_session_id'] ?? 0);
        if ($existing > 0) {
            return $existing;
        }

        TenantContext::setCompanyId($companyId);
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT id FROM rateb_pos_sessions WHERE company_id = :cid AND user_id = :uid AND terminal_id = :tid AND status = :st LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'uid' => $userId, 'tid' => $terminalId, 'st' => 'active']);
        $rowId = $stmt->fetchColumn();
        if ($rowId) {
            return (int) $rowId;
        }

        return (new PosSession())->create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'terminal_id' => $terminalId,
            'user_id' => $userId,
            'shift_id' => $shiftId,
            'status' => 'active',
            'started_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function endDbSession(int $sessionId): void
    {
        if ($sessionId < 1) {
            return;
        }
        (new PosSession())->update($sessionId, [
            'status' => 'ended',
            'ended_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        return [
            'status' => 'inactive',
            'terminal_id' => 0,
            'shift_id' => 0,
            'branch_id' => 0,
            'warehouse_id' => 0,
            'user_id' => 0,
            'company_id' => 0,
            'db_session_id' => 0,
            'customer' => null,
            'cart' => ['lines' => [], 'updated_at' => null],
            'started_at' => null,
            'updated_at' => null,
        ];
    }
}

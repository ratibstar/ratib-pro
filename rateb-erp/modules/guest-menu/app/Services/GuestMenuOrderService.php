<?php
declare(strict_types=1);

namespace Rateb\App\GuestMenu\Services;

use Rateb\App\Core\Database;
use Rateb\App\Pos\Services\Bridge\PosNotificationBridgeService;
use PDO;

/** Guest QR menu — submit simple table orders (no auth). */
final class GuestMenuOrderService
{
    private static bool $schemaReady = false;

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
        $this->ensureSchema();
    }

    /**
     * @param array<string, mixed> $settings Enabled guest menu row
     * @param array<string, mixed> $input table_label, guest_name, items[]
     * @return array{ok:bool, order_id?:int, order_no?:string, message?:string}
     */
    public function submit(array $settings, array $input): array
    {
        if ((string) ($settings['mode'] ?? 'browse') !== 'order') {
            return ['ok' => false, 'message' => 'order_mode_disabled'];
        }

        $companyId = (int) ($settings['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'message' => 'invalid_company'];
        }

        $items = $input['items'] ?? [];
        if (!is_array($items) || $items === []) {
            return ['ok' => false, 'message' => 'empty_cart'];
        }

        $normalized = [];
        $total = 0.0;
        $currency = 'SAR';
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $qty = max(1, (int) ($row['qty'] ?? 1));
            $price = max(0.0, (float) ($row['unit_price'] ?? 0));
            $productId = (int) ($row['product_id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($productId < 1 || $name === '') {
                continue;
            }
            $lineTotal = round($price * $qty, 2);
            $total += $lineTotal;
            if (!empty($row['currency'])) {
                $currency = (string) $row['currency'];
            }
            $normalized[] = [
                'product_id' => $productId,
                'name' => $name,
                'qty' => $qty,
                'unit_price' => $price,
                'line_total' => $lineTotal,
                'currency' => $currency,
            ];
        }

        if ($normalized === []) {
            return ['ok' => false, 'message' => 'empty_cart'];
        }

        $tableLabel = trim((string) ($input['table_label'] ?? ''));
        $guestName = trim((string) ($input['guest_name'] ?? ''));
        $slug = (string) ($settings['public_slug'] ?? '');
        $orderNo = 'GM-' . gmdate('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $itemsJson = json_encode($normalized, JSON_UNESCAPED_UNICODE);
        if ($itemsJson === false) {
            return ['ok' => false, 'message' => 'encode_failed'];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO rateb_guest_menu_orders
             (company_id, branch_id, public_slug, order_no, table_label, guest_name, items_json, total_amount, currency, status, created_at)
             VALUES (:cid, :bid, :slug, :ono, :table_label, :guest_name, :items, :total, :currency, \'pending\', NOW())'
        );
        $stmt->execute([
            'cid' => $companyId,
            'bid' => isset($settings['branch_id']) ? (int) $settings['branch_id'] : null,
            'slug' => $slug,
            'ono' => $orderNo,
            'table_label' => $tableLabel !== '' ? $tableLabel : null,
            'guest_name' => $guestName !== '' ? $guestName : null,
            'items' => $itemsJson,
            'total' => round($total, 2),
            'currency' => $currency,
        ]);

        $orderId = (int) $this->db->lastInsertId();
        $this->notifyNewOrder($companyId, $orderNo, $tableLabel, $guestName, round($total, 2), $currency, $orderId);

        return [
            'ok' => true,
            'order_id' => $orderId,
            'order_no' => $orderNo,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listForCompany(int $companyId, int $limit = 50): array
    {
        if ($companyId < 1) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $stmt = $this->db->prepare(
            'SELECT * FROM rateb_guest_menu_orders
             WHERE company_id = :cid
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $limit
        );
        $stmt->execute(['cid' => $companyId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function updateStatus(int $companyId, int $orderId, string $status): bool
    {
        if ($companyId < 1 || $orderId < 1) {
            return false;
        }
        if (!in_array($status, ['pending', 'accepted', 'cancelled'], true)) {
            return false;
        }
        $stmt = $this->db->prepare(
            'UPDATE rateb_guest_menu_orders SET status = :st, updated_at = NOW()
             WHERE id = :id AND company_id = :cid'
        );
        $stmt->execute(['st' => $status, 'id' => $orderId, 'cid' => $companyId]);

        return $stmt->rowCount() > 0;
    }

    private function notifyNewOrder(
        int $companyId,
        string $orderNo,
        string $tableLabel,
        string $guestName,
        float $total,
        string $currency,
        int $orderId,
    ): void {
        try {
            $who = $guestName !== '' ? $guestName : ($tableLabel !== '' ? __('guest_menu_table_short', ['table' => $tableLabel]) : __('guest_menu_guest_anonymous'));
            $msg = __('guest_menu_order_notify_body', [
                'order' => $orderNo,
                'who' => $who,
                'total' => number_format($total, 2) . ' ' . $currency,
            ]);
            (new PosNotificationBridgeService())->notifyCompany(
                $companyId,
                __('guest_menu_order_notify_title'),
                $msg,
                'info',
                'guest_menu_order',
                'guest_menu_order',
                $orderId,
            );
        } catch (\Throwable $e) {
            error_log('GuestMenuOrderService notify: ' . $e->getMessage());
        }
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS rateb_guest_menu_orders (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id INT UNSIGNED NOT NULL,
                branch_id INT UNSIGNED NULL,
                public_slug VARCHAR(64) NOT NULL,
                order_no VARCHAR(32) NOT NULL,
                table_label VARCHAR(64) NULL,
                guest_name VARCHAR(120) NULL,
                items_json JSON NOT NULL,
                total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                currency VARCHAR(8) NOT NULL DEFAULT \'SAR\',
                status ENUM(\'pending\', \'accepted\', \'cancelled\') NOT NULL DEFAULT \'pending\',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_gm_orders_company (company_id, status, created_at),
                KEY idx_gm_orders_slug (public_slug, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        self::$schemaReady = true;
    }
}

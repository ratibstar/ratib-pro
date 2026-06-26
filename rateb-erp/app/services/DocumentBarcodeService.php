<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Auth;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\BranchAccessService;
use Rateb\App\Models\Contract;
use Rateb\App\Models\Inventory;
use Rateb\App\Models\Invoice;
use Rateb\App\Models\PurchaseOrder;

final class DocumentBarcodeService
{
    public const PREFIX = 'RATEBERP:';

    public function generateBarcode(string $type, int $companyId, int $recordId): string
    {
        $companyId = max(0, $companyId);
        $recordId = max(1, $recordId);
        if ($type === 'invoice') {
            $prefix = 'INV';
        } elseif ($type === 'contract') {
            $prefix = 'CTR';
        } elseif ($type === 'inventory') {
            $prefix = 'RTB';
        } elseif ($type === 'purchase_order') {
            $prefix = 'PO';
        } else {
            $prefix = 'DOC';
        }
        return $prefix . str_pad((string) $companyId, 4, '0', STR_PAD_LEFT)
            . str_pad((string) $recordId, 8, '0', STR_PAD_LEFT);
    }

    public function qrPayload(string $barcode, string $type, int $recordId): string
    {
        return $this->publicScanUrl($barcode);
    }

    public function publicScanUrl(string $barcode): string
    {
        return rateb_url('scan/doc/' . rawurlencode(strtoupper(trim($barcode))));
    }

    public function documentEditUrl(string $type, int $recordId): string
    {
        if ($recordId < 1) {
            return '';
        }
        if ($type === 'inventory') {
            return rateb_app_url('inventory/' . $recordId . '/edit');
        }
        if ($type === 'invoice') {
            return rateb_url('admin/invoices/' . $recordId . '/edit');
        }
        if ($type === 'contract') {
            return rateb_app_url('contracts/' . $recordId . '/edit');
        }
        if ($type === 'purchase_order') {
            return rateb_app_url('purchase-orders/' . $recordId);
        }
        return '';
    }

    private function normalizeStoredQr(string $stored, string $barcode, string $type, int $recordId): string
    {
        $expected = $this->publicScanUrl($barcode);
        $stored = trim($stored);
        if ($stored === '') {
            return $expected;
        }
        if ($stored[0] === '{' || strpos($stored, self::PREFIX) === 0) {
            return $expected;
        }
        if (strpos($stored, '/edit') !== false || strpos($stored, 'scan/doc/') === false) {
            return $expected;
        }
        return $stored;
    }

    /** @return array<string, mixed>|null */
    public function resolvePublic(string $code): ?array
    {
        return $this->resolveForAuthenticatedUser($code);
    }

    /** @return array<string, mixed>|null */
    public function resolveForAuthenticatedUser(string $code): ?array
    {
        if (!Auth::check()) {
            return null;
        }
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }
        foreach ($this->typeOrderForCode($code) as $type) {
            $row = $this->findByBarcodeSafe($type, $code);
            if ($row && $this->canViewBarcodeRecord($type, $row)) {
                return $this->publicCard($type, $row);
            }
        }
        return null;
    }

    /** @param array<string, mixed> $row */
    private function canViewBarcodeRecord(string $type, array $row): bool
    {
        if (TenantContext::isSuperAdmin()) {
            return true;
        }
        $companyId = (int) TenantContext::companyId();
        if ($companyId < 1 || $companyId !== (int) ($row['company_id'] ?? 0)) {
            return false;
        }
        $branchId = isset($row['branch_id']) ? (int) $row['branch_id'] : 0;
        if ($branchId > 0) {
            $access = new BranchAccessService();
            if (!$access->canAccessBranch($branchId)) {
                return false;
            }
        }
        $resource = $this->resourceForType($type);
        if ($resource !== '' && function_exists('rateb_can_view_entity')) {
            return rateb_can_view_entity($resource);
        }
        return function_exists('rateb_can') && rateb_can('documents.view');
    }

    private function resourceForType(string $type): string
    {
        if ($type === 'invoice') {
            return 'invoices';
        }
        if ($type === 'contract') {
            return 'contracts';
        }
        if ($type === 'inventory') {
            return 'inventory';
        }
        if ($type === 'purchase_order') {
            return 'purchase-orders';
        }
        return '';
    }

    /** @return list<string> */
    private function typeOrderForCode(string $code): array
    {
        $all = ['inventory', 'invoice', 'contract', 'purchase_order'];
        if (str_starts_with($code, 'PO')) {
            return ['purchase_order', 'invoice', 'contract', 'inventory'];
        }
        if (str_starts_with($code, 'INV')) {
            return ['invoice', 'purchase_order', 'contract', 'inventory'];
        }
        if (str_starts_with($code, 'CTR')) {
            return ['contract', 'purchase_order', 'invoice', 'inventory'];
        }
        if (str_starts_with($code, 'RTB')) {
            return ['inventory', 'purchase_order', 'invoice', 'contract'];
        }
        return $all;
    }

    /** @return array<string, mixed>|null */
    private function findByBarcodeSafe(string $type, string $code): ?array
    {
        try {
            return $this->findByBarcode($type, $code);
        } catch (\Throwable $e) {
            if (DatabaseErrorService::isSchemaIssue($e)) {
                return null;
            }
            throw $e;
        }
    }

    /** @return array<string, mixed>|null */
    private function findByBarcode(string $type, string $code): ?array
    {
        if ($type === 'invoice') {
            $rows = (new Invoice())->query(
                'SELECT * FROM rateb_invoices WHERE barcode = :code LIMIT 1',
                ['code' => $code]
            );
        } elseif ($type === 'contract') {
            $rows = (new Contract())->query(
                'SELECT * FROM rateb_contracts WHERE barcode = :code LIMIT 1',
                ['code' => $code]
            );
        } elseif ($type === 'inventory') {
            $rows = (new Inventory())->query(
                'SELECT i.*, w.name AS warehouse_name
                 FROM rateb_inventory i
                 LEFT JOIN rateb_warehouses w ON w.id = i.warehouse_id
                 WHERE i.barcode = :code LIMIT 1',
                ['code' => $code]
            );
        } elseif ($type === 'purchase_order') {
            $rows = (new PurchaseOrder())->query(
                'SELECT po.*, s.name AS supplier_name
                 FROM rateb_purchase_orders po
                 LEFT JOIN rateb_suppliers s ON s.id = po.supplier_id
                 WHERE po.barcode = :code LIMIT 1',
                ['code' => $code]
            );
        } else {
            return null;
        }
        return $rows[0] ?? null;
    }

    /** @param array<string, mixed> $row */
    private function publicCard(string $type, array $row): array
    {
        $recordId = (int) ($row['id'] ?? 0);
        $barcode = (string) ($row['barcode'] ?? '');
        $title = $this->defaultTitle($type, $row);
        $subtitle = $this->defaultSubtitle($type, $row);
        $qrPayload = $this->publicScanUrl($barcode);
        return [
            'type' => $type,
            'type_label' => __($type === 'inventory' ? 'inventory' : ($type === 'invoice' ? 'invoices' : ($type === 'purchase_order' ? 'purchase_orders' : 'contracts'))),
            'recordId' => $recordId,
            'title' => $title,
            'subtitle' => $subtitle,
            'barcode' => $barcode,
            'qr_image_url' => $this->qrPublicUrl($qrPayload, 160),
            'fields' => $this->publicFields($type, $row),
        ];
    }

    /** @param array<string, mixed> $row */
    /** @return array<int, array{label:string,value:string}> */
    private function publicFields(string $type, array $row): array
    {
        $config = $this->scanFieldConfig();
        $defs = $config[$type] ?? [];
        $out = [];
        foreach ($defs as $def) {
            $key = (string) ($def['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $label = __((string) ($def['label'] ?? $key));
            $value = $this->formatScanValue($row, $key, (string) ($def['format'] ?? ''));
            $field = $this->field($label, $value);
            if ($field !== null) {
                $out[] = $field;
            }
        }
        return $out;
    }

    /** @return array<string, list<array{key:string,label:string,format?:string}>> */
    private function scanFieldConfig(): array
    {
        $path = dirname(__DIR__, 2) . '/config/document-scan-fields.php';
        if (!is_file($path)) {
            return [];
        }
        $config = require $path;
        return is_array($config) ? $config : [];
    }

    /** @param array<string, mixed> $row */
    private function formatScanValue(array $row, string $key, string $format): string
    {
        $raw = $row[$key] ?? '';
        if ($format === 'money') {
            $num = is_numeric($raw) ? (float) $raw : 0.0;
            if ($num <= 0) {
                return '';
            }
            $currency = trim((string) ($row['currency'] ?? 'SAR'));
            return number_format($num, 2) . ($currency !== '' ? ' ' . $currency : '');
        }
        return trim((string) $raw);
    }

    /** @return array{label:string,value:string}|null */
    private function field(string $label, string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        return ['label' => $label, 'value' => $value];
    }

    public function qrImageUrl(string $payload, int $size = 280): string
    {
        $size = max(160, min(500, $size));
        return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size
            . '&margin=20&ecc=H&format=png&color=000000&bgcolor=ffffff&data=' . rawurlencode($payload);
    }

    public function qrProxyUrl(string $payload, int $size = 280): string
    {
        return rateb_url('barcode/qr?data=' . rawurlencode($payload) . '&size=' . $size);
    }

    public function qrPublicUrl(string $payload, int $size = 280): string
    {
        return rateb_url('scan/qr?data=' . rawurlencode($payload) . '&size=' . $size);
    }

    /** @return array{barcode:string,qr_code:string}|null */
    public function ensure(string $type, int $recordId): ?array
    {
        $row = $this->loadRow($type, $recordId);
        if (!$row) {
            return null;
        }
        $existing = trim((string) ($row['barcode'] ?? ''));
        if ($existing !== '') {
            $qr = $this->normalizeStoredQr((string) ($row['qr_code'] ?? ''), $existing, $type, $recordId);
            if ($qr !== (string) ($row['qr_code'] ?? '')) {
                $this->updateRow($type, $recordId, ['qr_code' => $qr]);
            }
            return [
                'barcode' => $existing,
                'qr_code' => $qr,
            ];
        }
        $companyId = (int) ($row['company_id'] ?? 0);
        $barcode = $this->generateBarcode($type, $companyId, $recordId);
        $qr = $this->qrPayload($barcode, $type, $recordId);
        $this->updateRow($type, $recordId, ['barcode' => $barcode, 'qr_code' => $qr]);
        return ['barcode' => $barcode, 'qr_code' => $qr];
    }

    /** @return array<string, mixed>|null */
    public function labelData(string $type, int $recordId, string $title = '', string $subtitle = ''): ?array
    {
        $codes = $this->ensure($type, $recordId);
        if (!$codes) {
            return null;
        }
        $row = $this->loadRow($type, $recordId);
        if (!$row) {
            return null;
        }
        if ($title === '') {
            $title = $this->defaultTitle($type, $row);
        }
        if ($subtitle === '') {
            $subtitle = $this->defaultSubtitle($type, $row);
        }
        return [
            'type' => $type,
            'recordId' => $recordId,
            'title' => $title,
            'subtitle' => $subtitle,
            'barcode' => $codes['barcode'],
            'qr_code' => $codes['qr_code'],
            'qr_image_url' => $this->qrPublicUrl($codes['qr_code'], 200),
            'qr_proxy_url' => $this->qrPublicUrl($codes['qr_code'], 200),
        ];
    }

    /** @return array<string, mixed>|null */
    private function loadRow(string $type, int $recordId): ?array
    {
        if ($type === 'invoice') {
            return (new Invoice())->find($recordId);
        }
        if ($type === 'contract') {
            return (new Contract())->find($recordId);
        }
        if ($type === 'inventory') {
            return (new Inventory())->find($recordId);
        }
        if ($type === 'purchase_order') {
            return (new PurchaseOrder())->find($recordId);
        }
        return null;
    }

    /** @param array<string, mixed> $data */
    private function updateRow(string $type, int $recordId, array $data): void
    {
        if ($type === 'invoice') {
            (new Invoice())->update($recordId, $data);
        } elseif ($type === 'contract') {
            (new Contract())->update($recordId, $data);
        } elseif ($type === 'inventory') {
            (new Inventory())->update($recordId, $data);
        } elseif ($type === 'purchase_order') {
            (new PurchaseOrder())->update($recordId, $data);
        }
    }

    /** @param array<string, mixed> $row */
    private function defaultTitle(string $type, array $row): string
    {
        if ($type === 'invoice') {
            return (string) ($row['invoice_no'] ?? __('invoices'));
        }
        if ($type === 'contract') {
            return (string) ($row['contract_no'] ?? $row['title'] ?? __('contracts'));
        }
        if ($type === 'inventory') {
            return (string) ($row['item_name'] ?? __('inventory'));
        }
        if ($type === 'purchase_order') {
            return (string) ($row['order_no'] ?? __('purchase_orders'));
        }
        return __('barcode');
    }

    /** @param array<string, mixed> $row */
    private function defaultSubtitle(string $type, array $row): string
    {
        if ($type === 'invoice') {
            return __('invoices') . ' #' . (int) ($row['id'] ?? 0);
        }
        if ($type === 'contract') {
            return (string) ($row['title'] ?? __('contracts'));
        }
        if ($type === 'inventory') {
            $sku = trim((string) ($row['sku'] ?? ''));
            $name = trim((string) ($row['item_name'] ?? ''));
            if ($sku !== '' && strcasecmp($sku, $name) !== 0) {
                return __('sku') . ': ' . $sku;
            }
            $category = trim((string) ($row['category'] ?? ''));
            return $category !== '' ? $category : '';
        }
        if ($type === 'purchase_order') {
            return (string) ($row['supplier_name'] ?? __('supplier'));
        }
        return '';
    }
}

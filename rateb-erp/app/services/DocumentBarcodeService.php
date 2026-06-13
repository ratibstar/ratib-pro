<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\Contract;
use Rateb\App\Models\Inventory;
use Rateb\App\Models\Invoice;

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
        } else {
            $prefix = 'DOC';
        }
        return $prefix . str_pad((string) $companyId, 4, '0', STR_PAD_LEFT)
            . str_pad((string) $recordId, 8, '0', STR_PAD_LEFT);
    }

    public function qrPayload(string $barcode, string $type, int $recordId): string
    {
        $url = $this->documentEditUrl($type, $recordId);
        if ($url !== '') {
            return $url;
        }
        return $barcode;
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
        return '';
    }

    private function normalizeStoredQr(string $stored, string $barcode, string $type, int $recordId): string
    {
        $expected = $this->qrPayload($barcode, $type, $recordId);
        $stored = trim($stored);
        if ($stored === '') {
            return $expected;
        }
        if ($stored[0] === '{' || strpos($stored, self::PREFIX) === 0) {
            return $expected;
        }
        if (strpos($expected, 'http') === 0 && strpos($stored, 'http') !== 0) {
            return $expected;
        }
        return $stored;
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
            'qr_image_url' => $this->qrImageUrl($codes['qr_code'], 200),
            'qr_proxy_url' => $this->qrProxyUrl($codes['qr_code'], 200),
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
        return '';
    }
}

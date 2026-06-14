<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\JournalEntry;

final class ZatcaService
{
    /** @return array<string, mixed> */
    public function getTaxProfile(int $companyId): array
    {
        $row = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_company_tax_profiles WHERE company_id = :cid LIMIT 1',
            ['cid' => $companyId]
        );
        return $row ?: [
            'company_id' => $companyId,
            'vat_number' => '',
            'cr_number' => '',
            'legal_name_ar' => '',
            'legal_name_en' => '',
            'street' => '',
            'building_no' => '',
            'city' => '',
            'postal_code' => '',
            'zatca_enabled' => 0,
            'zatca_environment' => 'sandbox',
        ];
    }

    /** @param array<string, mixed> $data */
    public function saveTaxProfile(int $companyId, array $data): void
    {
        $pdo = Database::connection();
        $exists = (new JournalEntry())->queryOne(
            'SELECT company_id FROM rateb_company_tax_profiles WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $payload = [
            'vat_number' => trim((string) ($data['vat_number'] ?? '')),
            'cr_number' => trim((string) ($data['cr_number'] ?? '')),
            'legal_name_ar' => trim((string) ($data['legal_name_ar'] ?? '')),
            'legal_name_en' => trim((string) ($data['legal_name_en'] ?? '')),
            'street' => trim((string) ($data['street'] ?? '')),
            'building_no' => trim((string) ($data['building_no'] ?? '')),
            'city' => trim((string) ($data['city'] ?? '')),
            'postal_code' => trim((string) ($data['postal_code'] ?? '')),
            'zatca_enabled' => !empty($data['zatca_enabled']) ? 1 : 0,
            'zatca_environment' => ($data['zatca_environment'] ?? '') === 'production' ? 'production' : 'sandbox',
            'cid' => $companyId,
        ];
        if ($exists) {
            $pdo->prepare(
                'UPDATE rateb_company_tax_profiles SET vat_number = :vat_number, cr_number = :cr_number,
                 legal_name_ar = :legal_name_ar, legal_name_en = :legal_name_en, street = :street,
                 building_no = :building_no, city = :city, postal_code = :postal_code,
                 zatca_enabled = :zatca_enabled, zatca_environment = :zatca_environment
                 WHERE company_id = :cid'
            )->execute($payload);
            return;
        }
        $pdo->prepare(
            'INSERT INTO rateb_company_tax_profiles
             (company_id, vat_number, cr_number, legal_name_ar, legal_name_en, street, building_no, city, postal_code, zatca_enabled, zatca_environment)
             VALUES (:cid, :vat_number, :cr_number, :legal_name_ar, :legal_name_en, :street, :building_no, :city, :postal_code, :zatca_enabled, :zatca_environment)'
        )->execute($payload);
    }

    /** Simplified ZATCA Phase-1 QR (TLV base64). */
    public function generateQrBase64(int $companyId, array $invoice): string
    {
        $profile = $this->getTaxProfile($companyId);
        $seller = (string) ($profile['legal_name_ar'] ?: $profile['legal_name_en'] ?: 'Seller');
        $vat = (string) ($profile['vat_number'] ?? '');
        $timestamp = (string) ($invoice['issued_at'] ?? date('Y-m-d')) . 'T' . date('H:i:s');
        $total = number_format((float) ($invoice['total_amount'] ?? 0), 2, '.', '');
        $tax = number_format((float) ($invoice['tax_amount'] ?? 0), 2, '.', '');
        $tlv = $this->tlv('1', $seller)
            . $this->tlv('2', $vat)
            . $this->tlv('3', $timestamp)
            . $this->tlv('4', $total)
            . $this->tlv('5', $tax);
        return base64_encode($tlv);
    }

    public function readinessStatus(int $companyId): array
    {
        $p = $this->getTaxProfile($companyId);
        $checks = [
            'vat_number' => strlen((string) ($p['vat_number'] ?? '')) >= 15,
            'legal_name' => trim((string) ($p['legal_name_ar'] ?? $p['legal_name_en'] ?? '')) !== '',
            'address' => trim((string) ($p['city'] ?? '')) !== '',
            'enabled' => (int) ($p['zatca_enabled'] ?? 0) === 1,
        ];
        $ready = !in_array(false, $checks, true);
        return ['profile' => $p, 'checks' => $checks, 'ready' => $ready];
    }

    /** @return array<int, array<string, mixed>> */
    public function listInvoicesWithQr(int $companyId, int $limit = 50): array
    {
        return (new JournalEntry())->query(
            'SELECT id, invoice_no, issued_at, total_amount, tax_amount, status, zatca_status, zatca_qr
             FROM rateb_invoices WHERE company_id = :cid ORDER BY id DESC LIMIT ' . max(1, min(200, $limit)),
            ['cid' => $companyId]
        );
    }

    public function generateInvoiceQr(int $companyId, int $invoiceId): bool
    {
        $invoice = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_invoices WHERE id = :id AND company_id = :cid',
            ['id' => $invoiceId, 'cid' => $companyId]
        );
        if (!$invoice) {
            return false;
        }
        $qr = $this->generateQrBase64($companyId, (array) $invoice);
        $uuid = $invoice['zatca_uuid'] ?? null;
        if (!$uuid) {
            $uuid = $this->uuidV4();
        }
        Database::connection()->prepare(
            'UPDATE rateb_invoices SET zatca_qr = :qr, zatca_uuid = :uid, zatca_status = :st WHERE id = :id'
        )->execute([
            'qr' => $qr,
            'uid' => $uuid,
            'st' => 'cleared',
            'id' => $invoiceId,
        ]);
        return true;
    }

    private function tlv(string $tag, string $value): string
    {
        return chr((int) $tag) . chr(strlen($value)) . $value;
    }

    private function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        $hex = bin2hex($data);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    }
}

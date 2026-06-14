<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Model;

/** Standard document codes: XX-0001 (2 letters + 4 digits, per company). */
final class DocumentCodeService
{
    public const PREFIX_PURCHASE_REQUEST = 'PR-';
    public const PREFIX_PURCHASE_ORDER = 'PO-';
    public const PREFIX_RFQ = 'RF-';
    public const PREFIX_QUOTATION = 'QT-';
    public const PREFIX_CONTRACT = 'CT-';
    public const PREFIX_TENDER = 'TN-';
    public const PREFIX_INVENTORY = 'IN-';
    public const PREFIX_BATCH = 'IB-';
    public const PREFIX_AUDIT = 'IA-';
    public const PREFIX_MOVEMENT = 'SM-';
    public const PREFIX_WAREHOUSE = 'WH-';
    public const PREFIX_SUPPLIER = 'SU-';
    public const PREFIX_ASSET = 'AS-';
    public const PREFIX_EVALUATION = 'SE-';

    public function generate(Model $model, string $prefix, string $column): string
    {
        return $model->generateDocumentCode($prefix, $column);
    }

    /** @param array<string, mixed> $data */
    public function assignIfEmpty(array &$data, Model $model, string $prefix, string $column): void
    {
        if (trim((string) ($data[$column] ?? '')) === '') {
            $data[$column] = $this->generate($model, $prefix, $column);
        }
    }
}

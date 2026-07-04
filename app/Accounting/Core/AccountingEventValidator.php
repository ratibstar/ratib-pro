<?php
declare(strict_types=1);

namespace App\Accounting\Core;

final class AccountingEventValidator
{
    /** @var list<string> */
    private const SOURCE_SYSTEMS = ['rateb-erp', 'main-site', 'control-panel', 'ledger'];

    /** @var list<string> */
    private const EVENT_TYPES = ['invoice', 'payment', 'expense', 'transfer', 'journal'];

    /**
     * @return array{valid:bool, errors:list<string>}
     */
    public function validate(array $event): array
    {
        $errors = [];

        $source = (string) ($event['source_system'] ?? '');
        if (!in_array($source, self::SOURCE_SYSTEMS, true)) {
            $errors[] = 'source_system must be one of: ' . implode(', ', self::SOURCE_SYSTEMS);
        }

        $type = (string) ($event['event_type'] ?? '');
        if (!in_array($type, self::EVENT_TYPES, true)) {
            $errors[] = 'event_type must be one of: ' . implode(', ', self::EVENT_TYPES);
        }

        if (!isset($event['company_id']) || !is_numeric($event['company_id'])) {
            $errors[] = 'company_id is required and must be numeric';
        }

        if (array_key_exists('branch_id', $event) && $event['branch_id'] !== null && !is_numeric($event['branch_id'])) {
            $errors[] = 'branch_id must be null or numeric';
        }

        if (!isset($event['amount']) || !is_numeric($event['amount'])) {
            $errors[] = 'amount is required and must be numeric';
        } elseif ((float) $event['amount'] < 0) {
            $errors[] = 'amount must be non-negative';
        }

        $currency = (string) ($event['currency'] ?? '');
        if ($currency === '' || strlen($currency) !== 3) {
            $errors[] = 'currency is required (ISO 4217, 3 chars)';
        }

        if (trim((string) ($event['debit_account'] ?? '')) === '') {
            $errors[] = 'debit_account is required';
        }

        if (trim((string) ($event['credit_account'] ?? '')) === '') {
            $errors[] = 'credit_account is required';
        }

        if (trim((string) ($event['reference_type'] ?? '')) === '') {
            $errors[] = 'reference_type is required';
        }

        if (!array_key_exists('reference_id', $event) || $event['reference_id'] === '' || $event['reference_id'] === null) {
            $errors[] = 'reference_id is required';
        }

        if (!isset($event['metadata']) || !is_array($event['metadata'])) {
            $errors[] = 'metadata must be an array';
        }

        return ['valid' => $errors === [], 'errors' => $errors];
    }
}

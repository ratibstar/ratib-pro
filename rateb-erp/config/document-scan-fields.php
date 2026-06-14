<?php
declare(strict_types=1);

/**
 * Public scan view fields — add entries here to show new columns after QR scan.
 *
 * @return array<string, list<array{key:string,label:string,format?:string}>>
 */
return [
    'inventory' => [
        ['key' => 'sku', 'label' => 'sku'],
        ['key' => 'quantity', 'label' => 'quantity'],
        ['key' => 'warehouse_name', 'label' => 'warehouses'],
        ['key' => 'production_date', 'label' => 'production_date'],
        ['key' => 'expiry_date', 'label' => 'expiry_date'],
        ['key' => 'status', 'label' => 'status'],
    ],
    'invoice' => [
        ['key' => 'invoice_no', 'label' => 'invoice_no'],
        ['key' => 'total_amount', 'label' => 'total_amount', 'format' => 'money'],
        ['key' => 'status', 'label' => 'status'],
        ['key' => 'issued_at', 'label' => 'issued_at'],
        ['key' => 'due_date', 'label' => 'due_date'],
    ],
    'contract' => [
        ['key' => 'contract_no', 'label' => 'contract_no'],
        ['key' => 'status', 'label' => 'status'],
        ['key' => 'start_date', 'label' => 'start_date'],
        ['key' => 'end_date', 'label' => 'end_date'],
        ['key' => 'value', 'label' => 'value', 'format' => 'money'],
    ],
    'purchase_order' => [
        ['key' => 'order_no', 'label' => 'order_no'],
        ['key' => 'supplier_name', 'label' => 'supplier'],
        ['key' => 'total_amount', 'label' => 'total_amount', 'format' => 'money'],
        ['key' => 'status', 'label' => 'status'],
        ['key' => 'order_date', 'label' => 'order_date'],
    ],
];

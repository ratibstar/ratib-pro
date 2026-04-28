<?php
/**
 * EN: Handles core framework/runtime behavior in `core/examples/secure_insert_example.php`.
 * AR: يدير سلوك النواة والإطار الأساسي للتشغيل في `core/examples/secure_insert_example.php`.
 */
/**
 * Example: Secure INSERT - country_id from TENANT_ID only
 *
 * Never pass country_id from POST/GET.
 */
require_once __DIR__ . '/../BaseModel.php';

$data = [
    'name' => 'New Item',
    'description' => 'From form',
    'status' => 'active',
];
// country_id is added automatically by BaseModel::secureInsert
$id = BaseModel::secureInsert('your_table', $data);

<?php
/**
 * EN: Handles core framework/runtime behavior in `core/examples/secure_select_example.php`.
 * AR: يدير سلوك النواة والإطار الأساسي للتشغيل في `core/examples/secure_select_example.php`.
 */
/**
 * Example: Secure SELECT with tenant isolation
 *
 * All queries MUST include: WHERE country_id = :tenant_id
 * Never accept country_id from user input.
 */
require_once __DIR__ . '/../BaseModel.php';

// Example 1: BaseModel::secureQuery
$sql = "SELECT * FROM users WHERE country_id = :tenant_id AND status = 'active'";
$stmt = BaseModel::secureQuery($sql, []);

// Example 2: With extra params
$sql = "SELECT * FROM roles WHERE country_id = :tenant_id AND role_id = :role_id";
$stmt = BaseModel::secureQuery($sql, [':role_id' => 2]);

// Example 3: Super admin with tenant override (allow_cross_tenant)
$sql = "SELECT * FROM users WHERE country_id = :tenant_id";
$stmt = BaseModel::secureQuery($sql, [], ['allow_cross_tenant' => true]);

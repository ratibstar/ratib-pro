<?php
/**
 * EN: Handles API endpoint/business logic in `api/vendors/list.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/vendors/list.php`.
 */
header('Content-Type: application/json');
echo json_encode([
    ["id" => 1, "name" => "Acme Supplies", "contact" => "John Doe", "email" => "acme@example.com"],
    ["id" => 2, "name" => "Global Traders", "contact" => "Jane Smith", "email" => "global@example.com"]
]); 
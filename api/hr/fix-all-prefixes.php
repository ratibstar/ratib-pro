<?php
/**
 * EN: Handles API endpoint/business logic in `api/hr/fix-all-prefixes.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/hr/fix-all-prefixes.php`.
 */
/**
 * Complete fix script for HR ID prefixes
 * Run this: https://out.ratib.sa/api/hr/fix-all-prefixes.php
 */

header('Content-Type: application/json');

$file = __DIR__ . '/../core/formatted-id-helper.php';

if (!file_exists($file)) {
    echo json_encode(['success' => false, 'message' => 'Helper file not found']);
    exit;
}

$content = file_get_contents($file);

// Fix attendance: EM -> AT
$content = str_replace(
    "FROM attendance WHERE record_id REGEXP '^EM[0-9]+$'",
    "FROM attendance WHERE record_id REGEXP '^AT[0-9]+$'",
    $content
);
$content = str_replace(
    "function generateHRAttendanceId(\$conn) {\n    try {\n        \$sql = \"SELECT IFNULL(MAX(CAST(SUBSTRING(record_id, 3) AS UNSIGNED)), 0) + 1 AS next_id FROM attendance WHERE record_id REGEXP '^AT[0-9]+$'\";\n        \$nextId = _getNextId(\$conn, \$sql);\n        return 'EM'",
    "function generateHRAttendanceId(\$conn) {\n    try {\n        \$sql = \"SELECT IFNULL(MAX(CAST(SUBSTRING(record_id, 3) AS UNSIGNED)), 0) + 1 AS next_id FROM attendance WHERE record_id REGEXP '^AT[0-9]+$'\";\n        \$nextId = _getNextId(\$conn, \$sql);\n        return 'AT'",
    $content
);
$content = str_replace(
    "* Generate formatted ID for attendance table (format: EM0001, EM0002, etc.)",
    "* Generate formatted ID for attendance table (format: AT0001, AT0002, etc.)",
    $content
);
$content = str_replace(
    "* @return string Formatted ID (e.g., 'EM0001')\n */\nfunction generateHRAttendanceId",
    "* @return string Formatted ID (e.g., 'AT0001')\n */\nfunction generateHRAttendanceId",
    $content
);

// Fix advances: EM -> AD
$content = str_replace(
    "FROM advances WHERE record_id REGEXP '^EM[0-9]+$'",
    "FROM advances WHERE record_id REGEXP '^AD[0-9]+$'",
    $content
);
$content = str_replace(
    "function generateHRAdvanceId(\$conn) {\n    try {\n        \$sql = \"SELECT IFNULL(MAX(CAST(SUBSTRING(record_id, 3) AS UNSIGNED)), 0) + 1 AS next_id FROM advances WHERE record_id REGEXP '^AD[0-9]+$'\";\n        \$nextId = _getNextId(\$conn, \$sql);\n        return 'EM'",
    "function generateHRAdvanceId(\$conn) {\n    try {\n        \$sql = \"SELECT IFNULL(MAX(CAST(SUBSTRING(record_id, 3) AS UNSIGNED)), 0) + 1 AS next_id FROM advances WHERE record_id REGEXP '^AD[0-9]+$'\";\n        \$nextId = _getNextId(\$conn, \$sql);\n        return 'AD'",
    $content
);
$content = str_replace(
    "* Generate formatted ID for advances table (format: EM0001, EM0002, etc.)",
    "* Generate formatted ID for advances table (format: AD0001, AD0002, etc.)",
    $content
);
$content = str_replace(
    "* @return string Formatted ID (e.g., 'EM0001')\n */\nfunction generateHRAdvanceId",
    "* @return string Formatted ID (e.g., 'AD0001')\n */\nfunction generateHRAdvanceId",
    $content
);

// Fix salaries: EM -> PA
$content = str_replace(
    "FROM salaries WHERE record_id REGEXP '^EM[0-9]+$'",
    "FROM salaries WHERE record_id REGEXP '^PA[0-9]+$'",
    $content
);
$content = str_replace(
    "function generateHRSalaryId(\$conn) {\n    try {\n        \$sql = \"SELECT IFNULL(MAX(CAST(SUBSTRING(record_id, 3) AS UNSIGNED)), 0) + 1 AS next_id FROM salaries WHERE record_id REGEXP '^PA[0-9]+$'\";\n        \$nextId = _getNextId(\$conn, \$sql);\n        return 'EM'",
    "function generateHRSalaryId(\$conn) {\n    try {\n        \$sql = \"SELECT IFNULL(MAX(CAST(SUBSTRING(record_id, 3) AS UNSIGNED)), 0) + 1 AS next_id FROM salaries WHERE record_id REGEXP '^PA[0-9]+$'\";\n        \$nextId = _getNextId(\$conn, \$sql);\n        return 'PA'",
    $content
);
$content = str_replace(
    "* Generate formatted ID for salaries table (format: EM0001, EM0002, etc.)",
    "* Generate formatted ID for salaries table (format: PA0001, PA0002, etc.)",
    $content
);
$content = str_replace(
    "* @return string Formatted ID (e.g., 'EM0001')\n */\nfunction generateHRSalaryId",
    "* @return string Formatted ID (e.g., 'PA0001')\n */\nfunction generateHRSalaryId",
    $content
);

// Fix documents: EM -> DO
$content = str_replace(
    "FROM hr_documents WHERE record_id REGEXP '^EM[0-9]+$'",
    "FROM hr_documents WHERE record_id REGEXP '^DO[0-9]+$'",
    $content
);
$content = str_replace(
    "function generateHRDocumentId(\$conn) {\n    try {\n        \$sql = \"SELECT IFNULL(MAX(CAST(SUBSTRING(record_id, 3) AS UNSIGNED)), 0) + 1 AS next_id FROM hr_documents WHERE record_id REGEXP '^DO[0-9]+$'\";\n        \$nextId = _getNextId(\$conn, \$sql);\n        return 'EM'",
    "function generateHRDocumentId(\$conn) {\n    try {\n        \$sql = \"SELECT IFNULL(MAX(CAST(SUBSTRING(record_id, 3) AS UNSIGNED)), 0) + 1 AS next_id FROM hr_documents WHERE record_id REGEXP '^DO[0-9]+$'\";\n        \$nextId = _getNextId(\$conn, \$sql);\n        return 'DO'",
    $content
);
$content = str_replace(
    "* Generate formatted ID for hr_documents table (format: EM0001, EM0002, etc.)",
    "* Generate formatted ID for hr_documents table (format: DO0001, DO0002, etc.)",
    $content
);
$content = str_replace(
    "* @return string Formatted ID (e.g., 'EM0001')\n */\nfunction generateHRDocumentId",
    "* @return string Formatted ID (e.g., 'DO0001')\n */\nfunction generateHRDocumentId",
    $content
);

// Fix vehicles: EM -> VE
$content = str_replace(
    "FROM cars WHERE record_id REGEXP '^EM[0-9]+$'",
    "FROM cars WHERE record_id REGEXP '^VE[0-9]+$'",
    $content
);
$content = str_replace(
    "function generateHRVehicleId(\$conn) {\n    try {\n        \$sql = \"SELECT IFNULL(MAX(CAST(SUBSTRING(record_id, 3) AS UNSIGNED)), 0) + 1 AS next_id FROM cars WHERE record_id REGEXP '^VE[0-9]+$'\";\n        \$nextId = _getNextId(\$conn, \$sql);\n        return 'EM'",
    "function generateHRVehicleId(\$conn) {\n    try {\n        \$sql = \"SELECT IFNULL(MAX(CAST(SUBSTRING(record_id, 3) AS UNSIGNED)), 0) + 1 AS next_id FROM cars WHERE record_id REGEXP '^VE[0-9]+$'\";\n        \$nextId = _getNextId(\$conn, \$sql);\n        return 'VE'",
    $content
);
$content = str_replace(
    "* Generate formatted ID for cars table (format: EM0001, EM0002, etc.)",
    "* Generate formatted ID for cars table (format: VE0001, VE0002, etc.)",
    $content
);
$content = str_replace(
    "* @return string Formatted ID (e.g., 'EM0001')\n */\nfunction generateHRVehicleId",
    "* @return string Formatted ID (e.g., 'VE0001')\n */\nfunction generateHRVehicleId",
    $content
);

if (file_put_contents($file, $content)) {
    echo json_encode([
        'success' => true, 
        'message' => 'All prefixes fixed: AT (attendance), AD (advances), PA (salaries), DO (documents), VE (vehicles)'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to write file']);
}

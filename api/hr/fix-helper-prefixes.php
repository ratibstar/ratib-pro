<?php
/**
 * EN: Handles API endpoint/business logic in `api/hr/fix-helper-prefixes.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/hr/fix-helper-prefixes.php`.
 */
/**
 * Quick fix script to update helper file with correct prefixes
 * Run this once: /api/hr/fix-helper-prefixes.php
 */

$file = __DIR__ . '/../core/formatted-id-helper.php';
$content = file_get_contents($file);

// Replace SQL patterns
$content = str_replace("FROM attendance WHERE record_id REGEXP '^EM[0-9]+$'", "FROM attendance WHERE record_id REGEXP '^AT[0-9]+$'", $content);
$content = str_replace("FROM advances WHERE record_id REGEXP '^EM[0-9]+$'", "FROM advances WHERE record_id REGEXP '^AD[0-9]+$'", $content);
$content = str_replace("FROM salaries WHERE record_id REGEXP '^EM[0-9]+$'", "FROM salaries WHERE record_id REGEXP '^PA[0-9]+$'", $content);
$content = str_replace("FROM hr_documents WHERE record_id REGEXP '^EM[0-9]+$'", "FROM hr_documents WHERE record_id REGEXP '^DO[0-9]+$'", $content);
$content = str_replace("FROM cars WHERE record_id REGEXP '^EM[0-9]+$'", "FROM cars WHERE record_id REGEXP '^VE[0-9]+$'", $content);

// Replace return statements based on function context
$lines = explode("\n", $content);
$newLines = [];
$currentFunction = '';

foreach ($lines as $i => $line) {
    // Track which function we're in
    if (strpos($line, 'function generateHRAttendanceId') !== false) {
        $currentFunction = 'attendance';
    } elseif (strpos($line, 'function generateHRAdvanceId') !== false) {
        $currentFunction = 'advance';
    } elseif (strpos($line, 'function generateHRSalaryId') !== false) {
        $currentFunction = 'salary';
    } elseif (strpos($line, 'function generateHRDocumentId') !== false) {
        $currentFunction = 'document';
    } elseif (strpos($line, 'function generateHRVehicleId') !== false) {
        $currentFunction = 'vehicle';
    } elseif (strpos($line, 'function generateHREmployeeId') !== false) {
        $currentFunction = 'employee';
    } elseif (strpos($line, 'function ') !== false) {
        $currentFunction = '';
    }
    
    // Replace return statement based on current function
    if (strpos($line, "return 'EM'") !== false) {
        if ($currentFunction === 'attendance') {
            $line = str_replace("return 'EM'", "return 'AT'", $line);
        } elseif ($currentFunction === 'advance') {
            $line = str_replace("return 'EM'", "return 'AD'", $line);
        } elseif ($currentFunction === 'salary') {
            $line = str_replace("return 'EM'", "return 'PA'", $line);
        } elseif ($currentFunction === 'document') {
            $line = str_replace("return 'EM'", "return 'DO'", $line);
        } elseif ($currentFunction === 'vehicle') {
            $line = str_replace("return 'EM'", "return 'VE'", $line);
        }
    }
    
    $newLines[] = $line;
}
$content = implode("\n", $newLines);

// Update comments
$content = str_replace("Generate formatted ID for attendance table (format: EM0001, EM0002, etc.)", "Generate formatted ID for attendance table (format: AT0001, AT0002, etc.)", $content);
$content = str_replace("Generate formatted ID for advances table (format: EM0001, EM0002, etc.)", "Generate formatted ID for advances table (format: AD0001, AD0002, etc.)", $content);
$content = str_replace("Generate formatted ID for salaries table (format: EM0001, EM0002, etc.)", "Generate formatted ID for salaries table (format: PA0001, PA0002, etc.)", $content);
$content = str_replace("Generate formatted ID for hr_documents table (format: EM0001, EM0002, etc.)", "Generate formatted ID for hr_documents table (format: DO0001, DO0002, etc.)", $content);
$content = str_replace("Generate formatted ID for cars table (format: EM0001, EM0002, etc.)", "Generate formatted ID for cars table (format: VE0001, VE0002, etc.)", $content);

file_put_contents($file, $content);

header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Helper file updated with correct prefixes']);
?>

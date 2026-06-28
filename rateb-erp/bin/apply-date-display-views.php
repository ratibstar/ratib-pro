<?php
/**
 * Replace View::escape with View::formatDate for date-like display fields only.
 */
declare(strict_types=1);

$root = dirname(__DIR__) . '/views';
$dateNames = [
    'entry_date', 'order_date', 'payment_date', 'due_date', 'voucher_date', 'evaluation_date',
    'created_at', 'updated_at', 'issued_at', 'sent_at', 'approved_at', 'submitted_at',
    'last_run_at', 'next_expected_at', 'start_date', 'end_date', 'deadline', 'hire_date',
    'renewal_date', 'new_end_date', 'contract_end_date', 'follow_up_date', 'comm_date',
    'period_date', 'invoice_date', 'ends_at', 'customs_clearance_date',
];

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$changed = [];
foreach ($rii as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        continue;
    }
    $orig = implode("\n", $lines) . "\n";
    $out = [];
    foreach ($lines as $line) {
        $newLine = $line;
        if (str_contains($line, 'View::escape') && !str_contains($line, 'View::formatDate')) {
            $isInputValue = str_contains($line, 'value="<?php') || str_contains($line, 'data-due-date=');
            if (!$isInputValue) {
                foreach ($dateNames as $field) {
                    if (str_contains($line, "['{$field}'") || str_contains($line, "[\"{$field}\"]")) {
                        $newLine = str_replace('View::escape', 'View::formatDate', $line);
                        break;
                    }
                }
            }
        }
        $out[] = $newLine;
    }
    $content = implode("\n", $out) . "\n";
    if ($content !== $orig) {
        file_put_contents($path, $content);
        $changed[] = str_replace(dirname(__DIR__) . DIRECTORY_SEPARATOR, '', $path);
    }
}

echo 'Updated ' . count($changed) . " view file(s)\n";
foreach ($changed as $rel) {
    echo "  - $rel\n";
}

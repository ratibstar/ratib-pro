<?php
/**
 * Move Remaining Non-Critical Files to Archive
 * Run: php archive/move-remaining-files.php
 */

chdir(__DIR__ . '/..');

echo "Moving remaining instruction files...\n";

$filesToMove = [
    'ARCHIVE_INSTRUCTIONS.txt',
    'HOW_TO_ARCHIVE_FILES.txt',
    'PRODUCTION_FILES_LIST.txt',
    'RUN_ME_FIRST.txt',
    'PRODUCTION_DEPLOYMENT_SUMMARY.md'
];

foreach ($filesToMove as $file) {
    if (file_exists($file)) {
        rename($file, 'archive/' . basename($file));
        echo "Moved: $file\n";
    }
}

echo "\n✅ Done! All instruction files moved to archive.\n";
echo "Only DEPLOYMENT_READY.txt remains in root (for reference).\n";
?>

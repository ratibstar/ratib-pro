<?php
/**
 * Move Non-Critical Files to Archive Folder
 * Run this file from browser or command line: php move-to-archive.php
 */

echo "Creating archive folder...\n";
if (!is_dir('archive')) {
    mkdir('archive', 0755, true);
}

// Move .md files
echo "Moving .md files...\n";
$mdFiles = glob('*.md');
foreach ($mdFiles as $file) {
    if (basename($file) !== 'PRODUCTION_DEPLOYMENT_GUIDE.md') {
        rename($file, 'archive/' . basename($file));
        echo "Moved: $file\n";
    }
}

// Move folders
$foldersToMove = [
    'docs', 'tests', 'setup', 'errors', 'exports', 'php', 
    'Forms', 'dashboard', 'cron', 'database', 'backups', 'Utils'
];

echo "\nMoving folders...\n";
foreach ($foldersToMove as $folder) {
    if (is_dir($folder)) {
        rename($folder, 'archive/' . $folder);
        echo "Moved folder: $folder\n";
    }
}

// Move duplicate path folders
if (is_dir('path=api')) {
    rename('path=api', 'archive/path-api');
    echo "Moved: path=api\n";
}
if (is_dir('path=pages')) {
    rename('path=pages', 'archive/path-pages');
    echo "Moved: path=pages\n";
}

// Move script files
$scripts = ['PRODUCTION_DEPLOYMENT_GUIDE.md', 'move-to-archive.ps1', 'move-to-archive.bat', 'move-to-archive.php'];
foreach ($scripts as $script) {
    if (file_exists($script)) {
        rename($script, 'archive/' . basename($script));
        echo "Moved: $script\n";
    }
}

echo "\n✅ Done! All non-critical files moved to archive folder.\n";
echo "The archive folder can be excluded when uploading to production server.\n";
?>

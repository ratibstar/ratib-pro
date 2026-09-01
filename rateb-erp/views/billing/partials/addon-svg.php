<?php
declare(strict_types=1);

/** @var string $icon */
$icon = strtolower(trim((string) ($icon ?? 'default')));
$icons = [
    'crm' => '<path d="M8 8a3 3 0 1 0-3-3 3 3 0 0 0 3 3zm8 0a3 3 0 1 0-3-3 3 3 0 0 0 3 3zM2.5 19v-1.2C2.5 15.5 5.4 14 8 14c.8 0 1.6.1 2.3.4A6.5 6.5 0 0 0 16 14c2.6 0 5.5 1.5 5.5 3.8V19z"/>',
    'monthly' => '<path d="M7 3h2v2h6V3h2v2h3v16H4V5h3V3zm13 6H4v10h16V9z"/>',
    'yearly' => '<path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm1 5v4.6l3.2 1.9-1 1.7L11 13V7h2z"/>',
    'savings' => '<path d="M12 2l2.4 6.9H22l-5.6 4.1 2.1 6.9L12 16.8 5.5 20l2.1-6.9L2 8.9h7.6z"/>',
    'secure' => '<path d="M12 2 4 6v6c0 5 3.4 8.7 8 10 4.6-1.3 8-5 8-10V6l-8-4zm0 11.2 4.2-4.2 1.4 1.4L12 16 6.4 10.4l1.4-1.4z"/>',
    'feature' => '<path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/>',
    'active' => '<path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/>',
    'pending' => '<circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3"/>',
    'activating' => '<path d="M12 3a9 9 0 1 1-9 9h2a7 7 0 1 0 7-7V3z"/>',
    'failed' => '<path d="M11 7h2v7h-2zm0 9h2v2h-2z"/><path d="M12 2 1 21h22L12 2z" fill="none" stroke="currentColor" stroke-width="2"/>',
    'lock' => '<path d="M17 8h-1V6a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2zm-7-2a2 2 0 0 1 4 0v2h-4V6z"/>',
    'expired' => '<path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm5 11H7v-2h10z"/>',
    'unavailable' => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 8l8 8M16 8l-8 8"/>',
    'default' => '<path d="M4 6h16v12H4zM4 6l8 6 8-6"/>',
];
$path = $icons[$icon] ?? $icons['default'];
?>
<svg class="rateb-addon-svg rateb-addon-svg--<?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><?php echo $path; ?></svg>

<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/includes/control/back-button.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/includes/control/back-button.php`.
 */
$backUrl = $backUrl ?? pageUrl('control/dashboard.php');
$backText = $backText ?? 'Back to Dashboard';
?>
<a href="<?php echo htmlspecialchars($backUrl); ?>" class="back-button">
    <i class="fas fa-arrow-left"></i>
    <span><?php echo htmlspecialchars($backText); ?></span>
</a>

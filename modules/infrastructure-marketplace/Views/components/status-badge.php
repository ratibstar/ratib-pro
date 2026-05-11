<?php
declare(strict_types=1);

$status = strtolower((string) ($status ?? 'queued'));
$label = strtoupper((string) ($label ?? $status));
?>
<span class="infra-status-badge infra-status-badge--<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
</span>


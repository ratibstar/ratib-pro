<?php
declare(strict_types=1);
/** Shared empty-state for CRM panels (never dump raw [] JSON). */
$message = (string) ($message ?? __('no_records'));
?>
<p class="text-muted small mb-0 py-3 px-1"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>

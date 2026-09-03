<?php
declare(strict_types=1);
$report = is_array($report ?? null) ? $report : [];
$findings = is_array($report['findings'] ?? null) ? $report['findings'] : [];
$summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
$errors = is_array($report['check_errors'] ?? null) ? $report['check_errors'] : [];
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('crm_data_integrity_audit')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <span class="badge text-bg-secondary"><?php echo htmlspecialchars(__('crm_auto_delete_off'), ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_integrity_findings'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo (int) ($summary['total_findings'] ?? 0); ?></div></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_check_errors'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo (int) ($summary['check_errors'] ?? count($errors)); ?></div></div></div>
    </div>
    <p class="text-muted small"><?php echo htmlspecialchars(__('crm_integrity_findings_help'), ENT_QUOTES, 'UTF-8'); ?></p>
    <?php if ($errors !== []): ?>
        <div class="alert alert-warning small">
            <?php foreach ($errors as $err): ?>
                <div><?php echo htmlspecialchars((string) (($err['check'] ?? '') . ': ' . ($err['error'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="table-responsive border rounded">
        <table class="table table-sm mb-0 align-middle">
            <thead>
            <tr>
                <th><?php echo htmlspecialchars(__('crm_code'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th><?php echo htmlspecialchars(__('severity'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th><?php echo htmlspecialchars(__('crm_entity'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th><?php echo htmlspecialchars(__('crm_message'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th><?php echo htmlspecialchars(__('crm_safe_remediation'), ENT_QUOTES, 'UTF-8'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($findings as $f): ?>
                <tr>
                    <td class="small"><?php echo htmlspecialchars((string) ($f['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($f['severity'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="small">#<?php echo (int) ($f['entity_id'] ?? 0); ?></td>
                    <td class="small"><?php echo htmlspecialchars((string) ($f['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="small"><?php echo htmlspecialchars((string) ($f['remediation'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($findings === []): ?>
                <tr><td colspan="5" class="text-muted p-3"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

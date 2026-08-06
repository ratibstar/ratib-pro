<?php
declare(strict_types=1);

use Rateb\App\Core\View;

/** @var array<string,mixed> $report */
$report = $report ?? [];
$columns = is_array($report['columns'] ?? null) ? $report['columns'] : [];
$rows = is_array($report['rows'] ?? null) ? $report['rows'] : [];
$summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?php echo View::escape((string) ($report['title'] ?? __('logistics_reports'))); ?></h1>
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo rateb_app_url('logistics/reports'); ?>"><?php echo __('back'); ?></a>
</div>

<?php if ($summary !== []) { ?>
    <div class="rateb-card mb-3">
        <div class="rateb-card-body">
            <pre class="mb-0 small" style="white-space:pre-wrap;"><?php echo View::escape(json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: ''); ?></pre>
        </div>
    </div>
<?php } ?>

<div class="rateb-card">
    <div class="rateb-card-body p-0 table-responsive">
        <table class="table table-sm mb-0">
            <thead>
            <tr>
                <?php foreach ($columns as $col) { ?>
                    <th><?php echo View::escape((string) $col); ?></th>
                <?php } ?>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows === []) { ?>
                <tr><td colspan="<?php echo max(1, count($columns)); ?>" class="text-muted px-3 py-3"><?php echo __('no_records'); ?></td></tr>
            <?php } ?>
            <?php foreach ($rows as $row) { ?>
                <tr>
                    <?php foreach ($columns as $col) { ?>
                        <td><?php echo View::escape((string) ($row[$col] ?? '')); ?></td>
                    <?php } ?>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>

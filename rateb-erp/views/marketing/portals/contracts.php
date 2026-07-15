<section class="rateb-portal-section">
    <div class="container">
        <h1><?php echo __('contracts') ?: 'Contracts'; ?></h1>
        <p class="rateb-portal-lead"><?php echo __('contracts_readonly_hint') ?: 'Read-only view of ERP contracts.'; ?></p>
        <table class="rateb-portal-table">
            <thead><tr><th><?php echo __('contract_no') ?: 'No.'; ?></th><th><?php echo __('title') ?: 'Title'; ?></th><th><?php echo __('status') ?: 'Status'; ?></th><th><?php echo __('dates') ?: 'Dates'; ?></th><th><?php echo __('value') ?: 'Value'; ?></th></tr></thead>
            <tbody>
            <?php foreach ($contracts ?? [] as $c) { ?>
            <tr>
                <td><?php echo Rateb\App\Core\View::escape((string) ($c['contract_no'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($c['title'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($c['status'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($c['start_date'] ?? '') . ' → ' . (string) ($c['end_date'] ?? '')); ?></td>
                <td><?php echo number_format((float) ($c['value'] ?? 0), 2); ?></td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</section>

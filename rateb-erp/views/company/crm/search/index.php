<?php
declare(strict_types=1);
$result = $result ?? ['results' => [], 'total' => 0];
$results = $result['results'] ?? [];
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('crm_unified_search')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="get" class="row g-2 mb-4">
        <div class="col-md-6"><input class="form-control" name="q" value="<?php echo htmlspecialchars((string) ($q ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search CRM…" autofocus></div>
        <div class="col-auto"><button class="btn btn-primary" type="submit"><?php echo htmlspecialchars(__('search'), ENT_QUOTES, 'UTF-8'); ?></button></div>
    </form>
    <p class="text-muted">Total: <?php echo (int) ($result['total'] ?? 0); ?></p>
    <?php if (($result['ranked'] ?? []) !== []): ?>
    <h2 class="h5"><?php echo htmlspecialchars(__('crm_ranked_results'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <ul class="list-group mb-4">
        <?php foreach (($result['ranked'] ?? []) as $row): ?>
        <li class="list-group-item small d-flex justify-content-between">
            <span>
                <?php echo htmlspecialchars((string) ($row['entity_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                #<?php echo (int) ($row['id'] ?? 0); ?>
                · <?php echo htmlspecialchars((string) ($row['title'] ?? $row['name'] ?? $row['full_name'] ?? $row['subject'] ?? $row['quotation_no'] ?? $row['opportunity_no'] ?? $row['lead_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <span class="text-muted">rel <?php echo (int) ($row['relevance'] ?? 0); ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <?php foreach (['leads','contacts','companies','opportunities','quotations','activities'] as $type): ?>
    <h2 class="h5 mt-3"><?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?></h2>
    <?php if (($results[$type] ?? []) === []): ?>
    <p class="text-muted small"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></p>
    <?php else: ?>
    <ul class="list-group mb-3">
        <?php foreach ($results[$type] as $row): ?>
        <li class="list-group-item small">
            #<?php echo (int) ($row['id'] ?? 0); ?>
            · <?php echo htmlspecialchars((string) ($row['title'] ?? $row['name'] ?? $row['full_name'] ?? $row['subject'] ?? $row['quotation_no'] ?? $row['opportunity_no'] ?? $row['lead_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
            <?php if (!empty($row['email'])): ?> · <?php echo htmlspecialchars((string) $row['email'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <?php endforeach; ?>
</div>

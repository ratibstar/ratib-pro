<?php
/** @var string $mode server|client */
$mode = (string) ($mode ?? 'client');
$search = trim((string) ($search ?? ''));
$routePrefix = (string) ($routePrefix ?? '');
$formAction = (string) ($formAction ?? '');
$searchKey = (string) ($searchKey ?? 'q');
$searchClearUrl = (string) ($searchClearUrl ?? '');
$preserve = $preserve ?? ['company_id', 'status', 'date_from', 'date_to', 'from', 'to'];
?>
<div class="rateb-table-search" data-rateb-table-search-wrap="1"<?php echo $mode === 'server' ? ' data-rateb-server-search="1"' : ''; ?>>
    <?php if ($mode === 'server') { ?>
    <form method="get" action="<?php echo Rateb\App\Core\View::escape($formAction !== '' ? $formAction : rateb_url($routePrefix)); ?>" class="rateb-table-search-form" data-rateb-table-search-form="1">
        <?php foreach ($preserve as $key) {
            if (!isset($_GET[$key]) || (string) $_GET[$key] === '') {
                continue;
            } ?>
        <input type="hidden" name="<?php echo Rateb\App\Core\View::escape((string) $key); ?>" value="<?php echo Rateb\App\Core\View::escape((string) $_GET[$key]); ?>">
        <?php } ?>
        <div class="rateb-table-search-row">
            <div class="input-group input-group-sm rateb-table-search-input">
                <span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span>
                <input type="search" name="<?php echo Rateb\App\Core\View::escape($searchKey); ?>" class="form-control" value="<?php echo Rateb\App\Core\View::escape($search); ?>"
                    placeholder="<?php echo __('search_table_placeholder'); ?>" autocomplete="off"
                    aria-label="<?php echo __('search'); ?>">
                <?php if ($search !== '') {
                    $clearHref = $searchClearUrl !== ''
                        ? $searchClearUrl
                        : rateb_list_url($routePrefix, rateb_list_query_except([$searchKey]));
                ?>
                <a href="<?php echo Rateb\App\Core\View::escape($clearHref); ?>" class="btn btn-outline-secondary" title="<?php echo __('clear'); ?>">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </a>
                <?php } ?>
                <button type="submit" class="btn btn-primary"><?php echo __('search'); ?></button>
            </div>
        </div>
    </form>
    <?php } else { ?>
    <div class="rateb-table-search-row">
        <div class="input-group input-group-sm rateb-table-search-input" data-rateb-table-search-client="1">
            <span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span>
            <input type="search" class="form-control" data-rateb-table-search-field="1"
                placeholder="<?php echo __('search_table_placeholder'); ?>" autocomplete="off"
                aria-label="<?php echo __('search'); ?>">
            <button type="button" class="btn btn-outline-secondary d-none" data-rateb-table-search-clear="1" title="<?php echo __('clear'); ?>">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <span class="rateb-table-search-meta small text-muted d-none" data-rateb-search-meta="1"></span>
    </div>
    <?php } ?>
</div>

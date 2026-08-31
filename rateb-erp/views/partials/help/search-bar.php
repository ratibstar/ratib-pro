<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $searchHits */
/** @var string $searchQuery */
/** @var bool $hcSearchCompact */

use Rateb\App\Core\View;

$searchQuery = trim((string) ($searchQuery ?? ''));
$searchHits = is_array($searchHits ?? null) ? $searchHits : [];
$hcSearchCompact = !empty($hcSearchCompact);
?>
<div class="hc-search<?php echo $hcSearchCompact ? ' hc-search--compact' : ''; ?>" id="hc-search-form" role="search">
    <label class="visually-hidden" for="hc-search-input"><?php echo View::escape(__('help_search_label')); ?></label>
    <div class="hc-search__field">
        <i class="fas fa-magnifying-glass hc-search__icon" aria-hidden="true"></i>
        <input type="text"
               id="hc-search-input"
               class="hc-search__input"
               value="<?php echo View::escape($searchQuery); ?>"
               autocomplete="off"
               spellcheck="false"
               placeholder="<?php echo View::escape(__('help_search_placeholder')); ?>"
               aria-controls="hc-search-results"
               aria-expanded="<?php echo $searchHits !== [] ? 'true' : 'false'; ?>"
               aria-autocomplete="list">
        <button type="button" class="hc-search__clear" id="hc-search-clear"<?php echo $searchQuery === '' ? ' hidden' : ''; ?>>
            <?php echo View::escape(__('help_search_clear')); ?>
        </button>
    </div>
    <div class="hc-search__results" id="hc-search-results" role="listbox"<?php echo $searchHits === [] ? ' hidden' : ''; ?>>
        <?php foreach ($searchHits as $i => $hit) {
            $hitHref = (string) ($hit['help_url'] ?? '');
            if ($hitHref === '') {
                $hitType = (string) ($hit['type'] ?? 'article');
                $hitSlug = (string) ($hit['slug'] ?? '');
                $hitHref = $hitType === 'module'
                    ? rateb_url('admin/help/module/' . rawurlencode($hitSlug))
                    : rateb_url('admin/help/article/' . rawurlencode($hitSlug));
            }
            $meta = ((string) ($hit['type'] ?? '')) === 'module' ? '' : (string) ($hit['module_title'] ?? '');
            $mins = (int) ($hit['minutes'] ?? 0);
            if ($mins > 0) {
                $meta = trim($meta . ' · ' . $mins . 'm');
            }
            ?>
        <a class="hc-search__hit<?php echo $i === 0 ? ' is-active' : ''; ?>"
           data-hc-nav="1"
           role="option"
           href="<?php echo View::escape($hitHref); ?>">
            <span class="hc-search__hit-icon"><i class="fas <?php echo View::escape((string) ($hit['icon'] ?? 'fa-circle-question')); ?>" aria-hidden="true"></i></span>
            <span>
                <span class="hc-search__hit-title"><?php echo View::escape((string) ($hit['title'] ?? '')); ?></span>
                <span class="hc-search__hit-meta"><?php echo View::escape($meta); ?></span>
            </span>
        </a>
        <?php } ?>
    </div>
    <p class="hc-search__empty" id="hc-search-empty"<?php echo ($searchQuery !== '' && $searchHits === []) ? '' : ' hidden'; ?>>
        <?php echo View::escape(__('help_search_empty')); ?>
    </p>
</div>

<?php
declare(strict_types=1);
/** @var array<string,mixed> $page */
/** @var list<array<string,mixed>> $pages */
/** @var list<array{section:array<string,mixed>,blocks:list<array<string,mixed>>}> $tree */
/** @var array<string,array<string,string>> $blockTypes */
/** @var list<array<string,mixed>> $versions */
/** @var string $csrf */
$pageId = (int) ($page['id'] ?? 0);
$pageSlug = (string) ($page['slug'] ?? '');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(rateb_asset('css/website-builder.css'), ENT_QUOTES, 'UTF-8'); ?>">
<div class="container-fluid py-3 wb-admin" id="websiteBuilderRoot"
     data-page-id="<?php echo $pageId; ?>"
     data-page-slug="<?php echo htmlspecialchars($pageSlug, ENT_QUOTES, 'UTF-8'); ?>"
     data-csrf="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"
     data-reorder-url="<?php echo htmlspecialchars($saveReorderUrl ?? '', ENT_QUOTES, 'UTF-8'); ?>"
     data-add-section-url="<?php echo htmlspecialchars($addSectionUrl ?? '', ENT_QUOTES, 'UTF-8'); ?>"
     data-add-block-url="<?php echo htmlspecialchars($addBlockUrl ?? '', ENT_QUOTES, 'UTF-8'); ?>"
     data-update-block-url="<?php echo htmlspecialchars($updateBlockUrl ?? '', ENT_QUOTES, 'UTF-8'); ?>"
     data-delete-block-url="<?php echo htmlspecialchars($deleteBlockUrl ?? '', ENT_QUOTES, 'UTF-8'); ?>"
     data-delete-section-url="<?php echo htmlspecialchars($deleteSectionUrl ?? '', ENT_QUOTES, 'UTF-8'); ?>"
     data-publish-url="<?php echo htmlspecialchars($publishUrl ?? '', ENT_QUOTES, 'UTF-8'); ?>"
     data-draft-url="<?php echo htmlspecialchars($draftUrl ?? '', ENT_QUOTES, 'UTF-8'); ?>"
     data-preview-url="<?php echo htmlspecialchars($previewUrl ?? '', ENT_QUOTES, 'UTF-8'); ?>"
     data-rollback-url="<?php echo htmlspecialchars($rollbackUrl ?? '', ENT_QUOTES, 'UTF-8'); ?>"
     data-schedule-url="<?php echo htmlspecialchars($scheduleUrl ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <div class="wb-builder-toolbar d-flex flex-wrap gap-2 align-items-center mb-3">
        <h1 class="h4 mb-0 me-auto"><?php echo htmlspecialchars(__('website_builder') ?: 'Website builder', ENT_QUOTES, 'UTF-8'); ?></h1>
        <form method="get" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/builder')), ENT_QUOTES, 'UTF-8'); ?>" class="d-flex gap-2">
            <select name="page_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php foreach (($pages ?? []) as $p) { ?>
                <option value="<?php echo (int) $p['id']; ?>"<?php echo ((int) $p['id'] === $pageId) ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) ($p['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php } ?>
            </select>
        </form>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="wbBtnDraft">Draft</button>
        <button type="button" class="btn btn-sm btn-outline-primary" id="wbBtnPreview">Preview</button>
        <button type="button" class="btn btn-sm btn-success" id="wbBtnPublish">Publish</button>
        <input type="datetime-local" class="form-control form-control-sm wb-schedule-input" id="wbScheduleAt" aria-label="Schedule">
        <button type="button" class="btn btn-sm btn-outline-warning" id="wbBtnSchedule">Schedule</button>
    </div>

    <div class="row g-3">
        <div class="col-lg-3">
            <div class="wb-palette rateb-card">
                <div class="rateb-card-header"><strong>Blocks</strong></div>
                <div class="rateb-card-body wb-palette-list">
                    <?php foreach (($blockTypes ?? []) as $type => $meta) { ?>
                    <button type="button" class="wb-palette-item" data-block-type="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fas <?php echo htmlspecialchars($meta['icon'] ?? 'fa-cube', ENT_QUOTES, 'UTF-8'); ?>"></i>
                        <span><?php echo htmlspecialchars($meta['label_en'] ?? $type, ENT_QUOTES, 'UTF-8'); ?></span>
                    </button>
                    <?php } ?>
                </div>
                <div class="rateb-card-body border-top">
                    <button type="button" class="btn btn-sm btn-primary w-100" id="wbAddSection">+ Section</button>
                </div>
            </div>
            <div class="wb-versions rateb-card mt-3">
                <div class="rateb-card-header"><strong>Versions</strong></div>
                <ul class="list-group list-group-flush" id="wbVersionList">
                    <?php foreach (($versions ?? []) as $v) { ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <span>v<?php echo (int) ($v['version_no'] ?? 0); ?> · <?php echo htmlspecialchars((string) ($v['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                        <button type="button" class="btn btn-sm btn-outline-danger wb-rollback" data-version-id="<?php echo (int) ($v['id'] ?? 0); ?>">Rollback</button>
                    </li>
                    <?php } ?>
                </ul>
            </div>
        </div>
        <div class="col-lg-9">
            <div id="wbCanvas" class="wb-canvas">
                <?php foreach (($tree ?? []) as $pack) {
                    $section = $pack['section'] ?? [];
                    $sid = (int) ($section['id'] ?? 0);
                    ?>
                <div class="wb-section-card rateb-card" data-section-id="<?php echo $sid; ?>" draggable="true">
                    <div class="rateb-card-header d-flex align-items-center gap-2">
                        <span class="wb-drag-handle" title="Drag"><i class="fas fa-grip-vertical"></i></span>
                        <strong><?php echo htmlspecialchars((string) ($section['section_key'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span class="text-muted small"><?php echo htmlspecialchars((string) ($section['title_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                        <button type="button" class="btn btn-sm btn-outline-danger ms-auto wb-delete-section" data-section-id="<?php echo $sid; ?>">Delete</button>
                    </div>
                    <div class="rateb-card-body">
                        <div class="wb-block-list" data-section-id="<?php echo $sid; ?>">
                            <?php foreach (($pack['blocks'] ?? []) as $b) {
                                $bid = (int) ($b['id'] ?? 0);
                                ?>
                            <div class="wb-block-card" data-block-id="<?php echo $bid; ?>" draggable="true">
                                <span class="wb-drag-handle"><i class="fas fa-grip-lines"></i></span>
                                <span class="wb-block-type"><?php echo htmlspecialchars((string) ($b['block_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                <input class="form-control form-control-sm wb-block-title" name="title_en" value="<?php echo htmlspecialchars((string) ($b['title_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-block-id="<?php echo $bid; ?>">
                                <button type="button" class="btn btn-sm btn-outline-primary wb-save-block" data-block-id="<?php echo $bid; ?>">Save</button>
                                <button type="button" class="btn btn-sm btn-outline-danger wb-delete-block" data-block-id="<?php echo $bid; ?>">×</button>
                            </div>
                            <?php } ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-2 wb-drop-hint" data-section-id="<?php echo $sid; ?>">Drop blocks here / click palette then this section</button>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<script src="<?php echo htmlspecialchars(rateb_asset('js/website-builder.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>

<?php
declare(strict_types=1);
/** @var array<string,mixed> $tokens */
/** @var array<string,mixed> $theme */
/** @var array{available:list<array<string,mixed>>,installed:list<array<string,mixed>>,active:?array<string,mixed>,preview:?array<string,mixed>} $marketplace */
/** @var array<string,mixed> $override */
/** @var list<array<string,mixed>> $backups */
$c = $tokens['colors'] ?? [];
$ty = $tokens['typography'] ?? [];
$marketplace = $marketplace ?? ['available' => [], 'installed' => [], 'active' => null, 'preview' => null];
$active = $marketplace['active'] ?? null;
$preview = $marketplace['preview'] ?? null;
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(rateb_asset('css/website-builder.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(rateb_asset('css/website-theme-marketplace.css'), ENT_QUOTES, 'UTF-8'); ?>">
<div class="container-fluid py-3 wb-admin" id="websiteThemeMarketplace"
     data-csrf="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>"
     data-install-url="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/theme/marketplace/install')), ENT_QUOTES, 'UTF-8'); ?>"
     data-activate-url="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/theme/marketplace/activate')), ENT_QUOTES, 'UTF-8'); ?>"
     data-preview-url="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/theme/marketplace/preview')), ENT_QUOTES, 'UTF-8'); ?>"
     data-clear-preview-url="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/theme/marketplace/clear-preview')), ENT_QUOTES, 'UTF-8'); ?>"
     data-duplicate-url="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/theme/marketplace/duplicate')), ENT_QUOTES, 'UTF-8'); ?>"
     data-reset-url="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/theme/marketplace/reset')), ENT_QUOTES, 'UTF-8'); ?>"
     data-delete-url="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/theme/marketplace/delete')), ENT_QUOTES, 'UTF-8'); ?>"
     data-export-url="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/theme/marketplace/export')), ENT_QUOTES, 'UTF-8'); ?>"
     data-import-url="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/theme/marketplace/import')), ENT_QUOTES, 'UTF-8'); ?>"
     data-demo-url="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/theme/marketplace/demo')), ENT_QUOTES, 'UTF-8'); ?>"
     data-backup-url="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/theme/marketplace/backup')), ENT_QUOTES, 'UTF-8'); ?>"
     data-restore-url="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/theme/marketplace/restore')), ENT_QUOTES, 'UTF-8'); ?>">
    <h1 class="h3 mb-2"><?php echo htmlspecialchars((string) ($title ?? 'Theme Marketplace'), ENT_QUOTES, 'UTF-8'); ?></h1>
    <p class="text-muted mb-4"><?php echo htmlspecialchars(__('website_theme_marketplace_hint') ?: 'Install, preview, and customize themes. Overrides never modify the base package.', ENT_QUOTES, 'UTF-8'); ?></p>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="wb-theme-status-card">
                <div class="small text-muted">Active</div>
                <strong><?php echo htmlspecialchars((string) ($active['name_en'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
        </div>
        <div class="col-md-4">
            <div class="wb-theme-status-card">
                <div class="small text-muted">Preview</div>
                <strong><?php echo htmlspecialchars((string) ($preview['name_en'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></strong>
                <?php if ($preview) { ?>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="wbThemeClearPreview">Clear preview</button>
                <?php } ?>
            </div>
        </div>
        <div class="col-md-4">
            <div class="wb-theme-status-card">
                <div class="small text-muted">Import package</div>
                <input type="file" class="form-control form-control-sm" id="wbThemeImportFile" accept="application/json,.json">
                <button type="button" class="btn btn-sm btn-primary mt-2" id="wbThemeImportBtn">Import</button>
            </div>
        </div>
    </div>

    <h2 class="h5">Available themes</h2>
    <div class="row g-3 mb-4">
        <?php foreach (($marketplace['available'] ?? []) as $pkg) { ?>
        <div class="col-md-4">
            <div class="wb-theme-card">
                <h3 class="h6 mb-1"><?php echo htmlspecialchars((string) ($pkg['name_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                <div class="small text-muted mb-2"><?php echo htmlspecialchars((string) ($pkg['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · v<?php echo htmlspecialchars((string) ($pkg['version'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <button type="button" class="btn btn-sm btn-primary wb-theme-install" data-slug="<?php echo htmlspecialchars((string) ($pkg['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Install</button>
            </div>
        </div>
        <?php } ?>
        <?php if (($marketplace['available'] ?? []) === []) { ?>
        <div class="col-12 text-muted">No packages found under themes/</div>
        <?php } ?>
    </div>

    <h2 class="h5">Installed themes</h2>
    <div class="table-responsive mb-4">
        <table class="table table-striped align-middle">
            <thead><tr><th>Name</th><th>Package</th><th>Status</th><th>Source</th><th></th></tr></thead>
            <tbody>
            <?php foreach (($marketplace['installed'] ?? []) as $inst) {
                $iid = (int) ($inst['id'] ?? 0);
                ?>
                <tr>
                    <td><?php echo htmlspecialchars((string) ($inst['name_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($inst['package_slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><span class="badge text-bg-secondary"><?php echo htmlspecialchars((string) ($inst['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td><?php echo htmlspecialchars((string) ($inst['source'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="text-end text-nowrap wb-theme-actions">
                        <button type="button" class="btn btn-sm btn-success wb-theme-activate" data-id="<?php echo $iid; ?>">Activate</button>
                        <button type="button" class="btn btn-sm btn-outline-primary wb-theme-preview" data-id="<?php echo $iid; ?>">Preview</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary wb-theme-demo" data-id="<?php echo $iid; ?>">Demo</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary wb-theme-duplicate" data-id="<?php echo $iid; ?>">Duplicate</button>
                        <button type="button" class="btn btn-sm btn-outline-warning wb-theme-reset" data-id="<?php echo $iid; ?>">Reset</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary wb-theme-export" data-id="<?php echo $iid; ?>">Export</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary wb-theme-backup" data-id="<?php echo $iid; ?>">Backup</button>
                        <button type="button" class="btn btn-sm btn-outline-danger wb-theme-delete" data-id="<?php echo $iid; ?>">Delete</button>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>

    <?php if (($backups ?? []) !== []) { ?>
    <h2 class="h5">Active theme backups</h2>
    <ul class="list-group mb-4">
        <?php foreach ($backups as $b) { ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <span>v<?php echo (int) ($b['version_no'] ?? 0); ?> · <?php echo htmlspecialchars((string) ($b['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars((string) ($b['kind'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            <button type="button" class="btn btn-sm btn-outline-primary wb-theme-restore" data-version-id="<?php echo (int) ($b['id'] ?? 0); ?>">Restore</button>
        </li>
        <?php } ?>
    </ul>
    <?php } ?>

    <h2 class="h5">Customize (agency override)</h2>
    <p class="small text-muted">Saves into override layer — base package stays untouched.</p>
    <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/theme')), ENT_QUOTES, 'UTF-8'); ?>" class="row g-3">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        <div class="col-md-3"><label class="form-label">Primary</label><input type="color" class="form-control form-control-color" name="tokens[colors][primary]" value="<?php echo htmlspecialchars((string) ($c['primary'] ?? '#1a5fb4'), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-3"><label class="form-label">Secondary</label><input type="color" class="form-control form-control-color" name="tokens[colors][secondary]" value="<?php echo htmlspecialchars((string) ($c['secondary'] ?? '#3584e4'), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-3"><label class="form-label">Accent</label><input type="color" class="form-control form-control-color" name="tokens[colors][accent]" value="<?php echo htmlspecialchars((string) ($c['accent'] ?? '#26a269'), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-3"><label class="form-label">Text</label><input type="color" class="form-control form-control-color" name="tokens[colors][text]" value="<?php echo htmlspecialchars((string) ($c['text'] ?? '#241f31'), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-4"><label class="form-label">Font family</label><input class="form-control" name="tokens[typography][font_family]" value="<?php echo htmlspecialchars((string) ($ty['font_family'] ?? 'Tajawal'), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-4"><label class="form-label">Logo path</label><input class="form-control" name="logo_path" value="<?php echo htmlspecialchars((string) (($override['logo_path'] ?? null) ?: ($theme['logo_path'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-4"><label class="form-label">Favicon path</label><input class="form-control" name="favicon_path" value="<?php echo htmlspecialchars((string) (($override['favicon_path'] ?? null) ?: ($theme['favicon_path'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-3"><label class="form-label">Button radius</label><input class="form-control" name="tokens[buttons][radius]" value="<?php echo htmlspecialchars((string) (($tokens['buttons']['radius'] ?? '8px')), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-3"><label class="form-label">Card radius</label><input class="form-control" name="tokens[cards][radius]" value="<?php echo htmlspecialchars((string) (($tokens['cards']['radius'] ?? '12px')), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-3"><label class="form-label">Max width</label><input class="form-control" name="tokens[layout][max_width]" value="<?php echo htmlspecialchars((string) (($tokens['layout']['max_width'] ?? '1140px')), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-3"><label class="form-label">Direction</label>
            <select class="form-select" name="tokens[direction]">
                <?php foreach (['auto', 'rtl', 'ltr'] as $dir) { ?>
                <option value="<?php echo $dir; ?>"<?php echo (($tokens['direction'] ?? 'auto') === $dir) ? ' selected' : ''; ?>><?php echo strtoupper($dir); ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="col-12"><button class="btn btn-primary" type="submit">Save override</button></div>
    </form>
</div>
<script src="<?php echo htmlspecialchars(rateb_asset('js/website-theme-marketplace.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>

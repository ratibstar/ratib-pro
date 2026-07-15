<?php
declare(strict_types=1);
/** @var array<string,mixed> $tokens */
/** @var array<string,mixed> $theme */
$c = $tokens['colors'] ?? [];
$ty = $tokens['typography'] ?? [];
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(rateb_asset('css/website-builder.css'), ENT_QUOTES, 'UTF-8'); ?>">
<div class="container-fluid py-3 wb-admin">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? 'Theme'), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/theme')), ENT_QUOTES, 'UTF-8'); ?>" class="row g-3">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        <div class="col-md-3"><label class="form-label">Primary</label><input type="color" class="form-control form-control-color" name="tokens[colors][primary]" value="<?php echo htmlspecialchars((string) ($c['primary'] ?? '#1a5fb4'), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-3"><label class="form-label">Secondary</label><input type="color" class="form-control form-control-color" name="tokens[colors][secondary]" value="<?php echo htmlspecialchars((string) ($c['secondary'] ?? '#3584e4'), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-3"><label class="form-label">Accent</label><input type="color" class="form-control form-control-color" name="tokens[colors][accent]" value="<?php echo htmlspecialchars((string) ($c['accent'] ?? '#26a269'), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-3"><label class="form-label">Text</label><input type="color" class="form-control form-control-color" name="tokens[colors][text]" value="<?php echo htmlspecialchars((string) ($c['text'] ?? '#241f31'), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-4"><label class="form-label">Font family</label><input class="form-control" name="tokens[typography][font_family]" value="<?php echo htmlspecialchars((string) ($ty['font_family'] ?? 'Tajawal'), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-4"><label class="form-label">Logo path</label><input class="form-control" name="logo_path" value="<?php echo htmlspecialchars((string) ($theme['logo_path'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-4"><label class="form-label">Direction</label>
            <select class="form-select" name="tokens[direction]">
                <?php foreach (['auto', 'rtl', 'ltr'] as $dir) { ?>
                <option value="<?php echo $dir; ?>"<?php echo (($tokens['direction'] ?? 'auto') === $dir) ? ' selected' : ''; ?>><?php echo strtoupper($dir); ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="col-md-3"><label class="form-label">Button radius</label><input class="form-control" name="tokens[buttons][radius]" value="<?php echo htmlspecialchars((string) (($tokens['buttons']['radius'] ?? '8px')), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-3"><label class="form-label">Card radius</label><input class="form-control" name="tokens[cards][radius]" value="<?php echo htmlspecialchars((string) (($tokens['cards']['radius'] ?? '12px')), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-3"><label class="form-label">Max width</label><input class="form-control" name="tokens[layout][max_width]" value="<?php echo htmlspecialchars((string) (($tokens['layout']['max_width'] ?? '1140px')), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-3"><label class="form-label">Shadow MD</label><input class="form-control" name="tokens[shadows][md]" value="<?php echo htmlspecialchars((string) (($tokens['shadows']['md'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-12"><button class="btn btn-primary" type="submit">Save theme</button></div>
    </form>
</div>

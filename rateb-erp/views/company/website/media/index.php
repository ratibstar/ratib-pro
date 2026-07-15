<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $folders */
/** @var list<array<string,mixed>> $media */
/** @var int|null $folderId */
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(rateb_asset('css/website-builder.css'), ENT_QUOTES, 'UTF-8'); ?>">
<div class="container-fluid py-3 wb-admin" id="websiteMediaRoot"
     data-csrf="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>"
     data-upload-url="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/media/upload')), ENT_QUOTES, 'UTF-8'); ?>"
     data-folder-url="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/media/folder')), ENT_QUOTES, 'UTF-8'); ?>">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? 'Media'), ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <form class="d-flex gap-2" id="wbFolderForm">
                <input class="form-control" name="name" placeholder="New folder" required>
                <button class="btn btn-outline-primary" type="submit">Add folder</button>
            </form>
        </div>
        <div class="col-md-6">
            <form class="d-flex gap-2" id="wbUploadForm" enctype="multipart/form-data">
                <input type="file" class="form-control" name="file" accept="image/*,video/mp4,application/pdf" required>
                <button class="btn btn-primary" type="submit">Upload</button>
            </form>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/media')), ENT_QUOTES, 'UTF-8'); ?>">Root</a>
        <?php foreach (($folders ?? []) as $f) { ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/media') . '?folder=' . (int) $f['id']), ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fas fa-folder"></i> <?php echo htmlspecialchars((string) ($f['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
        </a>
        <?php } ?>
    </div>
    <div class="row g-3">
        <?php foreach (($media ?? []) as $m) { ?>
        <div class="col-6 col-md-3 col-xl-2">
            <div class="border rounded p-2 h-100">
                <?php if (str_starts_with((string) ($m['mime_type'] ?? ''), 'image/')) { ?>
                <img class="img-fluid wb-media-thumb" src="<?php echo htmlspecialchars(rateb_url('site/media/' . rawurlencode(basename((string) ($m['file_path'] ?? '')))), ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy">
                <?php } else { ?>
                <div class="text-muted small"><?php echo htmlspecialchars((string) ($m['mime_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php } ?>
                <div class="small text-truncate mt-1"><?php echo htmlspecialchars((string) ($m['file_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        </div>
        <?php } ?>
    </div>
</div>
<script src="<?php echo htmlspecialchars(rateb_asset('js/website-media.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>

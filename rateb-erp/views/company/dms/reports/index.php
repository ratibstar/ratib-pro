<?php declare(strict_types=1); /** @var array<string, array<string, int>> $board */ ?>
<div class="container-fluid py-3">
<h1 class="h3 mb-3"><?php echo htmlspecialchars((string)($title??''), ENT_QUOTES,'UTF-8'); ?></h1>
<?php foreach(($board??[]) as $entity => $counts): ?>
<div class="mb-3"><div class="text-muted small mb-2"><?php echo htmlspecialchars((string)$entity, ENT_QUOTES,'UTF-8'); ?></div>
<div class="row g-3"><?php foreach((array)$counts as $st => $cnt): ?><div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small"><?php echo htmlspecialchars((string)$st, ENT_QUOTES,'UTF-8'); ?></div><div class="fs-4 fw-semibold"><?php echo (int)$cnt; ?></div></div></div><?php endforeach; ?></div></div>
<?php endforeach; ?>
</div>

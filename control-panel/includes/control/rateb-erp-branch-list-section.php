<?php
/** CP branch list section — uses PlatformCompanyBranchService via $branchList */
$branches = $branchList['items'] ?? [];
$esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<form method="get" class="row g-2 mb-3 align-items-end">
    <?php foreach ($listQueryBase as $qk => $qv) { if ($qk !== 'q' && $qk !== 'status' && $qk !== 'branch_type' && $qk !== 'archive' && $qk !== 'sort' && $qk !== 'dir' && $qk !== 'page' && $qk !== 'per_page') { ?>
    <input type="hidden" name="<?php echo $esc((string) $qk); ?>" value="<?php echo $esc((string) $qv); ?>">
    <?php } } ?>
    <div class="col-md-3"><label class="form-label small mb-0">بحث</label><input type="search" name="q" class="form-control form-control-sm" value="<?php echo $esc((string) ($branchListOpts['q'] ?? '')); ?>"></div>
    <div class="col-md-2"><label class="form-label small mb-0">الحالة</label><select name="status" class="form-select form-select-sm"><option value="">الكل</option><option value="active"<?php echo ($branchListOpts['status'] ?? '') === 'active' ? ' selected' : ''; ?>>نشط</option><option value="inactive"<?php echo ($branchListOpts['status'] ?? '') === 'inactive' ? ' selected' : ''; ?>>موقوف</option></select></div>
    <div class="col-md-2"><label class="form-label small mb-0">النوع</label><select name="branch_type" class="form-select form-select-sm"><option value="">الكل</option><option value="main"<?php echo ($branchListOpts['branch_type'] ?? '') === 'main' ? ' selected' : ''; ?>>رئيسي</option><option value="child"<?php echo ($branchListOpts['branch_type'] ?? '') === 'child' ? ' selected' : ''; ?>>فرعي</option></select></div>
    <div class="col-md-2"><label class="form-label small mb-0">الأرشيف</label><select name="archive" class="form-select form-select-sm"><option value="">نشط فقط</option><option value="archived"<?php echo ($branchListOpts['archive'] ?? '') === 'archived' ? ' selected' : ''; ?>>مؤرشف</option><option value="all"<?php echo ($branchListOpts['archive'] ?? '') === 'all' ? ' selected' : ''; ?>>الكل</option></select></div>
    <div class="col-md-2"><label class="form-label small mb-0">ترتيب</label><select name="sort" class="form-select form-select-sm"><option value="name">الاسم</option><option value="code"<?php echo ($branchListOpts['sort'] ?? '') === 'code' ? ' selected' : ''; ?>>الكود</option><option value="status"<?php echo ($branchListOpts['sort'] ?? '') === 'status' ? ' selected' : ''; ?>>الحالة</option><option value="created_at"<?php echo ($branchListOpts['sort'] ?? '') === 'created_at' ? ' selected' : ''; ?>>تاريخ الإنشاء</option></select></div>
    <div class="col-md-1"><button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="fas fa-search"></i></button></div>
</form>
<form method="post" id="branch-bulk-form-<?php echo (int) $cid; ?>" class="mb-2 d-flex flex-wrap gap-2">
    <input type="hidden" name="_csrf" value="<?php echo $esc($csrf); ?>">
    <?php if ($agencyId > 0) { ?><input type="hidden" name="agency_id" value="<?php echo $agencyId; ?>"><?php } else { ?><input type="hidden" name="platform" value="1"><?php } ?>
    <input type="hidden" name="action" value="bulk_branch">
    <input type="hidden" name="company_id" value="<?php echo (int) $cid; ?>">
    <select name="bulk_action" class="form-select form-select-sm" style="width:auto"><option value="enable">تفعيل</option><option value="disable">إيقاف</option><option value="archive">أرشفة</option><option value="restore">استعادة</option></select>
    <button type="submit" class="btn btn-sm btn-outline-secondary">تطبيق على المحدد</button>
</form>
<?php if ($branches !== []) { ?>
<div class="table-responsive mb-2"><table class="table table-sm align-middle mb-0"><thead><tr><th></th><th>الفرع</th><th>الكود</th><th>النوع</th><th>الحالة</th><th>رابط</th><th></th></tr></thead><tbody>
<?php foreach ($branches as $branch) {
    $bid = (int) ($branch['id'] ?? 0);
    $portalUrl = control_rateb_erp_branch_portal_url($bid, $branch);
    $isMain = !empty($branch['is_main']);
    $isActive = (string) ($branch['status'] ?? '') === 'active';
    $isArchived = (int) ($branch['is_archived'] ?? 0) === 1;
    ?>
<tr<?php echo $isArchived ? ' class="opacity-75"' : ''; ?>>
<td><input type="checkbox" form="branch-bulk-form-<?php echo (int) $cid; ?>" name="branch_ids[]" value="<?php echo $bid; ?>"></td>
<td><?php echo $esc((string) ($branch['name'] ?? '')); ?><?php echo $isArchived ? ' <span class="badge bg-secondary">مؤرشف</span>' : ''; ?></td>
<td><code><?php echo $esc((string) ($branch['code'] ?? '')); ?></code></td>
<td><?php echo $isMain ? '<span class="badge bg-info">رئيسي</span>' : '<span class="badge bg-secondary">فرع</span>'; ?></td>
<td><?php echo $isActive ? '<span class="badge bg-success">نشط</span>' : '<span class="badge bg-warning text-dark">موقوف</span>'; ?></td>
<td><a href="<?php echo $esc($portalUrl); ?>" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i></a></td>
<td class="text-nowrap">
<?php if (!$isArchived) { ?>
<button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#edit-branch-<?php echo $bid; ?>"><i class="fas fa-edit"></i></button>
<?php if (!$isMain) { ?>
<form method="post" class="d-inline"><input type="hidden" name="_csrf" value="<?php echo $esc($csrf); ?>"><?php if ($agencyId > 0) { ?><input type="hidden" name="agency_id" value="<?php echo $agencyId; ?>"><?php } else { ?><input type="hidden" name="platform" value="1"><?php } ?><input type="hidden" name="action" value="toggle_branch"><input type="hidden" name="company_id" value="<?php echo (int) $cid; ?>"><input type="hidden" name="branch_id" value="<?php echo $bid; ?>"><input type="hidden" name="status" value="<?php echo $isActive ? 'inactive' : 'active'; ?>"><button type="submit" class="btn btn-sm btn-outline-warning"><i class="fas fa-power-off"></i></button></form>
<form method="post" class="d-inline"><input type="hidden" name="_csrf" value="<?php echo $esc($csrf); ?>"><?php if ($agencyId > 0) { ?><input type="hidden" name="agency_id" value="<?php echo $agencyId; ?>"><?php } else { ?><input type="hidden" name="platform" value="1"><?php } ?><input type="hidden" name="action" value="archive_branch"><input type="hidden" name="company_id" value="<?php echo (int) $cid; ?>"><input type="hidden" name="branch_id" value="<?php echo $bid; ?>"><button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-archive"></i></button></form>
<?php } } else { ?>
<form method="post" class="d-inline"><input type="hidden" name="_csrf" value="<?php echo $esc($csrf); ?>"><?php if ($agencyId > 0) { ?><input type="hidden" name="agency_id" value="<?php echo $agencyId; ?>"><?php } else { ?><input type="hidden" name="platform" value="1"><?php } ?><input type="hidden" name="action" value="restore_branch"><input type="hidden" name="company_id" value="<?php echo (int) $cid; ?>"><input type="hidden" name="branch_id" value="<?php echo $bid; ?>"><button type="submit" class="btn btn-sm btn-outline-success"><i class="fas fa-undo"></i></button></form>
<?php } ?>
</td></tr>
<?php } ?>
</tbody></table></div>
<?php if ($branchListPages > 1) { ?>
<nav class="mb-3"><ul class="pagination pagination-sm">
<?php for ($pi = 1; $pi <= $branchListPages; $pi++) {
    $pq = array_merge($listQueryBase, ['page' => $pi, 'per_page' => $branchListPerPage]);
    $phref = '?' . http_build_query(array_filter($pq, static fn ($v): bool => $v !== null && $v !== ''));
    ?><li class="page-item<?php echo $pi === $branchListPage ? ' active' : ''; ?>"><a class="page-link" href="<?php echo $esc($phref); ?>"><?php echo $pi; ?></a></li><?php } ?>
</ul><p class="small text-muted mb-0"><?php echo $branchListTotal; ?> فرع — صفحة <?php echo $branchListPage; ?> / <?php echo $branchListPages; ?></p></nav>
<?php } ?>
<?php foreach ($branches as $branch) {
    if ((int) ($branch['is_archived'] ?? 0) === 1) { continue; }
    $bid = (int) ($branch['id'] ?? 0);
    $isActive = (string) ($branch['status'] ?? '') === 'active';
    ?>
<div class="modal fade" id="edit-branch-<?php echo $bid; ?>" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="post"><div class="modal-header"><h5 class="modal-title">تعديل الفرع</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body row g-2">
<input type="hidden" name="_csrf" value="<?php echo $esc($csrf); ?>"><?php if ($agencyId > 0) { ?><input type="hidden" name="agency_id" value="<?php echo $agencyId; ?>"><?php } else { ?><input type="hidden" name="platform" value="1"><?php } ?><input type="hidden" name="action" value="update_branch"><input type="hidden" name="company_id" value="<?php echo (int) $cid; ?>"><input type="hidden" name="branch_id" value="<?php echo $bid; ?>">
<div class="col-md-6"><label class="form-label small">اسم الفرع *</label><input type="text" name="branch_name" class="form-control form-control-sm" required value="<?php echo $esc((string) ($branch['name'] ?? '')); ?>"></div>
<div class="col-md-3"><label class="form-label small">الكود</label><input type="text" name="branch_code" class="form-control form-control-sm" value="<?php echo $esc((string) ($branch['code'] ?? '')); ?>"></div>
<div class="col-md-3"><label class="form-label small">الحالة</label><select name="branch_status" class="form-select form-select-sm"><option value="active"<?php echo $isActive ? ' selected' : ''; ?>>نشط</option><option value="inactive"<?php echo !$isActive ? ' selected' : ''; ?>>موقوف</option></select></div>
<div class="col-md-4"><label class="form-label small">الهاتف</label><input type="text" name="branch_phone" class="form-control form-control-sm" value="<?php echo $esc((string) ($branch['phone'] ?? '')); ?>"></div>
<div class="col-md-4"><label class="form-label small">البريد</label><input type="email" name="branch_email" class="form-control form-control-sm" value="<?php echo $esc((string) ($branch['email'] ?? '')); ?>"></div>
<div class="col-md-4"><label class="form-label small">رابط الخريطة</label><input type="text" name="branch_map_url" class="form-control form-control-sm" value="<?php echo $esc((string) ($branch['map_url'] ?? '')); ?>"></div>
<div class="col-12"><label class="form-label small">العنوان</label><input type="text" name="branch_address" class="form-control form-control-sm" value="<?php echo $esc((string) ($branch['address'] ?? '')); ?>"></div>
</div><div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">إلغاء</button><button type="submit" class="btn btn-primary btn-sm">حفظ</button></div></form></div></div></div>
<?php } ?>
<?php } else { ?>
<p class="small text-muted mb-3">لا توجد فروع مطابقة.</p>
<?php } ?>

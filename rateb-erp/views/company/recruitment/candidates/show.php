<?php
declare(strict_types=1);
/** @var array<string,mixed> $item */
/** @var list<array<string,mixed>> $timeline */
/** @var list<array<string,mixed>> $documents */
/** @var list<string> $transitions */
$id = (int) ($item['id'] ?? 0);
$csrf = \Rateb\App\Core\Csrf::field();
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><?php echo htmlspecialchars((string) ($item['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="text-muted"><?php echo htmlspecialchars((string) ($item['candidate_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                · <span class="badge text-bg-primary"><?php echo htmlspecialchars((string) ($item['workflow_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('recruitment/candidates') . '/' . $id . '/edit'), ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars(__('edit'), ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('recruitment/candidates')), ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars(__('back'), ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="border rounded p-3 mb-3">
                <h2 class="h5"><?php echo htmlspecialchars(__('profile'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <dl class="row mb-0">
                    <dt class="col-sm-4"><?php echo htmlspecialchars(__('email'), ENT_QUOTES, 'UTF-8'); ?></dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars((string) ($item['email'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-4"><?php echo htmlspecialchars(__('phone'), ENT_QUOTES, 'UTF-8'); ?></dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars((string) ($item['phone'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-4"><?php echo htmlspecialchars(__('nationality'), ENT_QUOTES, 'UTF-8'); ?></dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars((string) ($item['nationality'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-4"><?php echo htmlspecialchars(__('job_title'), ENT_QUOTES, 'UTF-8'); ?></dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars((string) ($item['job_title_target'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></dd>
                </dl>
            </div>

            <?php if (!empty($canWorkflow) && $transitions !== []): ?>
            <div class="border rounded p-3 mb-3">
                <h2 class="h5"><?php echo htmlspecialchars(__('recruitment_workflow'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('recruitment/candidates') . '/' . $id . '/transition'), ENT_QUOTES, 'UTF-8'); ?>" class="row g-2">
                    <?php echo $csrf; ?>
                    <div class="col-md-5">
                        <select name="to_status" class="form-select" required>
                            <?php foreach ($transitions as $st): ?>
                                <option value="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="reason" class="form-control" placeholder="<?php echo htmlspecialchars(__('notes'), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><?php echo htmlspecialchars(__('apply'), ENT_QUOTES, 'UTF-8'); ?></button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <div class="border rounded p-3 mb-3">
                <h2 class="h5"><?php echo htmlspecialchars(__('recruitment_quick_add'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('recruitment/candidates') . '/' . $id . '/visa'), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo $csrf; ?>
                            <label class="form-label"><?php echo htmlspecialchars(__('recruitment_visa'), ENT_QUOTES, 'UTF-8'); ?></label>
                            <input class="form-control mb-2" name="visa_type" placeholder="type">
                            <button class="btn btn-sm btn-outline-primary" type="submit"><?php echo htmlspecialchars(__('add'), ENT_QUOTES, 'UTF-8'); ?></button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('recruitment/candidates') . '/' . $id . '/medical'), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo $csrf; ?>
                            <label class="form-label"><?php echo htmlspecialchars(__('recruitment_medical'), ENT_QUOTES, 'UTF-8'); ?></label>
                            <input class="form-control mb-2" name="clinic_name" placeholder="clinic">
                            <button class="btn btn-sm btn-outline-primary" type="submit"><?php echo htmlspecialchars(__('add'), ENT_QUOTES, 'UTF-8'); ?></button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('recruitment/candidates') . '/' . $id . '/contract'), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo $csrf; ?>
                            <label class="form-label"><?php echo htmlspecialchars(__('recruitment_contract'), ENT_QUOTES, 'UTF-8'); ?></label>
                            <input class="form-control mb-2" name="title" required placeholder="title">
                            <button class="btn btn-sm btn-outline-primary" type="submit"><?php echo htmlspecialchars(__('add'), ENT_QUOTES, 'UTF-8'); ?></button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('recruitment/candidates') . '/' . $id . '/passport'), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo $csrf; ?>
                            <label class="form-label"><?php echo htmlspecialchars(__('recruitment_passport'), ENT_QUOTES, 'UTF-8'); ?></label>
                            <input class="form-control mb-2" name="passport_no" required placeholder="passport no">
                            <button class="btn btn-sm btn-outline-primary" type="submit"><?php echo htmlspecialchars(__('add'), ENT_QUOTES, 'UTF-8'); ?></button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('recruitment/candidates') . '/' . $id . '/interview'), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo $csrf; ?>
                            <label class="form-label"><?php echo htmlspecialchars(__('recruitment_interview'), ENT_QUOTES, 'UTF-8'); ?></label>
                            <input class="form-control mb-2" type="datetime-local" name="scheduled_at">
                            <button class="btn btn-sm btn-outline-primary" type="submit"><?php echo htmlspecialchars(__('add'), ENT_QUOTES, 'UTF-8'); ?></button>
                        </form>
                    </div>
                </div>
            </div>

            <?php if (!empty($canUpload)): ?>
            <div class="border rounded p-3 mb-3">
                <h2 class="h5"><?php echo htmlspecialchars(__('documents'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <form method="post" enctype="multipart/form-data" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('recruitment/candidates') . '/' . $id . '/documents'), ENT_QUOTES, 'UTF-8'); ?>" class="row g-2">
                    <?php echo $csrf; ?>
                    <div class="col-md-5"><input type="text" name="title" class="form-control" placeholder="<?php echo htmlspecialchars(__('title'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="col-md-5"><input type="file" name="document" class="form-control" required></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><?php echo htmlspecialchars(__('upload'), ENT_QUOTES, 'UTF-8'); ?></button></div>
                </form>
                <ul class="list-unstyled mt-3 mb-0">
                    <?php foreach ($documents as $doc): ?>
                        <li class="small"><?php echo htmlspecialchars((string) ($doc['title'] ?? $doc['original_name'] ?? '#' . ($doc['id'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                    <?php if ($documents === []): ?>
                        <li class="text-muted small"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        <div class="col-lg-5">
            <div class="border rounded p-3">
                <h2 class="h5"><?php echo htmlspecialchars(__('timeline'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($timeline as $ev): ?>
                        <li class="mb-3 border-bottom pb-2">
                            <div class="fw-semibold"><?php echo htmlspecialchars((string) ($ev['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="small text-muted"><?php echo htmlspecialchars((string) ($ev['event_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                · <?php echo htmlspecialchars((string) ($ev['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php if (!empty($ev['body'])): ?>
                                <div class="small"><?php echo htmlspecialchars((string) $ev['body'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    <?php if ($timeline === []): ?>
                        <li class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

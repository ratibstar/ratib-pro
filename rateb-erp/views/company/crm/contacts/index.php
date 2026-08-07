<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
    <?php if (!empty($canCreate)): ?>
    <form method="post" class="border rounded p-3 mb-3 col-lg-9">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="row g-2">
            <div class="col-md-3"><input class="form-control" name="full_name" required placeholder="<?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div class="col-md-3"><input class="form-control" name="email" type="email" placeholder="<?php echo htmlspecialchars(__('email'), ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div class="col-md-3">
                <select class="form-select" name="crm_company_id">
                    <option value=""><?php echo htmlspecialchars(__('crm_companies'), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php foreach (($companies ?? []) as $co): ?>
                    <option value="<?php echo (int) ($co['id'] ?? 0); ?>" <?php echo ((int) ($crm_company_id ?? 0) === (int) ($co['id'] ?? 0)) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($co['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><button class="btn btn-primary w-100" type="submit"><?php echo htmlspecialchars(__('create'), ENT_QUOTES, 'UTF-8'); ?></button></div>
        </div>
    </form>
    <?php endif; ?>
    <div class="table-responsive"><table class="table table-striped"><thead><tr><th><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('crm_companies'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('email'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('phone'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead>
    <tbody>
    <?php foreach (($items ?? []) as $row): ?>
        <tr>
            <td><a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/contacts') . '/' . (int) $row['id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($row['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></td>
            <td><?php echo htmlspecialchars((string) ($row['crm_company_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string) ($row['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (($items ?? []) === []): ?><tr><td colspan="4" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
    </tbody></table></div>
</div>

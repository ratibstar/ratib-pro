<?php
declare(strict_types=1);

/**
 * @return array{code:string,count:int}
 */
function admin_bulk_apply(object $pdo, array $adminActor, string $screen, string $action, array $ids, string $reason): array
{
    $ids = array_values(array_unique(array_filter(array_map(static fn ($v) => (int) $v, $ids), static fn ($v) => $v > 0)));
    if ($ids === []) {
        return ['code' => 'missing', 'count' => 0];
    }
    if (in_array($action, ['disable', 'enable', 'delete', 'activate', 'deactivate'], true) && mb_strlen(trim($reason)) < 3) {
        return ['code' => 'reason', 'count' => 0];
    }

    $applied = 0;
    switch ($screen) {
        case 'users':
            if (!admin_actor_has($adminActor, 'customers.manage')) {
                return ['code' => 'invalid', 'count' => 0];
            }
            if (!in_array($action, ['disable', 'enable'], true)) {
                return ['code' => 'invalid', 'count' => 0];
            }
            foreach ($ids as $userId) {
                $result = admin_set_account_state($pdo, $adminActor, $userId, $action === 'disable', $reason);
                if (($result['ok'] ?? false) === true) {
                    $applied++;
                }
            }
            break;

        case 'staff':
            if (!admin_actor_is_owner($adminActor) || !admin_actor_has($adminActor, 'staff.manage')) {
                return ['code' => 'invalid', 'count' => 0];
            }
            if (!in_array($action, ['activate', 'deactivate'], true)) {
                return ['code' => 'invalid', 'count' => 0];
            }
            $status = $action === 'deactivate' ? 'inactive' : 'active';
            foreach ($ids as $staffId) {
                if ((int) ($adminActor['staff_id'] ?? 0) === $staffId) {
                    continue;
                }
                $pdo->prepare("UPDATE admin_staff SET status = ? WHERE id = ? AND role_code <> 'owner'")->execute([$status, $staffId]);
                admin_audit($pdo, $adminActor, $action === 'deactivate' ? 'staff_disable' : 'staff_enable', 'staff', $staffId, $reason, 'bulk');
                $applied++;
            }
            break;

        case 'campaigns':
            if (!admin_actor_has($adminActor, 'campaigns.manage')) {
                return ['code' => 'invalid', 'count' => 0];
            }
            if ($action !== 'delete') {
                return ['code' => 'invalid', 'count' => 0];
            }
            if (!admin_actor_is_owner($adminActor)) {
                return ['code' => 'owner_only', 'count' => 0];
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT id FROM campaigns WHERE id IN ($placeholders) AND status IN ('draft','failed')");
            $stmt->execute($ids);
            $allowed = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($allowed as $campaignId) {
                $campaignId = (int) $campaignId;
                $pdo->prepare('DELETE FROM campaigns WHERE id = ?')->execute([$campaignId]);
                if (admin_table_has($pdo, 'admin_audit_log', 'id')) {
                    admin_audit($pdo, $adminActor, 'campaign_delete', 'campaign', $campaignId, $reason, 'bulk');
                }
                $applied++;
            }
            break;

        default:
            return ['code' => 'invalid', 'count' => 0];
    }

    return ['code' => $applied > 0 ? 'applied' : 'missing', 'count' => $applied];
}

/** @return list<array{value:string,label:string}> */
function admin_bulk_actions_for_screen(string $screen): array
{
    return match ($screen) {
        'users' => [
            ['value' => 'disable', 'label' => admin_t('Disable selected', 'تعطيل المحدد')],
            ['value' => 'enable', 'label' => admin_t('Enable selected', 'تفعيل المحدد')],
        ],
        'staff' => [
            ['value' => 'deactivate', 'label' => admin_t('Disable selected', 'تعطيل المحدد')],
            ['value' => 'activate', 'label' => admin_t('Activate selected', 'تفعيل المحدد')],
        ],
        'campaigns' => [
            ['value' => 'delete', 'label' => admin_t('Delete draft/failed', 'حذف مسودة/فاشلة')],
        ],
        default => [],
    };
}
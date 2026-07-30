<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;

/**
 * Platform Agent Apps console — read/write over ESS / HR / notification / mobile config data.
 */
final class AgentAppsOpsService
{
    private function companyScopeSql(string $alias = ''): array
    {
        $col = $alias !== '' ? "{$alias}.company_id" : 'company_id';
        if (TenantContext::isSuperAdmin()
            || (function_exists('rateb_is_super_admin') && rateb_is_super_admin())) {
            return ['', []];
        }
        $cid = (int) (TenantContext::companyId() ?? 0);
        if ($cid < 1 && function_exists('rateb_resolve_ops_company_id')) {
            $cid = (int) rateb_resolve_ops_company_id();
        }
        if ($cid < 1) {
            $cid = (int) SessionManager::get('rateb_company_id', 0);
        }
        if ($cid < 1) {
            return ['', []];
        }

        return [" AND {$col} = :ops_cid", ['ops_cid' => $cid]];
    }

    /**
     * @return array{items:list<array<string,mixed>>,total:int,pending:int}
     */
    public function listComplaints(int $limit = 50, int $offset = 0, string $status = '', string $type = ''): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        [$scopeSql, $scopeParams] = $this->companyScopeSql('r');
        $params = $scopeParams;
        $where = "r.request_type IN ('inquiry','complaint')" . $scopeSql;
        if ($status !== '' && in_array($status, ['pending', 'approved', 'rejected', 'cancelled'], true)) {
            $where .= ' AND r.status = :st';
            $params['st'] = $status;
        }
        if ($type !== '' && in_array($type, ['inquiry', 'complaint'], true)) {
            $where .= ' AND r.request_type = :rtype';
            $params['rtype'] = $type;
        }

        try {
            $pdo = Database::connection();
            $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM rateb_hr_employee_requests r WHERE {$where}");
            $totalStmt->execute($params);
            $total = (int) $totalStmt->fetchColumn();

            $pendingParams = $scopeParams;
            $pendingWhere = "r.request_type IN ('inquiry','complaint') AND r.status = 'pending'" . $scopeSql;
            $pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM rateb_hr_employee_requests r WHERE {$pendingWhere}");
            $pendingStmt->execute($pendingParams);
            $pending = (int) $pendingStmt->fetchColumn();

            $sql = "SELECT r.id, r.company_id, r.request_no, r.employee_id, r.request_type, r.request_date,
                           r.status, r.notes, r.created_at, r.processed_at,
                           c.name AS company_name,
                           e.name AS employee_name
                    FROM rateb_hr_employee_requests r
                    LEFT JOIN rateb_companies c ON c.id = r.company_id
                    LEFT JOIN rateb_employees e ON e.id = r.employee_id
                    WHERE {$where}
                    ORDER BY r.id DESC
                    LIMIT {$limit} OFFSET {$offset}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return ['items' => $items, 'total' => $total, 'pending' => $pending];
        } catch (\Throwable $e) {
            error_log('AgentAppsOpsService::listComplaints: ' . $e->getMessage());

            return ['items' => [], 'total' => 0, 'pending' => 0];
        }
    }

    public function setComplaintStatus(int $id, string $action, int $userId): bool
    {
        if ($id < 1 || !in_array($action, ['approve', 'reject'], true)) {
            return false;
        }
        $state = $action === 'approve' ? 'approved' : 'rejected';
        [$scopeSql, $scopeParams] = $this->companyScopeSql('');
        $params = array_merge($scopeParams, [
            'st' => $state,
            'uid' => $userId > 0 ? $userId : null,
            'id' => $id,
            'pending' => 'pending',
        ]);
        $sql = 'UPDATE rateb_hr_employee_requests
                SET status = :st, processed_by = :uid, processed_at = NOW()
                WHERE id = :id AND status = :pending
                  AND request_type IN (\'inquiry\',\'complaint\')' . $scopeSql;
        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute($params);

            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            error_log('AgentAppsOpsService::setComplaintStatus: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * @return array{items:list<array<string,mixed>>,total:int,avg:string}
     */
    public function listRatings(int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        [$scopeSql, $scopeParams] = $this->companyScopeSql('r');
        $params = $scopeParams;
        $where = 'r.deleted_at IS NULL' . $scopeSql;

        try {
            $pdo = Database::connection();
            $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM rateb_hrm_performance_reviews r WHERE {$where}");
            $totalStmt->execute($params);
            $total = (int) $totalStmt->fetchColumn();

            $avgStmt = $pdo->prepare(
                "SELECT AVG(r.overall_score) FROM rateb_hrm_performance_reviews r
                 WHERE {$where} AND r.overall_score IS NOT NULL"
            );
            $avgStmt->execute($params);
            $avg = (float) $avgStmt->fetchColumn();

            $sql = "SELECT r.id, r.company_id, r.code, r.overall_score, r.workflow_status, r.summary,
                           r.updated_at, r.created_at, c.name AS company_name
                    FROM rateb_hrm_performance_reviews r
                    LEFT JOIN rateb_companies c ON c.id = r.company_id
                    WHERE {$where}
                    ORDER BY r.updated_at DESC, r.id DESC
                    LIMIT {$limit} OFFSET {$offset}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return [
                'items' => $items,
                'total' => $total,
                'avg' => number_format($avg, 1) . '/5',
            ];
        } catch (\Throwable $e) {
            error_log('AgentAppsOpsService::listRatings: ' . $e->getMessage());

            return ['items' => [], 'total' => 0, 'avg' => '0/5'];
        }
    }

    /**
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public function listNotifications(int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        [$scopeSql, $scopeParams] = $this->companyScopeSql('n');
        $params = $scopeParams;
        $where = '1=1' . $scopeSql;

        try {
            $pdo = Database::connection();
            $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM rateb_notifications n WHERE {$where}");
            $totalStmt->execute($params);
            $total = (int) $totalStmt->fetchColumn();

            $sql = "SELECT n.id, n.company_id, n.user_id, n.title, n.message, n.type, n.is_read, n.created_at,
                           c.name AS company_name
                    FROM rateb_notifications n
                    LEFT JOIN rateb_companies c ON c.id = n.company_id
                    WHERE {$where}
                    ORDER BY n.id DESC
                    LIMIT {$limit} OFFSET {$offset}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return ['items' => $items, 'total' => $total];
        } catch (\Throwable $e) {
            error_log('AgentAppsOpsService::listNotifications: ' . $e->getMessage());

            return ['items' => [], 'total' => 0];
        }
    }

    /**
     * Mobile salary / payment feature matrix per company.
     *
     * @return list<array<string,mixed>>
     */
    public function listPaymentFeatureMatrix(): array
    {
        $svc = new MobileAppConfigService();
        $rows = $svc->listCompaniesWithConfig();
        $out = [];
        foreach ($rows as $row) {
            $features = $svc->decodeFeatures($row['enabled_features'] ?? null);
            $out[] = [
                'company_id' => (int) ($row['company_id'] ?? 0),
                'company_name' => (string) ($row['company_name'] ?? ''),
                'app_name' => (string) ($row['app_name'] ?? ''),
                'mobile_active' => (string) ($row['mobile_status'] ?? '') === MobileAppConfigService::STATUS_ACTIVE,
                'payroll' => !empty($features['payroll']),
                'payslips' => !empty($features['payslips']),
                'payments' => !empty($features['payments']),
            ];
        }

        return $out;
    }

    public function countPendingComplaints(): int
    {
        return $this->listComplaints(1, 0, 'pending')['pending'];
    }

    public function notificationCount(): int
    {
        return $this->listNotifications(1, 0)['total'];
    }

    public function ratingsAvgLabel(): string
    {
        return $this->listRatings(1, 0)['avg'];
    }

    /** @return list<string> */
    public static function contentSlugs(): array
    {
        return ['about', 'privacy', 'terms', 'faq', 'help', 'home'];
    }

    public function resolveWriteCompanyId(int $requested = 0): int
    {
        $isSuper = TenantContext::isSuperAdmin()
            || (function_exists('rateb_is_super_admin') && rateb_is_super_admin());
        if ($isSuper && $requested > 0) {
            return $requested;
        }
        $cid = (int) (TenantContext::companyId() ?? 0);
        if ($cid < 1 && function_exists('rateb_resolve_ops_company_id')) {
            $cid = (int) rateb_resolve_ops_company_id();
        }
        if ($cid < 1) {
            $cid = (int) SessionManager::get('rateb_company_id', 0);
        }
        if ($cid < 1 && class_exists(DedicatedTenantPolicy::class)) {
            $cid = (int) DedicatedTenantPolicy::primaryCompanyId();
        }
        if ($cid < 1 && $requested > 0 && $isSuper) {
            return $requested;
        }

        return $cid > 0 ? $cid : ($isSuper ? $requested : 0);
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    public function listCompanyOptions(): array
    {
        $rows = (new MobileAppConfigService())->listCompaniesWithConfig();
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) ($row['company_id'] ?? 0),
                'name' => (string) ($row['company_name'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public function listContents(int $companyFilter = 0, int $limit = 100): array
    {
        MobileAppContentSchemaBootstrap::ensure();
        $limit = max(1, min(200, $limit));
        [$scopeSql, $scopeParams] = $this->companyScopeSql('t');
        $params = $scopeParams;
        $where = '1=1' . $scopeSql;
        if ($companyFilter > 0) {
            $where .= ' AND t.company_id = :cf';
            $params['cf'] = $companyFilter;
        }
        try {
            $pdo = Database::connection();
            $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM rateb_mobile_app_contents t WHERE {$where}");
            $totalStmt->execute($params);
            $total = (int) $totalStmt->fetchColumn();
            $sql = "SELECT t.*, c.name AS company_name
                    FROM rateb_mobile_app_contents t
                    LEFT JOIN rateb_companies c ON c.id = t.company_id
                    WHERE {$where}
                    ORDER BY t.company_id ASC, t.sort_order ASC, t.id ASC
                    LIMIT {$limit}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return ['items' => $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [], 'total' => $total];
        } catch (\Throwable $e) {
            error_log('AgentAppsOpsService::listContents: ' . $e->getMessage());

            return ['items' => [], 'total' => 0];
        }
    }

    /**
     * @param array<string,mixed> $input
     * @return array{ok:bool,message:string,id?:int}
     */
    public function saveContent(array $input, int $userCompanyHint = 0): array
    {
        MobileAppContentSchemaBootstrap::ensure();
        $id = (int) ($input['id'] ?? 0);
        $companyId = $this->resolveWriteCompanyId((int) ($input['company_id'] ?? $userCompanyHint));
        $slug = strtolower(trim((string) ($input['slug'] ?? '')));
        if ($companyId < 1) {
            return ['ok' => false, 'message' => 'company_required'];
        }
        if ($slug === '' || !preg_match('/^[a-z0-9_\-]{2,64}$/', $slug)) {
            return ['ok' => false, 'message' => 'slug_invalid'];
        }
        $payload = [
            'company_id' => $companyId,
            'slug' => $slug,
            'title_ar' => mb_substr(trim((string) ($input['title_ar'] ?? '')), 0, 255),
            'title_en' => mb_substr(trim((string) ($input['title_en'] ?? '')), 0, 255),
            'body_ar' => (string) ($input['body_ar'] ?? ''),
            'body_en' => (string) ($input['body_en'] ?? ''),
            'sort_order' => (int) ($input['sort_order'] ?? 0),
            'is_active' => !empty($input['is_active']) ? 1 : 0,
        ];
        try {
            $model = new \Rateb\App\Models\MobileAppContent();
            if ($id > 0) {
                $existing = $model->find($id);
                if (!$existing || !$this->canAccessCompanyRow((int) ($existing['company_id'] ?? 0))) {
                    return ['ok' => false, 'message' => 'not_found'];
                }
                $model->update($id, $payload);

                return ['ok' => true, 'message' => 'saved', 'id' => $id];
            }
            $newId = $model->create($payload);

            return ['ok' => true, 'message' => 'saved', 'id' => $newId];
        } catch (\Throwable $e) {
            error_log('AgentAppsOpsService::saveContent: ' . $e->getMessage());

            return ['ok' => false, 'message' => 'save_failed'];
        }
    }

    public function deleteContent(int $id): bool
    {
        MobileAppContentSchemaBootstrap::ensure();
        if ($id < 1) {
            return false;
        }
        try {
            $model = new \Rateb\App\Models\MobileAppContent();
            $existing = $model->find($id);
            if (!$existing || !$this->canAccessCompanyRow((int) ($existing['company_id'] ?? 0))) {
                return false;
            }

            return $model->delete($id);
        } catch (\Throwable $e) {
            error_log('AgentAppsOpsService::deleteContent: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public function listOffers(int $companyFilter = 0, int $limit = 100, bool $activeOnly = false): array
    {
        MobileAppContentSchemaBootstrap::ensure();
        $limit = max(1, min(200, $limit));
        [$scopeSql, $scopeParams] = $this->companyScopeSql('t');
        $params = $scopeParams;
        $where = '1=1' . $scopeSql;
        if ($companyFilter > 0) {
            $where .= ' AND t.company_id = :cf';
            $params['cf'] = $companyFilter;
        }
        if ($activeOnly) {
            $where .= ' AND t.is_active = 1'
                . ' AND (t.starts_at IS NULL OR t.starts_at <= CURDATE())'
                . ' AND (t.ends_at IS NULL OR t.ends_at >= CURDATE())';
        }
        try {
            $pdo = Database::connection();
            $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM rateb_mobile_app_offers t WHERE {$where}");
            $totalStmt->execute($params);
            $total = (int) $totalStmt->fetchColumn();
            $sql = "SELECT t.*, c.name AS company_name
                    FROM rateb_mobile_app_offers t
                    LEFT JOIN rateb_companies c ON c.id = t.company_id
                    WHERE {$where}
                    ORDER BY t.sort_order ASC, t.id DESC
                    LIMIT {$limit}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return ['items' => $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [], 'total' => $total];
        } catch (\Throwable $e) {
            error_log('AgentAppsOpsService::listOffers: ' . $e->getMessage());

            return ['items' => [], 'total' => 0];
        }
    }

    /**
     * @param array<string,mixed> $input
     * @return array{ok:bool,message:string,id?:int}
     */
    public function saveOffer(array $input, int $userCompanyHint = 0): array
    {
        MobileAppContentSchemaBootstrap::ensure();
        $id = (int) ($input['id'] ?? 0);
        $companyId = $this->resolveWriteCompanyId((int) ($input['company_id'] ?? $userCompanyHint));
        if ($companyId < 1) {
            return ['ok' => false, 'message' => 'company_required'];
        }
        $titleAr = mb_substr(trim((string) ($input['title_ar'] ?? '')), 0, 255);
        $titleEn = mb_substr(trim((string) ($input['title_en'] ?? '')), 0, 255);
        if ($titleAr === '' && $titleEn === '') {
            return ['ok' => false, 'message' => 'title_required'];
        }
        $starts = trim((string) ($input['starts_at'] ?? ''));
        $ends = trim((string) ($input['ends_at'] ?? ''));
        $payload = [
            'company_id' => $companyId,
            'title_ar' => $titleAr,
            'title_en' => $titleEn,
            'body_ar' => (string) ($input['body_ar'] ?? ''),
            'body_en' => (string) ($input['body_en'] ?? ''),
            'image_path' => mb_substr(trim((string) ($input['image_path'] ?? '')), 0, 500) ?: null,
            'discount_label' => mb_substr(trim((string) ($input['discount_label'] ?? '')), 0, 80),
            'starts_at' => $starts !== '' ? $starts : null,
            'ends_at' => $ends !== '' ? $ends : null,
            'sort_order' => (int) ($input['sort_order'] ?? 0),
            'is_active' => !empty($input['is_active']) ? 1 : 0,
        ];
        try {
            $model = new \Rateb\App\Models\MobileAppOffer();
            if ($id > 0) {
                $existing = $model->find($id);
                if (!$existing || !$this->canAccessCompanyRow((int) ($existing['company_id'] ?? 0))) {
                    return ['ok' => false, 'message' => 'not_found'];
                }
                $model->update($id, $payload);

                return ['ok' => true, 'message' => 'saved', 'id' => $id];
            }
            $newId = $model->create($payload);

            return ['ok' => true, 'message' => 'saved', 'id' => $newId];
        } catch (\Throwable $e) {
            error_log('AgentAppsOpsService::saveOffer: ' . $e->getMessage());

            return ['ok' => false, 'message' => 'save_failed'];
        }
    }

    public function deleteOffer(int $id): bool
    {
        MobileAppContentSchemaBootstrap::ensure();
        if ($id < 1) {
            return false;
        }
        try {
            $model = new \Rateb\App\Models\MobileAppOffer();
            $existing = $model->find($id);
            if (!$existing || !$this->canAccessCompanyRow((int) ($existing['company_id'] ?? 0))) {
                return false;
            }

            return $model->delete($id);
        } catch (\Throwable $e) {
            error_log('AgentAppsOpsService::deleteOffer: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Legal/content snippets for Flutter settings (extensions map).
     *
     * @return array<string, string>
     */
    public function mobileExtensionsForCompany(int $companyId): array
    {
        if ($companyId < 1) {
            return [];
        }
        MobileAppContentSchemaBootstrap::ensure();
        $ext = [];
        try {
            $stmt = Database::connection()->prepare(
                'SELECT slug, body_ar, body_en, title_ar, title_en
                 FROM rateb_mobile_app_contents
                 WHERE company_id = :cid AND is_active = 1
                   AND slug IN (\'privacy\',\'terms\',\'about\',\'faq\',\'help\',\'home\')'
            );
            $stmt->execute(['cid' => $companyId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $slug = (string) ($row['slug'] ?? '');
                $body = trim((string) ($row['body_ar'] ?? ''));
                if ($body === '') {
                    $body = trim((string) ($row['body_en'] ?? ''));
                }
                if ($body === '') {
                    continue;
                }
                if ($slug === 'privacy') {
                    $ext['privacy_policy'] = $body;
                } elseif ($slug === 'terms') {
                    $ext['terms_of_service'] = $body;
                } else {
                    $ext[$slug] = $body;
                    $title = trim((string) ($row['title_ar'] ?? ''));
                    if ($title === '') {
                        $title = trim((string) ($row['title_en'] ?? ''));
                    }
                    if ($title !== '') {
                        $ext[$slug . '_title'] = $title;
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('AgentAppsOpsService::mobileExtensionsForCompany: ' . $e->getMessage());
        }

        return $ext;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function apiActiveOffers(int $companyId): array
    {
        if ($companyId < 1) {
            return [];
        }
        $list = $this->listOffers($companyId, 50, true);
        $out = [];
        foreach ($list['items'] as $row) {
            if ((int) ($row['company_id'] ?? 0) !== $companyId) {
                continue;
            }
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'title_ar' => (string) ($row['title_ar'] ?? ''),
                'title_en' => (string) ($row['title_en'] ?? ''),
                'body_ar' => (string) ($row['body_ar'] ?? ''),
                'body_en' => (string) ($row['body_en'] ?? ''),
                'image' => (string) ($row['image_path'] ?? ''),
                'discount_label' => (string) ($row['discount_label'] ?? ''),
                'starts_at' => $row['starts_at'] ?? null,
                'ends_at' => $row['ends_at'] ?? null,
            ];
        }

        return $out;
    }

    private function canAccessCompanyRow(int $companyId): bool
    {
        if ($companyId < 1) {
            return false;
        }
        if (TenantContext::isSuperAdmin()
            || (function_exists('rateb_is_super_admin') && rateb_is_super_admin())) {
            return true;
        }
        $cid = $this->resolveWriteCompanyId(0);

        return $cid > 0 && $cid === $companyId;
    }
}

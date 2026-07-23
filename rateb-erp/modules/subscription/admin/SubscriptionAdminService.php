<?php
declare(strict_types=1);

namespace Rateb\App\Subscription\Admin;

use Rateb\App\Subscription\DefaultRenewalAuthorizer;
use Rateb\App\Subscription\NotificationHistoryRepository;
use Rateb\App\Subscription\RenewalAuthorizer;
use Rateb\App\Subscription\RenewalEngine;
use Rateb\App\Subscription\RenewalRepository;
use Rateb\App\Subscription\RenewalRequest;
use Rateb\App\Subscription\RenewalResult;
use Rateb\App\Subscription\SubscriptionBootstrap;
use Rateb\App\Subscription\SubscriptionStatus;

/**
 * Operational service for the subscription administration panel.
 * No payment / auto-billing. Manual lifecycle actions only.
 */
final class SubscriptionAdminService
{
    private SubscriptionAdminRepository $repo;
    private RenewalRepository $renewals;
    private NotificationHistoryRepository $notifications;
    private ?RenewalAuthorizer $authorizer;

    public function __construct(
        ?SubscriptionAdminRepository $repo = null,
        ?RenewalRepository $renewals = null,
        ?NotificationHistoryRepository $notifications = null,
        ?RenewalAuthorizer $authorizer = null
    ) {
        $this->repo = $repo ?? new SubscriptionAdminRepository();
        $this->renewals = $renewals ?? new RenewalRepository();
        $this->notifications = $notifications ?? new NotificationHistoryRepository();
        $this->authorizer = $authorizer;
    }

    public function dashboard(?string $todayYmd = null): SubscriptionAdminDashboard
    {
        return $this->repo->dashboardCounts(
            $todayYmd ?? gmdate('Y-m-d'),
            SubscriptionAdminViewModel::EXPIRING_SOON_DAYS
        );
    }

    /**
     * Ensure every company has an engine row (insert-only bootstrap from companies + billing dates).
     *
     * @return array{inserted:int,examined:int}
     */
    public function syncMissingCompanies(?string $todayYmd = null): array
    {
        return $this->repo->syncMissingCompanies($todayYmd ?? gmdate('Y-m-d'));
    }

    /**
     * @return array{
     *   items: list<array<string, mixed>>,
     *   total: int,
     *   page: int,
     *   limit: int,
     *   status: string,
     *   search: string
     * }
     */
    public function listTenants(
        int $page,
        int $limit,
        string $status = 'all',
        string $search = '',
        ?string $todayYmd = null
    ): array {
        $today = $todayYmd ?? gmdate('Y-m-d');
        $pageInfo = SubscriptionAdminViewModel::pagination($page, $limit);
        $raw = $this->repo->listTenants(
            $pageInfo['offset'],
            $pageInfo['limit'],
            $status,
            $search !== '' ? $search : null,
            $today
        );
        $items = [];
        foreach ($raw['items'] as $row) {
            $items[] = SubscriptionAdminViewModel::mapTenantRow($row, $today);
        }
        return [
            'items' => $items,
            'total' => $raw['total'],
            'page' => $pageInfo['page'],
            'limit' => $pageInfo['limit'],
            'status' => $status,
            'search' => $search,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function tenantDetail(int $companyId, ?string $todayYmd = null): ?array
    {
        $today = $todayYmd ?? gmdate('Y-m-d');
        $row = $this->repo->findTenant($companyId);
        if ($row === null) {
            return null;
        }

        $mapped = SubscriptionAdminViewModel::mapTenantRow($row, $today);
        $lifecycle = $this->repo->listLifecycleAudits($companyId, 50);
        $renewalHistory = $this->renewals->listHistoryByCompanyId($companyId, 50);
        $suspensionAudit = $this->repo->listSuspensionAudits($companyId, 50);
        $notificationHistory = $this->notifications->listByCompanyId($companyId, 50);

        return [
            'tenant' => $mapped,
            'engine' => $row,
            'timeline' => SubscriptionAdminViewModel::buildTimeline(
                $row,
                $lifecycle,
                $renewalHistory,
                $suspensionAudit
            ),
            'notifications' => $notificationHistory,
            'renewals' => $renewalHistory,
            'suspensions' => $suspensionAudit,
            'lifecycle_audits' => $lifecycle,
        ];
    }

    public function renewManual(
        int $companyId,
        string $newExpiryDate,
        string $renewalPeriod,
        int $actorId,
        ?string $reference = null
    ): RenewalResult {
        if (!$this->canManage($actorId)) {
            return RenewalResult::rejected($companyId, 'unauthorized', 'Actor not authorized to renew');
        }

        $engine = new RenewalEngine(null, null, $this->authorizer ?? new DefaultRenewalAuthorizer());
        return $engine->renew(new RenewalRequest(
            $companyId,
            $newExpiryDate,
            $renewalPeriod !== '' ? $renewalPeriod : 'manual',
            $actorId,
            $reference
        ));
    }

    /**
     * Extend expiry date without requiring a period token (ops console).
     *
     * @return array{success:bool,code:string,message:string,new_expiry:?string}
     */
    public function extendExpiry(int $companyId, string $newExpiryDate, int $actorId): array
    {
        if (!$this->canManage($actorId)) {
            return [
                'success' => false,
                'code' => 'unauthorized',
                'message' => 'Actor not authorized to extend',
                'new_expiry' => null,
            ];
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newExpiryDate)) {
            return [
                'success' => false,
                'code' => 'invalid_expiry',
                'message' => 'new_expiry_date must be Y-m-d',
                'new_expiry' => null,
            ];
        }

        $today = gmdate('Y-m-d');
        if ($newExpiryDate < $today) {
            return [
                'success' => false,
                'code' => 'invalid_expiry',
                'message' => 'new_expiry_date must be today or later',
                'new_expiry' => null,
            ];
        }

        $row = $this->repo->findTenant($companyId);
        if ($row === null) {
            return [
                'success' => false,
                'code' => 'invalid_company',
                'message' => 'No subscription engine row for company',
                'new_expiry' => null,
            ];
        }

        $oldStatus = strtoupper((string) ($row['current_status'] ?? SubscriptionStatus::ACTIVE));
        $previous = substr((string) ($row['subscription_end'] ?? ''), 0, 10);

        if (!$this->repo->extendExpiry($companyId, $newExpiryDate)) {
            return [
                'success' => false,
                'code' => 'persist_failed',
                'message' => 'Failed to update expiry',
                'new_expiry' => null,
            ];
        }

        $this->renewals->insertHistory(
            $companyId,
            $previous !== '' ? $previous : null,
            $newExpiryDate,
            'extend',
            $actorId,
            'admin-extend'
        );
        $this->repo->insertLifecycleAudit(
            $companyId,
            'EXTENDED',
            $oldStatus,
            SubscriptionStatus::ACTIVE,
            $actorId
        );

        if (class_exists(SubscriptionBootstrap::class, false)) {
            SubscriptionBootstrap::bindForCompany($companyId);
        }

        error_log(sprintf(
            'RATEB subscription extended: company_id=%d actor_id=%d new_expiry=%s',
            $companyId,
            $actorId,
            $newExpiryDate
        ));

        return [
            'success' => true,
            'code' => 'extended',
            'message' => 'Expiry extended; tenant ACTIVE',
            'new_expiry' => $newExpiryDate,
        ];
    }

    public function canView(int $actorId = 0): bool
    {
        unset($actorId);
        if (function_exists('rateb_can')) {
            return rateb_can('subscriptions.view') || rateb_can('subscriptions.manage');
        }
        return false;
    }

    public function canManage(int $actorId): bool
    {
        if ($this->authorizer !== null) {
            return $this->authorizer->canRenew($actorId);
        }
        if (function_exists('rateb_can') && rateb_can('subscriptions.manage')) {
            return DefaultRenewalAuthorizer::actorMayRenew($actorId)
                || (function_exists('rateb_is_super_admin') && rateb_is_super_admin());
        }
        return DefaultRenewalAuthorizer::actorMayRenew($actorId);
    }
}

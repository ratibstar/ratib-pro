<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

final class Subscription extends Model
{
    protected string $table = 'rateb_subscriptions';
    protected bool $tenantScoped = false;
    protected array $fillable = [
        'company_id', 'plan_id', 'status', 'billing_cycle', 'amount',
        'starts_at', 'ends_at', 'auto_renew',
    ];

    public function getActiveForCompany(int $companyId): ?array
    {
        return $this->queryOne(
            "SELECT * FROM rateb_subscriptions WHERE company_id = :cid AND status = 'active' ORDER BY id DESC LIMIT 1",
            ['cid' => $companyId]
        );
    }

    public function withRelations(int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);
        return $this->query(
            "SELECT s.*, c.name AS company_name, p.name AS plan_name
             FROM rateb_subscriptions s
             JOIN rateb_companies c ON c.id = s.company_id
             JOIN rateb_plans p ON p.id = s.plan_id
             ORDER BY s.id DESC LIMIT {$limit} OFFSET {$offset}"
        );
    }
}

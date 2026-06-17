<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\SupplierEvaluation;

final class SupplierEvaluationService
{
  /** @return array{overall:float,percent:float,tier:string} */
    public function computeMetrics(int $quality, int $delivery, int $price, int $service): array
    {
        $overall = (new SupplierEvaluation())->recalculateOverall([$quality, $delivery, $price, $service]);
        $percent = round($overall * 10, 1);
        return [
            'overall' => $overall,
            'percent' => $percent,
            'tier' => $this->tierFromOverall($overall),
        ];
    }

    public function tierFromOverall(float $overall): string
    {
        if ($overall >= 9.0) {
            return 'excellent';
        }
        if ($overall >= 7.5) {
            return 'very_good';
        }
        if ($overall >= 5.0) {
            return 'good';
        }
        return 'weak';
    }

    public function tierLabel(string $tier): string
    {
        $key = 'eval_tier_' . $tier;
        $label = __($key);
        return $label !== $key ? $label : $tier;
    }

    public function tierBadgeClass(string $tier): string
    {
        return match ($tier) {
            'excellent' => 'success',
            'very_good' => 'primary',
            'good' => 'info',
            default => 'warning',
        };
    }

    /** @return list<array<string, mixed>> */
    public function historyForSupplier(int $companyId, int $supplierId, int $excludeId = 0, int $limit = 10): array
    {
        if ($companyId < 1 || $supplierId < 1) {
            return [];
        }
        $sql = 'SELECT id, evaluation_no, evaluation_date, period_start, period_end,
                       overall_score, score_percent, rating_tier, evaluator_name,
                       manager_approval, status, quality_score, delivery_score, price_score, service_score
                FROM rateb_supplier_evaluations
                WHERE company_id = :cid AND supplier_id = :sid';
        $params = ['cid' => $companyId, 'sid' => $supplierId];
        if ($excludeId > 0) {
            $sql .= ' AND id != :xid';
            $params['xid'] = $excludeId;
        }
        $sql .= ' ORDER BY evaluation_date DESC, id DESC LIMIT ' . max(1, min(20, $limit));
        return (new SupplierEvaluation())->query($sql, $params);
    }

    public function refreshSupplierRating(int $supplierId): void
    {
        (new SupplierEvaluation())->updateSupplierRating($supplierId);
    }
}

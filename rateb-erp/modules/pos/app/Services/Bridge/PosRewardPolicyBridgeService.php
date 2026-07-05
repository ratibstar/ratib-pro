<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Bridge;

use Rateb\App\Core\Database;
use Rateb\App\Models\PosSetting;

/** Company reward reversal policy from rateb_pos_settings.settings_json. */
final class PosRewardPolicyBridgeService
{
    /**
     * @return array{
     *   loyalty_clawback_earned: bool,
     *   loyalty_restore_redeemed: bool,
     *   loyalty_pro_rata: bool,
     *   coupon_reversal_full_only: bool,
     *   gift_card_refund_to_card: bool,
     *   points_per_currency: float,
     *   currency_per_point: float
     * }
     */
    public function policy(int $companyId): array
    {
        $defaults = [
            'loyalty_clawback_earned' => true,
            'loyalty_restore_redeemed' => true,
            'loyalty_pro_rata' => true,
            'coupon_reversal_full_only' => true,
            'gift_card_refund_to_card' => true,
            'points_per_currency' => 1.0,
            'currency_per_point' => 0.01,
        ];
        if ($companyId < 1) {
            return $defaults;
        }
        $row = (new PosSetting())->queryOne(
            'SELECT settings_json FROM rateb_pos_settings WHERE company_id = :cid AND (branch_id IS NULL OR branch_id = 0) LIMIT 1',
            ['cid' => $companyId]
        );
        if (!$row) {
            return $defaults;
        }
        $json = $row['settings_json'] ?? null;
        $cfg = is_string($json) ? json_decode($json, true) : (is_array($json) ? $json : []);
        if (!is_array($cfg)) {
            return $defaults;
        }
        $rewards = is_array($cfg['rewards'] ?? null) ? $cfg['rewards'] : $cfg;
        return [
            'loyalty_clawback_earned' => (bool) ($rewards['loyalty_clawback_earned'] ?? $defaults['loyalty_clawback_earned']),
            'loyalty_restore_redeemed' => (bool) ($rewards['loyalty_restore_redeemed'] ?? $defaults['loyalty_restore_redeemed']),
            'loyalty_pro_rata' => (bool) ($rewards['loyalty_pro_rata'] ?? $defaults['loyalty_pro_rata']),
            'coupon_reversal_full_only' => (bool) ($rewards['coupon_reversal_full_only'] ?? $defaults['coupon_reversal_full_only']),
            'gift_card_refund_to_card' => (bool) ($rewards['gift_card_refund_to_card'] ?? $defaults['gift_card_refund_to_card']),
            'points_per_currency' => max(0.0001, (float) ($rewards['points_per_currency'] ?? $defaults['points_per_currency'])),
            'currency_per_point' => max(0.0001, (float) ($rewards['currency_per_point'] ?? $defaults['currency_per_point'])),
        ];
    }
}

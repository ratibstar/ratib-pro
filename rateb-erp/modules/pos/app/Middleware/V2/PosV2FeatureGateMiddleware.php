<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Middleware\V2;

use Rateb\App\Core\Middleware\MiddlewareInterface;
use Rateb\App\Core\Response;
use Rateb\App\Pos\Application\V2\PosV2RequestScope;

/**
 * Blocks V2 routes when POS_V2_ENABLED resolves to false.
 *
 * Mode "web"  → redirect to V1 register.
 * Mode "api"  → structured JSON error (OpenAPI envelope).
 */
final class PosV2FeatureGateMiddleware implements MiddlewareInterface
{
    private readonly string $mode;

    public function __construct(string $mode = 'web')
    {
        $this->mode = strtolower(trim($mode)) === 'api' ? 'api' : 'web';
    }

    public function handle(): bool
    {
        $root = PosV2RequestScope::ensure();
        $context = $root->resolveFeatureFlagContext();
        $enabled = false;

        if ($context !== null) {
            $enabled = $root->services->featureFlags->isEnabled($context);
        }

        if ($enabled) {
            return true;
        }

        if ($this->mode === 'api') {
            Response::json([
                'success' => false,
                'error' => [
                    'code' => 'POS_V2_DISABLED',
                    'message' => 'POS V2 is not enabled for this company, branch, or terminal.',
                    'field' => null,
                    'details' => [
                        'fallback' => 'v1',
                    ],
                ],
            ], 404);

            return false;
        }

        $target = function_exists('rateb_app_url')
            ? rateb_app_url('pos/register')
            : (defined('RATEB_BASE_URL') ? RATEB_BASE_URL . '/admin/ops/pos/register' : '/admin/ops/pos/register');

        Response::redirect($target);

        return false;
    }
}

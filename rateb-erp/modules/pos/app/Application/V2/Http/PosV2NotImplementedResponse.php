<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Application\V2\Http;

use Rateb\App\Core\Response;

/** Standard 501 envelope for unfinished POS V2 endpoints. */
final class PosV2NotImplementedResponse
{
    /** @return array{success: false, error: array{code: string}} */
    public static function body(): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => 'NOT_IMPLEMENTED',
            ],
        ];
    }

    public static function send(): void
    {
        Response::json(self::body(), 501);
    }
}

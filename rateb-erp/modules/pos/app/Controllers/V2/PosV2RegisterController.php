<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Controllers\V2;

use Rateb\App\Pos\Application\V2\Http\PosV2NotImplementedResponse;
use Rateb\App\Pos\Controllers\PosBaseController;

/** V2 register web shell (Sprint 3). */
final class PosV2RegisterController extends PosBaseController
{
    public function index(): void
    {
        PosV2NotImplementedResponse::send();
    }
}

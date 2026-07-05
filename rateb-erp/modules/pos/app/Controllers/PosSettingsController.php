<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Controllers;

use Rateb\App\Core\Csrf;

final class PosSettingsController extends PosBaseController
{
    public function index(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/settings');
        $this->posView('settings/index', [
            'title' => __('pos_settings'),
            'csrf' => Csrf::token(),
        ]);
    }
}

<?php
declare(strict_types=1);

interface RATEB_ClientDashboard_Action_Interface
{
    /**
     * @return array{ok: bool, code: string, message: string, meta: array<string, mixed>, queued?: bool}
     */
    public function execute(RATEB_ClientDashboard_Action_Context $ctx): array;
}

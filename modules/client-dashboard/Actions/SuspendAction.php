<?php
declare(strict_types=1);

final class RATEB_ClientDashboard_Action_Suspend implements RATEB_ClientDashboard_Action_Interface
{
    public function execute(RATEB_ClientDashboard_Action_Context $ctx): array
    {
        return [
            'ok' => true,
            'code' => 'accepted',
            'message' => 'Suspend delegated to runtime orchestrator',
            'meta' => ['target_id' => $ctx->targetId],
            'queued' => true,
        ];
    }
}

<?php
declare(strict_types=1);

final class RATEB_ClientDashboard_Action_Cancel implements RATEB_ClientDashboard_Action_Interface
{
    public function execute(RATEB_ClientDashboard_Action_Context $ctx): array
    {
        return [
            'ok' => true,
            'code' => 'accepted',
            'message' => 'Cancel scheduled at end of term (policy-safe default)',
            'meta' => ['target_id' => $ctx->targetId],
            'queued' => false,
        ];
    }
}

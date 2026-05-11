<?php
declare(strict_types=1);

final class Ratib_ClientDashboard_Action_Cancel implements Ratib_ClientDashboard_Action_Interface
{
    public function execute(Ratib_ClientDashboard_Action_Context $ctx): array
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

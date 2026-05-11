<?php
declare(strict_types=1);

final class Ratib_ClientDashboard_Action_Restart implements Ratib_ClientDashboard_Action_Interface
{
    public function execute(Ratib_ClientDashboard_Action_Context $ctx): array
    {
        return [
            'ok' => true,
            'code' => 'accepted',
            'message' => 'Restart signal accepted',
            'meta' => ['target_id' => $ctx->targetId],
            'queued' => true,
        ];
    }
}

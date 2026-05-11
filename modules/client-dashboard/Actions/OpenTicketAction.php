<?php
declare(strict_types=1);

final class Ratib_ClientDashboard_Action_OpenTicket implements Ratib_ClientDashboard_Action_Interface
{
    public function execute(Ratib_ClientDashboard_Action_Context $ctx): array
    {
        return [
            'ok' => true,
            'code' => 'accepted',
            'message' => 'Ticket composer opens via support module bridge',
            'meta' => [
                'target_id' => $ctx->targetId,
                'hint' => pageUrl('help-center.php'),
            ],
            'queued' => false,
        ];
    }
}

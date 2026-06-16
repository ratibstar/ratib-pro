<?php
declare(strict_types=1);

final class RATEB_ClientDashboard_Action_OpenTicket implements RATEB_ClientDashboard_Action_Interface
{
    public function execute(RATEB_ClientDashboard_Action_Context $ctx): array
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

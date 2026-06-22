<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services;

use Ratib\ContactCenter\App\Application\Services\QueueDeliveryService;
use Ratib\ContactCenter\App\Application\Services\Recordings\RecordingIngestBridge;
use Ratib\ContactCenter\App\Application\Services\Supervisor\SupervisorAlertBridge;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Domain\Agents\AgentStateService;
use Ratib\ContactCenter\App\Domain\AI\Assistant\AiAssistantEngine;
use Ratib\ContactCenter\App\Domain\AI\Insights\AiCallInsightsEngine;
use Ratib\ContactCenter\App\Domain\AI\Insights\AiConversationInsightsEngine;
use Ratib\ContactCenter\App\Domain\AI\Insights\AiQaEngine;
use Ratib\ContactCenter\App\Domain\Queue\QueueRealtimeService;

/**
 * Wires EventBus subscribers once at bootstrap.
 */
final class RealtimeOrchestrator
{
    private static bool $booted = false;

    public static function boot(?EventBus $bus = null): EventBus
    {
        $eventBus = $bus ?? EventBus::instance();

        if (self::$booted) {
            return $eventBus;
        }

        $agentState = new AgentStateService($eventBus);
        $queueRealtime = new QueueRealtimeService($eventBus);
        $conversationBridge = new ConversationEventBridge();
        $erpLogger = new ErpActivityLogger();
        $aiAssistant = new AiAssistantEngine($eventBus);
        $supervisorAlerts = new SupervisorAlertBridge();
        $recordingIngest = new RecordingIngestBridge();
        $aiQa = new AiQaEngine();
        $aiCallInsights = new AiCallInsightsEngine();
        $aiConvInsights = new AiConversationInsightsEngine();

        $eventBus->subscribe($agentState);
        $eventBus->subscribe($queueRealtime);
        $eventBus->subscribe($conversationBridge);
        $eventBus->subscribe($erpLogger);
        $eventBus->subscribe($aiAssistant);
        $eventBus->subscribe($supervisorAlerts);
        $eventBus->subscribe($recordingIngest);
        $eventBus->subscribe($aiQa);
        $eventBus->subscribe($aiCallInsights);
        $eventBus->subscribe($aiConvInsights);
        QueueDeliveryService::registerSubscriber();

        EventBus::setInstance($eventBus);
        self::$booted = true;

        return $eventBus;
    }
}

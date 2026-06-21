<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services;

use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Domain\Agents\AgentStateService;
use Ratib\ContactCenter\App\Domain\AI\Assistant\AiAssistantEngine;
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

        $eventBus->subscribe($agentState);
        $eventBus->subscribe($queueRealtime);
        $eventBus->subscribe($conversationBridge);
        $eventBus->subscribe($erpLogger);
        $eventBus->subscribe($aiAssistant);

        EventBus::setInstance($eventBus);
        self::$booted = true;

        return $eventBus;
    }
}

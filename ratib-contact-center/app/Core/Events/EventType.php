<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Core\Events;

/**
 * Standardized real-time event type constants (immutable identifiers).
 */
final class EventType
{
    // Call
    public const CALL_INCOMING = 'CALL_INCOMING';
    public const CALL_CONNECTED = 'CALL_CONNECTED';
    public const CALL_ENDED = 'CALL_ENDED';
    public const CALL_TRANSFERRED = 'CALL_TRANSFERRED';
    public const CALL_ACCEPTED = 'CALL_ACCEPTED';
    public const CALL_HOLD = 'CALL_HOLD';
    public const CALL_RESUME = 'CALL_RESUME';

    // Softphone / SIP
    public const SIP_REGISTERED = 'SIP_REGISTERED';
    public const SIP_UNREGISTERED = 'SIP_UNREGISTERED';
    public const SOFTPHONE_STATE = 'SOFTPHONE_STATE';

    // IVR
    public const IVR_STARTED = 'IVR_STARTED';
    public const IVR_NODE_ENTERED = 'IVR_NODE_ENTERED';
    public const IVR_WAITING_INPUT = 'IVR_WAITING_INPUT';
    public const IVR_COMPLETED = 'IVR_COMPLETED';

    // Agent
    public const AGENT_LOGIN = 'AGENT_LOGIN';
    public const AGENT_READY = 'AGENT_READY';
    public const AGENT_BUSY = 'AGENT_BUSY';
    public const AGENT_WRAPUP = 'AGENT_WRAPUP';
    public const AGENT_OFFLINE = 'AGENT_OFFLINE';
    public const AGENT_STATE_UPDATED = 'AGENT_STATE_UPDATED';

    // Queue
    public const QUEUE_JOINED = 'QUEUE_JOINED';
    public const QUEUE_ASSIGNED = 'QUEUE_ASSIGNED';
    public const QUEUE_WAIT_TIME_UPDATED = 'QUEUE_WAIT_TIME_UPDATED';
    public const QUEUE_SNAPSHOT = 'QUEUE_SNAPSHOT';

    // AI Routing
    public const CALL_SCORING_STARTED = 'CALL_SCORING_STARTED';
    public const CALL_SCORING_COMPLETED = 'CALL_SCORING_COMPLETED';
    public const CALL_ASSIGNED = 'CALL_ASSIGNED';
    public const SLA_ESCALATED_CALL = 'SLA_ESCALATED_CALL';

    // Unified conversations (omnichannel inbox)
    public const CONVERSATION_CREATED = 'CONVERSATION_CREATED';
    public const CONVERSATION_UPDATED = 'CONVERSATION_UPDATED';
    public const MESSAGE_RECEIVED = 'MESSAGE_RECEIVED';
    public const MESSAGE_SENT = 'MESSAGE_SENT';
    public const CONVERSATION_ASSIGNED = 'CONVERSATION_ASSIGNED';
    public const CONVERSATION_PRIORITY_CHANGED = 'CONVERSATION_PRIORITY_CHANGED';

    // Dashboard aggregates (derived, still event-driven)
    public const SLA_ALERT = 'SLA_ALERT';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::CALL_INCOMING,
            self::CALL_CONNECTED,
            self::CALL_ENDED,
            self::CALL_TRANSFERRED,
            self::CALL_ACCEPTED,
            self::CALL_HOLD,
            self::CALL_RESUME,
            self::SIP_REGISTERED,
            self::SIP_UNREGISTERED,
            self::SOFTPHONE_STATE,
            self::IVR_STARTED,
            self::IVR_NODE_ENTERED,
            self::IVR_WAITING_INPUT,
            self::IVR_COMPLETED,
            self::AGENT_LOGIN,
            self::AGENT_READY,
            self::AGENT_BUSY,
            self::AGENT_WRAPUP,
            self::AGENT_OFFLINE,
            self::AGENT_STATE_UPDATED,
            self::QUEUE_JOINED,
            self::QUEUE_ASSIGNED,
            self::QUEUE_WAIT_TIME_UPDATED,
            self::QUEUE_SNAPSHOT,
            self::CALL_SCORING_STARTED,
            self::CALL_SCORING_COMPLETED,
            self::CALL_ASSIGNED,
            self::SLA_ESCALATED_CALL,
            self::CONVERSATION_CREATED,
            self::CONVERSATION_UPDATED,
            self::MESSAGE_RECEIVED,
            self::MESSAGE_SENT,
            self::CONVERSATION_ASSIGNED,
            self::CONVERSATION_PRIORITY_CHANGED,
            self::SLA_ALERT,
        ];
    }
}

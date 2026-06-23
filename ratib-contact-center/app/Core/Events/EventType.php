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

    // AI Copilot (advisory — does not override routing)
    public const AI_SUMMARY_UPDATED = 'AI_SUMMARY_UPDATED';
    public const AI_SENTIMENT_UPDATED = 'AI_SENTIMENT_UPDATED';
    public const AI_INTENT_DETECTED = 'AI_INTENT_DETECTED';
    public const AI_RECOMMENDATION_READY = 'AI_RECOMMENDATION_READY';
    public const AI_REPLY_SUGGESTED = 'AI_REPLY_SUGGESTED';
    public const AI_TICKET_CREATED = 'AI_TICKET_CREATED';
    public const AI_ASSISTANT_UPDATE = 'AI_ASSISTANT_UPDATE';

    // Dashboard aggregates (derived, still event-driven)
    public const SLA_ALERT = 'SLA_ALERT';

    // Production operations (Phase 8)
    public const OPS_PBX_UPDATED = 'OPS_PBX_UPDATED';
    public const OPS_PBX_ACTIVATED = 'OPS_PBX_ACTIVATED';
    public const OPS_SIP_UPDATED = 'OPS_SIP_UPDATED';
    public const OPS_QUEUE_UPDATED = 'OPS_QUEUE_UPDATED';
    public const OPS_IVR_UPDATED = 'OPS_IVR_UPDATED';
    public const OPS_IVR_PUBLISHED = 'OPS_IVR_PUBLISHED';
    public const OPS_AGENT_PROVISIONED = 'OPS_AGENT_PROVISIONED';
    public const OPS_DIAGNOSTIC_RUN = 'OPS_DIAGNOSTIC_RUN';
    public const OPS_CHECKLIST_UPDATED = 'OPS_CHECKLIST_UPDATED';
    public const OPS_CHECKLIST_AUTO_VERIFY = 'OPS_CHECKLIST_AUTO_VERIFY';
    public const OPS_AUDIT_LOGGED = 'OPS_AUDIT_LOGGED';
    public const OPS_HEALTH_UPDATED = 'OPS_HEALTH_UPDATED';

    // Supervisor & workforce (Phase 9)
    public const SUPERVISOR_DASHBOARD_UPDATED = 'SUPERVISOR_DASHBOARD_UPDATED';
    public const SUPERVISOR_WALLBOARD_UPDATED = 'SUPERVISOR_WALLBOARD_UPDATED';
    public const SUPERVISOR_SLA_UPDATED = 'SUPERVISOR_SLA_UPDATED';
    public const SUPERVISOR_SHIFT_UPDATED = 'SUPERVISOR_SHIFT_UPDATED';
    public const SUPERVISOR_ATTENDANCE_UPDATED = 'SUPERVISOR_ATTENDANCE_UPDATED';
    public const SUPERVISOR_BREAK_STARTED = 'SUPERVISOR_BREAK_STARTED';
    public const SUPERVISOR_BREAK_ENDED = 'SUPERVISOR_BREAK_ENDED';
    public const SUPERVISOR_OCCUPANCY_UPDATED = 'SUPERVISOR_OCCUPANCY_UPDATED';
    public const SUPERVISOR_ADHERENCE_UPDATED = 'SUPERVISOR_ADHERENCE_UPDATED';
    public const SUPERVISOR_ALERT_RAISED = 'SUPERVISOR_ALERT_RAISED';
    public const SUPERVISOR_ALERT_ACKNOWLEDGED = 'SUPERVISOR_ALERT_ACKNOWLEDGED';
    public const SUPERVISOR_AUDIT_LOGGED = 'SUPERVISOR_AUDIT_LOGGED';

    // CRM (Phase 10A)
    public const CRM_ACCOUNT_UPDATED = 'CRM_ACCOUNT_UPDATED';
    public const CRM_CONTACT_UPDATED = 'CRM_CONTACT_UPDATED';
    public const CRM_ACTIVITY_RECORDED = 'CRM_ACTIVITY_RECORDED';
    public const CRM_NOTE_ADDED = 'CRM_NOTE_ADDED';
    public const CRM_TAG_UPDATED = 'CRM_TAG_UPDATED';
    public const CRM_DOCUMENT_UPLOADED = 'CRM_DOCUMENT_UPLOADED';
    public const CRM_ERP_SYNCED = 'CRM_ERP_SYNCED';

    // Ticketing (Phase 10B)
    public const TICKET_CREATED = 'TICKET_CREATED';
    public const TICKET_ASSIGNED = 'TICKET_ASSIGNED';
    public const TICKET_ESCALATED = 'TICKET_ESCALATED';
    public const TICKET_RESOLVED = 'TICKET_RESOLVED';
    public const TICKET_REOPENED = 'TICKET_REOPENED';
    public const TICKET_MERGED = 'TICKET_MERGED';
    public const TICKET_SPLIT = 'TICKET_SPLIT';
    public const TICKET_COMMENT_ADDED = 'TICKET_COMMENT_ADDED';
    public const TICKET_SLA_BREACHED = 'TICKET_SLA_BREACHED';

    // QA (Phase 10C)
    public const QA_REVIEW_CREATED = 'QA_REVIEW_CREATED';
    public const QA_REVIEW_COMPLETED = 'QA_REVIEW_COMPLETED';
    public const QA_SCORE_UPDATED = 'QA_SCORE_UPDATED';

    // Recordings (Phase 10D)
    public const RECORDING_INGESTED = 'RECORDING_INGESTED';

    // Analytics (Phase 10E)
    public const ANALYTICS_AGGREGATED = 'ANALYTICS_AGGREGATED';

    // AI insights (Phase 10G)
    public const AI_QA_COMPLETED = 'AI_QA_COMPLETED';
    public const AI_INSIGHT_CREATED = 'AI_INSIGHT_CREATED';
    public const AI_RISK_DETECTED = 'AI_RISK_DETECTED';

    // SaaS Billing (Phase 11)
    public const BILLING_SUBSCRIPTION_UPDATED = 'BILLING_SUBSCRIPTION_UPDATED';
    public const BILLING_INVOICE_CREATED = 'BILLING_INVOICE_CREATED';
    public const BILLING_INVOICE_PAID = 'BILLING_INVOICE_PAID';
    public const BILLING_PAYMENT_INITIATED = 'BILLING_PAYMENT_INITIATED';
    public const BILLING_PAYMENT_SUCCEEDED = 'BILLING_PAYMENT_SUCCEEDED';
    public const BILLING_USAGE_RECORDED = 'BILLING_USAGE_RECORDED';
    public const BILLING_CYCLE_COMPLETED = 'BILLING_CYCLE_COMPLETED';
    public const LICENSE_UPDATED = 'LICENSE_UPDATED';

    // White label & reseller (Phase 11)
    public const WHITELABEL_UPDATED = 'WHITELABEL_UPDATED';
    public const RESELLER_UPDATED = 'RESELLER_UPDATED';
    public const RESELLER_COMMISSION_RECORDED = 'RESELLER_COMMISSION_RECORDED';
    public const TENANT_PROVISIONED = 'TENANT_PROVISIONED';

    // Disaster recovery (Phase 11)
    public const BACKUP_COMPLETED = 'BACKUP_COMPLETED';
    public const RESTORE_QUEUED = 'RESTORE_QUEUED';
    public const MONITOR_ALERT = 'MONITOR_ALERT';
    public const PBX_CLUSTER_UPDATED = 'PBX_CLUSTER_UPDATED';
    public const PBX_FAILOVER = 'PBX_FAILOVER';

    // Marketplace (Phase 11)
    public const MARKETPLACE_SUBSCRIBED = 'MARKETPLACE_SUBSCRIBED';
    public const MARKETPLACE_UNSUBSCRIBED = 'MARKETPLACE_UNSUBSCRIBED';

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
            self::AI_SUMMARY_UPDATED,
            self::AI_SENTIMENT_UPDATED,
            self::AI_INTENT_DETECTED,
            self::AI_RECOMMENDATION_READY,
            self::AI_REPLY_SUGGESTED,
            self::AI_TICKET_CREATED,
            self::AI_ASSISTANT_UPDATE,
            self::SLA_ALERT,
            self::OPS_PBX_UPDATED,
            self::OPS_PBX_ACTIVATED,
            self::OPS_SIP_UPDATED,
            self::OPS_QUEUE_UPDATED,
            self::OPS_IVR_UPDATED,
            self::OPS_IVR_PUBLISHED,
            self::OPS_AGENT_PROVISIONED,
            self::OPS_DIAGNOSTIC_RUN,
            self::OPS_CHECKLIST_UPDATED,
            self::OPS_CHECKLIST_AUTO_VERIFY,
            self::OPS_AUDIT_LOGGED,
            self::OPS_HEALTH_UPDATED,
            self::SUPERVISOR_DASHBOARD_UPDATED,
            self::SUPERVISOR_WALLBOARD_UPDATED,
            self::SUPERVISOR_SLA_UPDATED,
            self::SUPERVISOR_SHIFT_UPDATED,
            self::SUPERVISOR_ATTENDANCE_UPDATED,
            self::SUPERVISOR_BREAK_STARTED,
            self::SUPERVISOR_BREAK_ENDED,
            self::SUPERVISOR_OCCUPANCY_UPDATED,
            self::SUPERVISOR_ADHERENCE_UPDATED,
            self::SUPERVISOR_ALERT_RAISED,
            self::SUPERVISOR_ALERT_ACKNOWLEDGED,
            self::SUPERVISOR_AUDIT_LOGGED,
            self::CRM_ACCOUNT_UPDATED,
            self::CRM_CONTACT_UPDATED,
            self::CRM_ACTIVITY_RECORDED,
            self::CRM_NOTE_ADDED,
            self::CRM_TAG_UPDATED,
            self::CRM_DOCUMENT_UPLOADED,
            self::CRM_ERP_SYNCED,
            self::TICKET_CREATED,
            self::TICKET_ASSIGNED,
            self::TICKET_ESCALATED,
            self::TICKET_RESOLVED,
            self::TICKET_REOPENED,
            self::TICKET_MERGED,
            self::TICKET_SPLIT,
            self::TICKET_COMMENT_ADDED,
            self::TICKET_SLA_BREACHED,
            self::QA_REVIEW_CREATED,
            self::QA_REVIEW_COMPLETED,
            self::QA_SCORE_UPDATED,
            self::RECORDING_INGESTED,
            self::ANALYTICS_AGGREGATED,
            self::AI_QA_COMPLETED,
            self::AI_INSIGHT_CREATED,
            self::AI_RISK_DETECTED,
            self::BILLING_SUBSCRIPTION_UPDATED,
            self::BILLING_INVOICE_CREATED,
            self::BILLING_INVOICE_PAID,
            self::BILLING_PAYMENT_INITIATED,
            self::BILLING_PAYMENT_SUCCEEDED,
            self::BILLING_USAGE_RECORDED,
            self::BILLING_CYCLE_COMPLETED,
            self::LICENSE_UPDATED,
            self::WHITELABEL_UPDATED,
            self::RESELLER_UPDATED,
            self::RESELLER_COMMISSION_RECORDED,
            self::TENANT_PROVISIONED,
            self::BACKUP_COMPLETED,
            self::RESTORE_QUEUED,
            self::MONITOR_ALERT,
            self::PBX_CLUSTER_UPDATED,
            self::PBX_FAILOVER,
            self::MARKETPLACE_SUBSCRIBED,
            self::MARKETPLACE_UNSUBSCRIBED,
        ];
    }
}

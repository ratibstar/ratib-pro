<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories;

use Ratib\ContactCenter\App\Core\Database;

final class AiContextRepository
{
    /** @return array<string, mixed>|null */
    public function findByConversation(int $tenantId, int $conversationId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rcc_ai_context WHERE tenant_id = :tid AND conversation_id = :cid LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'cid' => $conversationId]);
        $row = $stmt->fetch();
        return $row !== false ? $this->hydrate($row) : null;
    }

    /** @param array<string, mixed> $data */
    public function upsert(int $tenantId, int $conversationId, array $data): array
    {
        $existing = $this->findByConversation($tenantId, $conversationId);
        if ($existing === null) {
            $stmt = Database::connection()->prepare(
                'INSERT INTO rcc_ai_context
                 (tenant_id, conversation_id, sentiment, sentiment_score, intent, intent_confidence,
                  summary_live, summary_final, risk_score, recommended_action, suggested_reply,
                  suggestions_json, ticket_id)
                 VALUES
                 (:tid, :cid, :sent, :ss, :intent, :ic, :slive, :sfinal, :risk, :action, :reply, :sug, :ticket)'
            );
        } else {
            $stmt = Database::connection()->prepare(
                'UPDATE rcc_ai_context SET
                 sentiment = :sent, sentiment_score = :ss, intent = :intent, intent_confidence = :ic,
                 summary_live = :slive, summary_final = COALESCE(:sfinal, summary_final),
                 risk_score = :risk, recommended_action = :action, suggested_reply = :reply,
                 suggestions_json = :sug, ticket_id = COALESCE(:ticket, ticket_id)
                 WHERE tenant_id = :tid AND conversation_id = :cid'
            );
        }

        $suggestions = $data['suggestions_json'] ?? $data['suggestions'] ?? null;
        if (is_array($suggestions)) {
            $suggestions = json_encode($suggestions, JSON_UNESCAPED_UNICODE);
        }

        $stmt->execute([
            'tid' => $tenantId,
            'cid' => $conversationId,
            'sent' => $data['sentiment'] ?? null,
            'ss' => $data['sentiment_score'] ?? null,
            'intent' => $data['intent'] ?? null,
            'ic' => $data['intent_confidence'] ?? null,
            'slive' => $data['summary_live'] ?? null,
            'sfinal' => $data['summary_final'] ?? null,
            'risk' => $data['risk_score'] ?? null,
            'action' => $data['recommended_action'] ?? null,
            'reply' => $data['suggested_reply'] ?? null,
            'sug' => $suggestions,
            'ticket' => $data['ticket_id'] ?? null,
        ]);

        return $this->findByConversation($tenantId, $conversationId) ?? [];
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): array
    {
        $suggestions = $row['suggestions_json'] ?? null;
        if (is_string($suggestions)) {
            $decoded = json_decode($suggestions, true);
            $suggestions = is_array($decoded) ? $decoded : [];
        }

        return [
            'conversation_id' => (int) $row['conversation_id'],
            'tenant_id' => (int) $row['tenant_id'],
            'sentiment' => $row['sentiment'] ?? null,
            'sentiment_score' => isset($row['sentiment_score']) ? (float) $row['sentiment_score'] : null,
            'intent' => $row['intent'] ?? null,
            'intent_confidence' => isset($row['intent_confidence']) ? (float) $row['intent_confidence'] : null,
            'summary_live' => $row['summary_live'] ?? null,
            'summary_final' => $row['summary_final'] ?? null,
            'risk_score' => isset($row['risk_score']) ? (float) $row['risk_score'] : null,
            'recommended_action' => $row['recommended_action'] ?? null,
            'suggested_reply' => $row['suggested_reply'] ?? null,
            'suggestions' => is_array($suggestions) ? $suggestions : [],
            'ticket_id' => isset($row['ticket_id']) ? (int) $row['ticket_id'] : null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}

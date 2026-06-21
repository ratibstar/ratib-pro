<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories;

use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Domain\Conversation\ConversationMessage;

final class ConversationMessageRepository
{
    public function append(int $tenantId, int $conversationId, ConversationMessage $message): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO rcc_conversation_messages
             (tenant_id, conversation_id, channel, direction, message, payload, external_id, sender_type, sender_id)
             VALUES (:tid, :conv, :ch, :dir, :msg, :payload, :ext, :stype, :sid)'
        );
        $stmt->execute([
            'tid' => $tenantId,
            'conv' => $conversationId,
            'ch' => $message->channel,
            'dir' => $message->direction,
            'msg' => $message->message,
            'payload' => json_encode($message->payload, JSON_UNESCAPED_UNICODE),
            'ext' => $message->externalId,
            'stype' => $message->senderType,
            'sid' => $message->senderId,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listByConversation(int $tenantId, int $conversationId, int $limit = 100): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, channel, direction, message, payload, sender_type, sender_id, created_at
             FROM rcc_conversation_messages
             WHERE tenant_id = :tid AND conversation_id = :conv
             ORDER BY created_at ASC LIMIT :lim'
        );
        $stmt->bindValue('tid', $tenantId, \PDO::PARAM_INT);
        $stmt->bindValue('conv', $conversationId, \PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $payload = $row['payload'] ?? '{}';
            if (is_string($payload)) {
                $decoded = json_decode($payload, true);
                $payload = is_array($decoded) ? $decoded : [];
            }
            $list[] = [
                'id' => (int) $row['id'],
                'channel' => (string) $row['channel'],
                'direction' => (string) $row['direction'],
                'message' => (string) $row['message'],
                'payload' => $payload,
                'sender_type' => (string) $row['sender_type'],
                'sender_id' => isset($row['sender_id']) ? (int) $row['sender_id'] : null,
                'created_at' => (string) $row['created_at'],
            ];
        }
        return $list;
    }
}

<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Knowledge;

use Ratib\ContactCenter\App\Core\Database;

final class KnowledgeSuggestionService
{
    public function __construct(private readonly KnowledgeBaseService $kb = new KnowledgeBaseService())
    {
    }

    /** @return list<array<string, mixed>> */
    public function suggestForConversation(int $tenantId, int $conversationId, int $limit = 5): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT subject, channel FROM rcc_conversations WHERE tenant_id = :tid AND id = :id LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $conversationId]);
        $conv = $stmt->fetch();
        if ($conv === false) {
            return [];
        }
        $query = trim((string) (($conv['subject'] ?? '') . ' ' . ($conv['channel'] ?? '')));
        if ($query === '') {
            $query = 'support';
        }
        return $this->kb->search($tenantId, $query, $limit);
    }

    /** @return list<array<string, mixed>> */
    public function suggestForQuery(int $tenantId, string $query, int $limit = 5): array
    {
        return $this->kb->search($tenantId, $query, $limit);
    }
}

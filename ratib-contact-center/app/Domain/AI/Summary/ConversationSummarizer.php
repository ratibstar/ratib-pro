<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\AI\Summary;

use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\ConversationMessageRepository;

/**
 * Live and wrap-up conversation summaries (extractive; LLM-ready).
 */
final class ConversationSummarizer
{
    public function __construct(
        private readonly ?ConversationMessageRepository $messages = null
    ) {
    }

    /**
     * @param list<array<string, mixed>> $messages
     */
    public function summarizeLive(array $messages, ?array $conversation = null): string
    {
        $recent = array_slice($messages, -8);
        $lines = [];
        foreach ($recent as $msg) {
            $dir = ($msg['direction'] ?? '') === 'outbound' ? 'Agent' : 'Customer';
            $channel = (string) ($msg['channel'] ?? 'chat');
            $text = trim((string) ($msg['message'] ?? ''));
            if ($text === '') {
                continue;
            }
            $lines[] = $dir . ' (' . $channel . '): ' . $this->truncate($text, 120);
        }

        if ($lines === []) {
            $identity = (string) ($conversation['customer_identity'] ?? 'Customer');
            return 'Active conversation with ' . $identity . ' — awaiting messages.';
        }

        return 'Live thread (' . count($lines) . ' recent): ' . implode(' | ', $lines);
    }

    /**
     * @param list<array<string, mixed>> $messages
     */
    public function summarizeFinal(array $messages, ?array $conversation = null): string
    {
        if ($messages === []) {
            return 'Call/conversation ended with no transcript messages recorded.';
        }

        $inbound = 0;
        $outbound = 0;
        $channels = [];
        $topics = [];

        foreach ($messages as $msg) {
            if (($msg['direction'] ?? '') === 'outbound') {
                $outbound++;
            } else {
                $inbound++;
            }
            $ch = (string) ($msg['channel'] ?? 'chat');
            $channels[$ch] = true;
            $text = trim((string) ($msg['message'] ?? ''));
            if ($text !== '') {
                $topics[] = $this->truncate($text, 80);
            }
        }

        $channelList = implode(', ', array_keys($channels));
        $identity = (string) ($conversation['customer_identity'] ?? 'customer');
        $topicSample = array_slice($topics, 0, 3);

        return sprintf(
            'Wrap-up for %s across [%s]: %d customer msgs, %d agent msgs. Key points: %s',
            $identity,
            $channelList !== '' ? $channelList : 'unknown',
            $inbound,
            $outbound,
            $topicSample !== [] ? implode('; ', $topicSample) : 'none captured'
        );
    }

    /** @return list<array<string, mixed>> */
    public function loadMessages(int $tenantId, int $conversationId): array
    {
        return ($this->messages ?? new ConversationMessageRepository())
            ->listByConversation($tenantId, $conversationId);
    }

    private function truncate(string $text, int $max): string
    {
        if (strlen($text) <= $max) {
            return $text;
        }
        return substr($text, 0, $max - 3) . '...';
    }
}

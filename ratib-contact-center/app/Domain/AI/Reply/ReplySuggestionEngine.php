<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\AI\Reply;

/**
 * Channel-aware reply suggestions (templates; advisory only).
 */
final class ReplySuggestionEngine
{
    /** @var array<string, mixed> */
    private array $config;

    /** @param array<string, mixed>|null $config */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? $this->loadConfig();
    }

    /**
     * @return array{suggested_reply:string,by_channel:array<string,string>,call_script:string|null}
     */
    public function suggest(string $intent, string $channel, ?array $conversation = null): array
    {
        $templates = $this->config['reply_templates'] ?? [];
        $intentKey = isset($templates[$intent]) ? $intent : 'default';
        $set = $templates[$intentKey] ?? $templates['default'] ?? [];

        $channel = $this->normalizeChannel($channel);
        $primary = (string) ($set[$channel] ?? $set['chat'] ?? 'Thank you for contacting us. How can I help?');

        $byChannel = [];
        foreach (['whatsapp', 'email', 'chat', 'voice'] as $ch) {
            if (isset($set[$ch])) {
                $byChannel[$ch] = (string) $set[$ch];
            }
        }

        $identity = (string) ($conversation['customer_identity'] ?? '');
        if ($identity !== '' && $channel === 'email') {
            $primary = str_replace('Dear Customer', 'Dear ' . $identity, $primary);
        }

        return [
            'suggested_reply' => $primary,
            'by_channel' => $byChannel,
            'call_script' => isset($set['voice']) ? (string) $set['voice'] : null,
        ];
    }

    private function normalizeChannel(string $channel): string
    {
        $channel = strtolower(trim($channel));
        if (in_array($channel, ['whatsapp', 'email', 'chat', 'voice'], true)) {
            return $channel;
        }
        if ($channel === 'social') {
            return 'chat';
        }
        return 'chat';
    }

    /** @return array<string, mixed> */
    private function loadConfig(): array
    {
        $path = dirname(__DIR__, 4) . '/config/assistant.php';
        return is_file($path) ? (require $path) : [];
    }
}

<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Omnichannel\Channels;

use Ratib\ContactCenter\App\Core\Database;

final class WhatsAppOutboundService
{
    /** @param array<string, mixed> $conversation */
    public function send(int $tenantId, int $conversationId, string $message, array $conversation): void
    {
        $config = (array) require dirname(__DIR__, 4) . '/config/omnichannel.php';
        $wa = $config['whatsapp'] ?? [];
        $token = (string) ($wa['access_token'] ?? '');
        $phoneId = (string) ($wa['phone_number_id'] ?? '');
        if ($token === '' || $phoneId === '') {
            throw new \RuntimeException('WhatsApp API not configured.');
        }

        $to = $this->resolvePhone($conversation);
        if ($to === '') {
            throw new \RuntimeException('WhatsApp recipient phone missing.');
        }

        $url = rtrim((string) $wa['api_url'], '/') . '/' . $phoneId . '/messages';
        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $message],
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code < 200 || $code >= 300) {
            $this->logOutbox($tenantId, $conversationId, 'whatsapp', 'failed', $response ?: 'HTTP ' . $code);
            throw new \RuntimeException('WhatsApp send failed: HTTP ' . $code);
        }

        $this->logOutbox($tenantId, $conversationId, 'whatsapp', 'sent', $response ?: '');
    }

    /** @param array<string, mixed> $conversation */
    private function resolvePhone(array $conversation): string
    {
        $identity = (string) ($conversation['customer_identity'] ?? '');
        $digits = preg_replace('/\D+/', '', $identity) ?? '';
        return $digits;
    }

    private function logOutbox(int $tenantId, int $conversationId, string $channel, string $status, string $detail): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO rcc_channel_outbox (tenant_id, conversation_id, channel, status, payload, error_message, sent_at)
             VALUES (:tid, :cid, :ch, :st, :payload, :err, :sent)'
        );
        $stmt->execute([
            'tid' => $tenantId,
            'cid' => $conversationId,
            'ch' => $channel,
            'st' => $status,
            'payload' => json_encode(['detail' => $detail], JSON_UNESCAPED_UNICODE),
            'err' => $status === 'failed' ? $detail : null,
            'sent' => $status === 'sent' ? gmdate('Y-m-d H:i:s') : null,
        ]);
    }
}

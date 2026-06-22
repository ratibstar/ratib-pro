<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Omnichannel\Channels;

use Ratib\ContactCenter\App\Core\Database;

final class EmailOutboundService
{
    /** @param array<string, mixed> $conversation */
    public function send(int $tenantId, int $conversationId, string $message, array $conversation): void
    {
        $config = (array) require dirname(__DIR__, 4) . '/config/omnichannel.php';
        $email = $config['email'] ?? [];
        $host = (string) ($email['smtp_host'] ?? '');
        if ($host === '') {
            throw new \RuntimeException('SMTP not configured.');
        }

        $to = $this->resolveEmail($conversation);
        if ($to === '') {
            throw new \RuntimeException('Email recipient missing.');
        }

        $from = (string) ($email['from_email'] ?? 'noreply@rateb.sa');
        $fromName = (string) ($email['from_name'] ?? 'RATIB');
        $subject = 'Re: Conversation #' . $conversationId;

        $sent = $this->smtpSend($email, $from, $fromName, $to, $subject, $message);
        $this->logOutbox($tenantId, $conversationId, 'email', $sent ? 'sent' : 'failed', $to);
        if (!$sent) {
            throw new \RuntimeException('SMTP send failed.');
        }
    }

    /** @param array<string, mixed> $conversation */
    private function resolveEmail(array $conversation): string
    {
        $meta = is_array($conversation['metadata'] ?? null) ? $conversation['metadata'] : [];
        $identity = $meta['identity'] ?? [];
        if (is_array($identity) && !empty($identity['identity']) && str_contains((string) $identity['identity'], '@')) {
            return (string) $identity['identity'];
        }
        $ident = (string) ($conversation['customer_identity'] ?? '');
        return str_contains($ident, '@') ? $ident : '';
    }

    /** @param array<string, mixed> $cfg */
    private function smtpSend(array $cfg, string $from, string $fromName, string $to, string $subject, string $body): bool
    {
        $host = (string) $cfg['smtp_host'];
        $port = (int) ($cfg['smtp_port'] ?? 587);
        $user = (string) ($cfg['smtp_user'] ?? '');
        $pass = (string) ($cfg['smtp_pass'] ?? '');

        $headers = [
            'From: ' . $fromName . ' <' . $from . '>',
            'To: ' . $to,
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];
        $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;

        $socket = @stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, 15);
        if ($socket === false) {
            error_log('[RCC Email] connect failed: ' . $errstr);
            return false;
        }

        $read = static function () use ($socket): string {
            $data = '';
            while ($line = fgets($socket, 512)) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };

        $write = static function (string $cmd) use ($socket): void {
            fwrite($socket, $cmd . "\r\n");
        };

        $read();
        $write('EHLO rateb.sa');
        $read();

        if ($user !== '' && $pass !== '') {
            $write('AUTH LOGIN');
            $read();
            $write(base64_encode($user));
            $read();
            $write(base64_encode($pass));
            $auth = $read();
            if (strpos($auth, '235') === false) {
                fclose($socket);
                return false;
            }
        }

        $write('MAIL FROM:<' . $from . '>');
        $read();
        $write('RCPT TO:<' . $to . '>');
        $read();
        $write('DATA');
        $read();
        $write($message . "\r\n.");
        $result = $read();
        $write('QUIT');
        fclose($socket);

        return strpos($result, '250') !== false;
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
            'payload' => json_encode(['to' => $detail], JSON_UNESCAPED_UNICODE),
            'err' => $status === 'failed' ? $detail : null,
            'sent' => $status === 'sent' ? gmdate('Y-m-d H:i:s') : null,
        ]);
    }
}

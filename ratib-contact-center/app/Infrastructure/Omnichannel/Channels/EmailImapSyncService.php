<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Omnichannel\Channels;

use Ratib\ContactCenter\App\Infrastructure\Channels\EmailChannelAdapter;
use Ratib\ContactCenter\App\Domain\Conversation\ConversationEngine;

/**
 * IMAP inbox sync — polls mailbox and ingests into ConversationEngine.
 */
final class EmailImapSyncService
{
    public function syncTenant(int $tenantId, int $limit = 20): int
    {
        $config = (array) require dirname(__DIR__, 4) . '/config/omnichannel.php';
        $email = $config['email'] ?? [];
        $host = (string) ($email['imap_host'] ?? '');
        $user = (string) ($email['imap_user'] ?? '');
        $pass = (string) ($email['imap_pass'] ?? '');
        if ($host === '' || $user === '' || $pass === '') {
            return 0;
        }

        $mailbox = '{' . $host . ':' . (int) ($email['imap_port'] ?? 993) . '/imap/ssl}'
            . (string) ($email['imap_mailbox'] ?? 'INBOX');
        $inbox = @imap_open($mailbox, $user, $pass);
        if ($inbox === false) {
            error_log('[RCC IMAP] open failed: ' . imap_last_error());
            return 0;
        }

        $uids = imap_search($inbox, 'UNSEEN') ?: [];
        $uids = array_slice($uids, 0, $limit);
        $engine = new ConversationEngine();
        $adapter = new EmailChannelAdapter($engine);
        $count = 0;

        foreach ($uids as $uid) {
            $header = imap_headerinfo($inbox, $uid);
            $body = imap_fetchbody($inbox, $uid, '1') ?: '';
            $from = $header->from[0] ?? null;
            $fromEmail = $from ? ($from->mailbox . '@' . $from->host) : '';
            $adapter->ingest($tenantId, [
                'from' => $fromEmail,
                'subject' => $header->subject ?? '',
                'message' => $body,
                'message_id' => $header->message_id ?? null,
            ]);
            imap_setflag_full($inbox, (string) $uid, '\\Seen');
            $count++;
        }

        imap_close($inbox);
        return $count;
    }
}

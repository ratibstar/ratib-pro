<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Converts eligible NotificationDecision values into history rows only.
 *
 * Never sends email/push/SMS/WhatsApp, never updates rateb_subscription_engine,
 * never touches UI. Deduplication is enforced by NotificationHistoryRepository.
 */
final class NotificationGenerator
{
    private NotificationHistoryStore $history;

    public function __construct(?NotificationHistoryStore $history = null)
    {
        $this->history = $history ?? new NotificationHistoryRepository();
    }

    /**
     * Persist one eligible decision. Returns inserted id, or 0 if skipped/duplicate.
     */
    public function generate(NotificationDecision $decision): int
    {
        if (!$decision->shouldGenerate()) {
            return 0;
        }

        return $this->history->recordGenerated($decision);
    }

    /**
     * @param list<NotificationDecision> $decisions
     * @return array{attempted:int, inserted:int, skipped:int, ids:list<int>}
     */
    public function generateMany(array $decisions): array
    {
        $attempted = 0;
        $inserted = 0;
        $skipped = 0;
        $ids = [];

        foreach ($decisions as $decision) {
            if (!$decision instanceof NotificationDecision) {
                continue;
            }
            $attempted++;
            if (!$decision->shouldGenerate()) {
                $skipped++;
                continue;
            }
            $id = $this->generate($decision);
            if ($id > 0) {
                $inserted++;
                $ids[] = $id;
            } else {
                $skipped++;
            }
        }

        return [
            'attempted' => $attempted,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'ids' => $ids,
        ];
    }
}

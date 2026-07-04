<?php
declare(strict_types=1);

namespace App\Accounting\Core;

use App\Accounting\Contracts\AccountingAdapterInterface;

/**
 * Single entry point for all accounting write events.
 * Validates and routes to the correct adapter — never touches SQL or DB directly.
 */
final class AccountingGateway
{
    /** @var list<AccountingAdapterInterface> */
    private array $adapters;

    private AccountingEventValidator $validator;

    /**
     * @param list<AccountingAdapterInterface> $adapters
     */
    public function __construct(array $adapters, ?AccountingEventValidator $validator = null)
    {
        $this->adapters = $adapters;
        $this->validator = $validator ?? new AccountingEventValidator();
    }

    /**
     * @param array<string, mixed> $event
     */
    public function post(array $event): AccountingResult
    {
        $check = $this->validator->validate($event);
        if (!$check['valid']) {
            return AccountingResult::fail(
                'Invalid accounting event',
                ['errors' => $check['errors']]
            );
        }

        $sourceSystem = (string) $event['source_system'];
        $adapter = $this->resolveAdapter($sourceSystem);
        if ($adapter === null) {
            return AccountingResult::fail(
                'No adapter registered for source_system: ' . $sourceSystem
            );
        }

        try {
            return $adapter->post($event);
        } catch (\Throwable $e) {
            error_log('AccountingGateway::post failed: ' . $e->getMessage());

            return AccountingResult::fail(
                'Adapter error: ' . $e->getMessage(),
                ['source_system' => $sourceSystem]
            );
        }
    }

    private function resolveAdapter(string $sourceSystem): ?AccountingAdapterInterface
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($sourceSystem)) {
                return $adapter;
            }
        }

        return null;
    }
}

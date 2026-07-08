<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Queue\Handlers;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\DuplicateReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\DuplicateWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Contracts\JobHandlerInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Job;

final class DuplicateScanJobHandler implements JobHandlerInterface
{
    public function __construct(
        private readonly DuplicateReadRepositoryInterface $duplicateReadRepository,
        private readonly DuplicateWriteRepositoryInterface $duplicateWriteRepository
    ) {
    }

    public function supports(string $jobType): bool
    {
        return $jobType === 'duplicate_scan';
    }

    public function handle(Job $job): void
    {
        $ruleCode = isset($job->payload['rule_code']) ? (string) $job->payload['rule_code'] : null;
        $rules = $this->duplicateReadRepository->listRules();
        $activeRules = array_values(array_filter(
            $rules,
            static fn (array $rule): bool => (bool) ($rule['is_active'] ?? false)
                && ($ruleCode === null || $ruleCode === '' || (string) ($rule['code'] ?? '') === $ruleCode)
        ));

        foreach ($activeRules as $rule) {
            $matchField = (string) ($rule['match_field'] ?? 'sku');
            $ruleId = isset($rule['id']) ? (int) $rule['id'] : null;

            if ($matchField === 'barcode') {
                $this->scanBarcodeGroups($ruleId);
            } else {
                $this->scanSkuGroups($ruleId);
            }
        }
    }

    private function scanSkuGroups(?int $ruleId): void
    {
        foreach ($this->duplicateReadRepository->findSkuCollisionGroups() as $group) {
            $groupUuid = $this->duplicateWriteRepository->createGroup('sku:' . $group['sku'], $ruleId);
            $isPrimary = true;
            foreach ($group['product_ids'] as $productId) {
                $this->duplicateWriteRepository->attachProduct($groupUuid, $productId, 1.0, $isPrimary);
                $isPrimary = false;
            }
        }
    }

    private function scanBarcodeGroups(?int $ruleId): void
    {
        foreach ($this->duplicateReadRepository->findBarcodeCollisionGroups() as $group) {
            $groupUuid = $this->duplicateWriteRepository->createGroup('barcode:' . $group['barcode'], $ruleId);
            $isPrimary = true;
            foreach ($group['product_ids'] as $productId) {
                $this->duplicateWriteRepository->attachProduct($groupUuid, $productId, 1.0, $isPrimary);
                $isPrimary = false;
            }
        }
    }
}

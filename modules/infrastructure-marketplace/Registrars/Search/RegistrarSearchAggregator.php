<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Registrars\Search;

final class RegistrarSearchAggregator
{
    /**
     * @param list<string> $tlds
     * @return list<array<string, mixed>>
     */
    public function searchAsyncPrepared(string $keyword, array $tlds): array
    {
        $keyword = strtolower(trim($keyword));
        $out = [];
        foreach ($tlds as $tld) {
            $out[] = [
                'fqdn' => $keyword . '.' . ltrim(strtolower($tld), '.'),
                'available' => null,
                'status' => 'pending_provider_query',
                'premium' => null,
                'cached' => false,
            ];
        }
        return $out;
    }
}


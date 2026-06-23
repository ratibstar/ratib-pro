<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Contracts\Payment;

/** @return array{ok:bool,redirect_url?:string,external_id?:string,error?:string,raw?:array} */
interface PaymentGatewayDriverInterface
{
    public function slug(): string;

    /** @param array<string, mixed> $credentials @param array<string, mixed> $charge */
    public function createCharge(array $credentials, array $charge): array;

    /** @param array<string, mixed> $credentials */
    public function verifyCharge(array $credentials, string $externalId): array;
}

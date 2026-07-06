<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Application\V2;

use Rateb\App\Pos\Application\V2\Http\PosV2JsonResponse;
use Rateb\App\Pos\DTO\V2\Bootstrap\PosV2BootstrapMeta;
use Rateb\App\Pos\DTO\V2\Catalog\CatalogProductResponse;
use Rateb\App\Pos\DTO\V2\Catalog\CatalogSearchResponse;
use Rateb\App\Pos\DTO\V2\Cart\CartResponse;
use Rateb\App\Pos\DTO\V2\Customer\CustomerSearchResponse;
use Rateb\App\Pos\DTO\V2\Customer\PosV2CustomerSummaryDto;
use Rateb\App\Pos\DTO\V2\Payment\PaymentSummaryDto;
use Rateb\App\Pos\DTO\V2\Register\RegisterBootstrapResponse;

/** Factory for OpenAPI-aligned JSON responses. */
final class PosV2ResponseFactory
{
    /**
     * @param array<string, mixed> $data
     */
    public function success(array $data, int $statusCode = 200): PosV2JsonResponse
    {
        return new PosV2JsonResponse($statusCode, $data);
    }

    public function bootstrapSuccess(
        RegisterBootstrapResponse $data,
        PosV2BootstrapMeta $meta,
        int $statusCode = 200,
    ): PosV2JsonResponse {
        return new PosV2JsonResponse($statusCode, [
            'success' => true,
            'data' => $data->toArray(),
            'meta' => $meta->toArray(),
        ]);
    }

    public function bootstrapError(
        string $code,
        string $message,
        int $statusCode = 422,
    ): PosV2JsonResponse {
        return new PosV2JsonResponse($statusCode, [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $details
     */
    public function error(
        string $code,
        string $message,
        int $statusCode = 422,
        ?string $field = null,
        array $details = [],
    ): PosV2JsonResponse {
        $error = [
            'code' => $code,
            'message' => $message,
            'field' => $field,
            'details' => $details,
        ];

        return new PosV2JsonResponse($statusCode, ['error' => $error]);
    }

    public function catalogSuccess(CatalogSearchResponse $data, int $statusCode = 200): PosV2JsonResponse
    {
        return new PosV2JsonResponse($statusCode, [
            'success' => true,
            'data' => $data->toArray(),
        ]);
    }

    public function catalogProductSuccess(CatalogProductResponse $data, int $statusCode = 200): PosV2JsonResponse
    {
        return new PosV2JsonResponse($statusCode, [
            'success' => true,
            'data' => $data->toArray(),
        ]);
    }

    public function catalogError(
        string $code,
        string $message,
        int $statusCode = 422,
    ): PosV2JsonResponse {
        return new PosV2JsonResponse($statusCode, [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ]);
    }

    public function cartSuccess(CartResponse $data, int $statusCode = 200): PosV2JsonResponse
    {
        return new PosV2JsonResponse($statusCode, [
            'success' => true,
            'data' => $data->toArray(),
        ]);
    }

    public function cartError(
        string $code,
        string $message,
        int $statusCode = 422,
    ): PosV2JsonResponse {
        return new PosV2JsonResponse($statusCode, [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ]);
    }

    public function customerSearchSuccess(CustomerSearchResponse $data, int $statusCode = 200): PosV2JsonResponse
    {
        return new PosV2JsonResponse($statusCode, [
            'success' => true,
            'data' => $data->toArray(),
        ]);
    }

    public function customerDetailSuccess(PosV2CustomerSummaryDto $data, int $statusCode = 200): PosV2JsonResponse
    {
        return new PosV2JsonResponse($statusCode, [
            'success' => true,
            'data' => $data->toArray(),
        ]);
    }

    public function customerError(
        string $code,
        string $message,
        int $statusCode = 422,
    ): PosV2JsonResponse {
        return new PosV2JsonResponse($statusCode, [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ]);
    }

    public function discountError(
        string $code,
        string $message,
        int $statusCode = 422,
    ): PosV2JsonResponse {
        return new PosV2JsonResponse($statusCode, [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ]);
    }

    public function paymentSummarySuccess(PaymentSummaryDto $data, int $statusCode = 200): PosV2JsonResponse
    {
        return new PosV2JsonResponse($statusCode, [
            'success' => true,
            'data' => $data->toArray(),
        ]);
    }

    public function paymentError(
        string $code,
        string $message,
        int $statusCode = 422,
    ): PosV2JsonResponse {
        return new PosV2JsonResponse($statusCode, [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ]);
    }
}

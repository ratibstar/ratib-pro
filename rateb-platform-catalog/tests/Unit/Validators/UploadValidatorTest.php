<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Validators\UploadValidator;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\AssetTypeReadRepositoryInterface;

catalog_test('UploadValidator rejects empty uploads', static function (): void {
    $validator = new UploadValidator(new class implements AssetTypeReadRepositoryInterface {
        public function findByUuid(string $uuid, LocaleContext $locale): ?array
        {
            return null;
        }

        public function findByCode(string $code, LocaleContext $locale): ?array
        {
            return null;
        }

        public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
        {
            return [];
        }
    });

    try {
        $validator->validateUpload([
            'content' => '',
            'mime_type' => 'image/png',
            'size' => 0,
            'extension' => 'png',
        ], 'image_original', new LocaleContext('en', 'ar'), true);
        throw new RuntimeException('Expected validation failure');
    } catch (InvalidArgumentException $e) {
        catalog_assert_same('Upload is empty', $e->getMessage());
    }
});

catalog_test('UploadValidator rejects executable extensions', static function (): void {
    $validator = new UploadValidator(new class implements AssetTypeReadRepositoryInterface {
        public function findByUuid(string $uuid, LocaleContext $locale): ?array
        {
            return null;
        }

        public function findByCode(string $code, LocaleContext $locale): ?array
        {
            return null;
        }

        public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
        {
            return [];
        }
    });

    try {
        $validator->validateUpload([
            'content' => 'MZ',
            'mime_type' => 'application/octet-stream',
            'size' => 2,
            'extension' => 'exe',
        ], 'image_original', new LocaleContext('en', 'ar'));
        throw new RuntimeException('Expected validation failure');
    } catch (InvalidArgumentException $e) {
        catalog_assert_same('Executable file types are not allowed', $e->getMessage());
    }
});

catalog_test('UploadValidator validates image MIME against asset type metadata', static function (): void {
    $locale = new LocaleContext('en', 'ar');
    $validator = new UploadValidator(new class implements AssetTypeReadRepositoryInterface {
        public function findByUuid(string $uuid, LocaleContext $locale): ?array
        {
            return null;
        }

        public function findByCode(string $code, LocaleContext $locale): ?array
        {
            return [
                'code' => 'image_original',
                'category' => 'image',
                'status' => 'active',
                'mime_patterns' => json_encode(['image/png']),
                'extension_patterns' => json_encode(['png']),
            ];
        }

        public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
        {
            return [];
        }
    });

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
    $validator->validateUpload([
        'content' => (string) $png,
        'mime_type' => 'image/png',
        'size' => strlen((string) $png),
        'extension' => 'png',
    ], 'image_original', $locale, true);

    try {
        $validator->validateUpload([
            'content' => (string) $png,
            'mime_type' => 'application/pdf',
            'size' => strlen((string) $png),
            'extension' => 'png',
        ], 'image_original', $locale, true);
        throw new RuntimeException('Expected MIME rejection');
    } catch (InvalidArgumentException $e) {
        catalog_assert_same('MIME type is not allowed for this asset type', $e->getMessage());
    }
});

catalog_test('UploadValidator rejects invalid base64 payloads', static function (): void {
    $locale = new LocaleContext('en', 'ar');
    $validator = new UploadValidator(new class implements AssetTypeReadRepositoryInterface {
        public function findByUuid(string $uuid, LocaleContext $locale): ?array
        {
            return null;
        }

        public function findByCode(string $code, LocaleContext $locale): ?array
        {
            return [
                'code' => 'pdf',
                'category' => 'document',
                'status' => 'active',
                'mime_patterns' => null,
                'extension_patterns' => null,
            ];
        }

        public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
        {
            return [];
        }
    });

    try {
        $validator->resolveAndValidate([
            'content_base64' => '%%%not-base64%%%',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
        ], null, 'pdf', $locale);
        throw new RuntimeException('Expected base64 rejection');
    } catch (InvalidArgumentException $e) {
        catalog_assert_same('Invalid base64 content', $e->getMessage());
    }
});

catalog_test('UploadValidator rejects uploads exceeding max size', static function (): void {
    $locale = new LocaleContext('en', 'ar');
    $validator = new UploadValidator(new class implements AssetTypeReadRepositoryInterface {
        public function findByUuid(string $uuid, LocaleContext $locale): ?array
        {
            return null;
        }

        public function findByCode(string $code, LocaleContext $locale): ?array
        {
            return [
                'code' => 'pdf',
                'category' => 'document',
                'status' => 'active',
                'mime_patterns' => null,
                'extension_patterns' => null,
            ];
        }

        public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
        {
            return [];
        }
    });

    try {
        $validator->validateUpload([
            'content' => str_repeat('a', 1024),
            'mime_type' => 'application/pdf',
            'size' => 60 * 1024 * 1024,
            'extension' => 'pdf',
        ], 'pdf', $locale);
        throw new RuntimeException('Expected size rejection');
    } catch (InvalidArgumentException $e) {
        catalog_assert_same('Upload exceeds maximum allowed size', $e->getMessage());
    }
});

catalog_test('UploadValidator rejects images exceeding dimensions', static function (): void {
    putenv('CATALOG_UPLOAD_IMAGE_MAX_WIDTH=1');
    putenv('CATALOG_UPLOAD_IMAGE_MAX_HEIGHT=1');

    $locale = new LocaleContext('en', 'ar');
    $validator = new UploadValidator(new class implements AssetTypeReadRepositoryInterface {
        public function findByUuid(string $uuid, LocaleContext $locale): ?array
        {
            return null;
        }

        public function findByCode(string $code, LocaleContext $locale): ?array
        {
            return [
                'code' => 'image_original',
                'category' => 'image',
                'status' => 'active',
                'mime_patterns' => json_encode(['image/png']),
                'extension_patterns' => json_encode(['png']),
            ];
        }

        public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
        {
            return [];
        }
    });

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABr19OHAAAADklEQVR42mP8z4ABgAMAAp9jZ8kAAAAASUVORK5CYII=', true);
    try {
        $validator->validateUpload([
            'content' => (string) $png,
            'mime_type' => 'image/png',
            'size' => strlen((string) $png),
            'extension' => 'png',
        ], 'image_original', $locale, true);
        throw new RuntimeException('Expected dimension rejection');
    } catch (InvalidArgumentException $e) {
        catalog_assert_same('Image dimensions exceed maximum', $e->getMessage());
    }

    putenv('CATALOG_UPLOAD_IMAGE_MAX_WIDTH');
    putenv('CATALOG_UPLOAD_IMAGE_MAX_HEIGHT');
});

catalog_test('UploadValidator rejects jar executable extension', static function (): void {
    $validator = new UploadValidator(new class implements AssetTypeReadRepositoryInterface {
        public function findByUuid(string $uuid, LocaleContext $locale): ?array
        {
            return null;
        }

        public function findByCode(string $code, LocaleContext $locale): ?array
        {
            return null;
        }

        public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
        {
            return [];
        }
    });

    try {
        $validator->validateUpload([
            'content' => 'PK',
            'mime_type' => 'application/octet-stream',
            'size' => 2,
            'extension' => 'jar',
        ], 'image_original', new LocaleContext('en', 'ar'));
        throw new RuntimeException('Expected jar rejection');
    } catch (InvalidArgumentException $e) {
        catalog_assert_same('Executable file types are not allowed', $e->getMessage());
    }
});

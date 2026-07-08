<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Validators;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Support\MediaUploadHelper;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\AssetTypeReadRepositoryInterface;

final class UploadValidator
{
    /** @var list<string> */
    private const FORBIDDEN_EXTENSIONS = [
        'exe', 'bat', 'cmd', 'com', 'scr', 'pif', 'sh', 'bash', 'php', 'phar', 'phtml',
        'js', 'vbs', 'ps1', 'msi', 'dll', 'app', 'deb', 'rpm', 'cgi', 'pl', 'asp', 'aspx', 'jsp',
        'jar', 'war', 'ear', 'py', 'pyc', 'pyw', 'rb', 'wsf', 'hta', 'gadget', 'msp', 'mst',
        'reg', 'inf', 'scf', 'lnk', 'so', 'dylib', 'wasm', 'apk', 'dmg', 'pkg', 'run',
    ];

    /** @var list<string> */
    private const FORBIDDEN_MIME_PREFIXES = [
        'application/x-msdownload',
        'application/x-msdos-program',
        'application/x-executable',
        'application/x-php',
        'application/x-httpd-php',
        'text/x-php',
        'application/javascript',
        'text/javascript',
    ];

    /** @var array<string, array{mime:list<string>,ext:list<string>}> */
    private const CATEGORY_DEFAULTS = [
        'image' => [
            'mime' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'],
            'ext' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'],
        ],
        'document' => [
            'mime' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'ext' => ['pdf', 'doc', 'docx'],
        ],
        'archive' => [
            'mime' => ['application/zip', 'application/x-zip-compressed'],
            'ext' => ['zip'],
        ],
        'firmware' => [
            'mime' => ['application/octet-stream', 'application/x-binary'],
            'ext' => ['bin', 'hex', 'fw'],
        ],
        'model_3d' => [
            'mime' => ['model/gltf+json', 'model/gltf-binary', 'application/octet-stream'],
            'ext' => ['gltf', 'glb', 'obj', 'stl'],
        ],
        'video' => [
            'mime' => ['video/mp4', 'video/webm', 'video/quicktime'],
            'ext' => ['mp4', 'webm', 'mov'],
        ],
        'other' => [
            'mime' => ['application/octet-stream'],
            'ext' => ['bin', 'dat'],
        ],
    ];

    /** @var array<string, mixed> */
    private readonly array $config;

    public function __construct(
        private readonly AssetTypeReadRepositoryInterface $assetTypeReadRepository
    ) {
        $path = defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT . '/config/upload.php' : dirname(__DIR__, 3) . '/config/upload.php';
        $config = is_file($path) ? require $path : [];

        $this->config = is_array($config) ? $config : [];
    }

    /**
     * @param array{content:string,mime_type:string,size:int,extension:string} $binary
     */
    public function validateUpload(
        array $binary,
        string $assetTypeCode,
        LocaleContext $locale,
        bool $requireImageDimensions = false
    ): void {
        if ($binary['content'] === '' || $binary['size'] <= 0) {
            throw new \InvalidArgumentException('Upload is empty');
        }

        $extension = strtolower(trim($binary['extension']));
        $mimeType = strtolower(trim($binary['mime_type']));

        $this->assertNotForbidden($extension, $mimeType);

        $assetType = $this->assetTypeReadRepository->findByCode($assetTypeCode, $locale);
        if ($assetType === null) {
            throw new \InvalidArgumentException('Unknown asset type');
        }

        if (($assetType['status'] ?? 'inactive') !== 'active') {
            throw new \InvalidArgumentException('Asset type is not active');
        }

        $category = (string) ($assetType['category'] ?? 'other');
        $allowedMimes = $this->resolvePatterns($assetType['mime_patterns'] ?? null, $category, 'mime');
        $allowedExtensions = $this->resolvePatterns($assetType['extension_patterns'] ?? null, $category, 'ext');

        if (!$this->matchesPattern($mimeType, $allowedMimes)) {
            throw new \InvalidArgumentException('MIME type is not allowed for this asset type');
        }

        if (!$this->matchesPattern($extension, $allowedExtensions)) {
            throw new \InvalidArgumentException('File extension is not allowed for this asset type');
        }

        $maxBytes = $this->maxBytesForCategory($category);
        if ($binary['size'] > $maxBytes) {
            throw new \InvalidArgumentException('Upload exceeds maximum allowed size');
        }

        if ($requireImageDimensions || $category === 'image') {
            $this->validateImageDimensions($binary['content']);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int}|null $uploadedFile
     * @return array{content:string,mime_type:string,size:int,extension:string}
     */
    public function resolveAndValidate(
        array $payload,
        ?array $uploadedFile,
        string $assetTypeCode,
        LocaleContext $locale,
        bool $requireImageDimensions = false
    ): array {
        if ($uploadedFile !== null && (int) ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $uploadedFile = null;
        }

        if ($uploadedFile !== null && (int) ($uploadedFile['size'] ?? 0) === 0) {
            throw new \InvalidArgumentException('Upload is empty');
        }

        if ($uploadedFile === null && isset($payload['content_base64']) && $payload['content_base64'] === '') {
            throw new \InvalidArgumentException('Upload is empty');
        }

        $binary = MediaUploadHelper::resolveBinary($payload, $uploadedFile);
        $this->validateUpload($binary, $assetTypeCode, $locale, $requireImageDimensions);

        return $binary;
    }

    private function assertNotForbidden(string $extension, string $mimeType): void
    {
        $forbiddenExtensions = $this->forbiddenExtensions();
        if (in_array($extension, $forbiddenExtensions, true)) {
            throw new \InvalidArgumentException('Executable file types are not allowed');
        }

        foreach (self::FORBIDDEN_MIME_PREFIXES as $forbidden) {
            if ($mimeType === $forbidden || str_starts_with($mimeType, $forbidden)) {
                throw new \InvalidArgumentException('Executable file types are not allowed');
            }
        }
    }

    /**
     * @return list<string>
     */
    private function forbiddenExtensions(): array
    {
        $configured = $this->config['forbidden_extensions'] ?? [];
        if (!is_array($configured) || $configured === []) {
            return self::FORBIDDEN_EXTENSIONS;
        }

        $merged = array_merge(self::FORBIDDEN_EXTENSIONS, $this->normalizeList($configured));

        return array_values(array_unique($merged));
    }

    /**
     * @return list<string>
     */
    private function resolvePatterns(mixed $stored, string $category, string $kind): array
    {
        $decoded = $this->decodeJsonList($stored);
        if ($decoded !== []) {
            return $decoded;
        }

        return self::CATEGORY_DEFAULTS[$category][$kind] ?? self::CATEGORY_DEFAULTS['other'][$kind];
    }

    /**
     * @return list<string>
     */
    private function decodeJsonList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $this->normalizeList($decoded) : [];
        }

        return is_array($value) ? $this->normalizeList($value) : [];
    }

    /**
     * @param list<mixed> $items
     * @return list<string>
     */
    private function normalizeList(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            if (!is_string($item) || $item === '') {
                continue;
            }
            $normalized[] = strtolower($item);
        }

        return $normalized;
    }

    /**
     * @param list<string> $patterns
     */
    private function matchesPattern(string $value, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === $value) {
                return true;
            }
            if (str_contains($pattern, '*') && fnmatch($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    private function maxBytesForCategory(string $category): int
    {
        $map = $this->config['max_bytes_by_category'] ?? [];

        return (int) ($map[$category] ?? $map['other'] ?? (25 * 1024 * 1024));
    }

    private function validateImageDimensions(string $content): void
    {
        $dimensions = MediaUploadHelper::imageDimensions($content);
        if ($dimensions['width'] === null || $dimensions['height'] === null) {
            throw new \InvalidArgumentException('Invalid image dimensions');
        }

        $minWidth = (int) ($this->config['image_min_width'] ?? 1);
        $minHeight = (int) ($this->config['image_min_height'] ?? 1);
        $maxWidth = (int) ($this->config['image_max_width'] ?? 10000);
        $maxHeight = (int) ($this->config['image_max_height'] ?? 10000);

        $width = (int) $dimensions['width'];
        $height = (int) $dimensions['height'];

        if ($width < $minWidth || $height < $minHeight) {
            throw new \InvalidArgumentException('Image dimensions are below minimum');
        }

        if ($width > $maxWidth || $height > $maxHeight) {
            throw new \InvalidArgumentException('Image dimensions exceed maximum');
        }
    }
}

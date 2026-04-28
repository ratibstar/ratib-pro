<?php
declare(strict_types=1);

/**
 * CoreAI Project Intelligence Graph Builder
 * مُنشئ مخطط ذكاء المشروع لـ CoreAI
 *
 * Scans all files inside /coreai and generates:
 * - import relationships
 * - function definitions + usage
 * - API connections
 *
 * Output file: /coreai/project_graph.json
 */

$coreaiRoot = realpath(dirname(__DIR__));
if ($coreaiRoot === false) {
    fwrite(STDERR, "Failed to resolve coreai root.\n");
    exit(1);
}

$outputFile = $coreaiRoot . DIRECTORY_SEPARATOR . 'project_graph.json';

/**
 * Convert absolute file path to project-relative path.
 * تحويل المسار الكامل إلى مسار نسبي داخل المشروع.
 */
function rel_path(string $absolutePath, string $root): string
{
    $normalized = str_replace('\\', '/', $absolutePath);
    $normalizedRoot = str_replace('\\', '/', $root);
    if (str_starts_with($normalized, $normalizedRoot . '/')) {
        return substr($normalized, strlen($normalizedRoot) + 1);
    }
    if ($normalized === $normalizedRoot) {
        return '.';
    }
    return $normalized;
}

/**
 * Identify file language by extension.
 * تحديد نوع الملف حسب الامتداد.
 */
function detect_language(string $path): string
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'php':
            return 'php';
        case 'js':
            return 'javascript';
        case 'css':
            return 'css';
        case 'html':
        case 'htm':
            return 'html';
        case 'json':
            return 'json';
        case 'md':
            return 'markdown';
        default:
            return 'other';
    }
}

/**
 * Collect import-like references from file content.
 * جمع مراجع الاستيراد والربط بين الملفات.
 *
 * @return array<int, string>
 */
function extract_imports(string $content): array
{
    $imports = [];
    $patterns = [
        '/\b(?:require|require_once|include|include_once)\s*\(?\s*[\'"]([^\'"]+)[\'"]\s*\)?/i',
        '/\bimport\s+(?:.+?\s+from\s+)?[\'"]([^\'"]+)[\'"]/i',
        '/\bimport\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/i',
        '/<script[^>]+src=[\'"]([^\'"]+)[\'"]/i',
        '/@import\s+(?:url\()?["\']?([^"\')\s]+)["\']?\)?/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $content, $matches) === 1 || (is_array($matches[1] ?? null) && $matches[1] !== [])) {
            foreach (($matches[1] ?? []) as $match) {
                $imports[] = trim((string)$match);
            }
        } elseif (preg_match_all($pattern, $content, $matches) > 0) {
            foreach (($matches[1] ?? []) as $match) {
                $imports[] = trim((string)$match);
            }
        }
    }

    return array_values(array_unique(array_filter($imports, static fn(string $v): bool => $v !== '')));
}

/**
 * Extract function definitions from PHP/JS.
 * استخراج تعريفات الدوال من PHP و JavaScript.
 *
 * @return array<int, string>
 */
function extract_function_definitions(string $content, string $language): array
{
    $defs = [];

    if ($language === 'php') {
        if (preg_match_all('/\bfunction\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $content, $matches) > 0) {
            $defs = array_merge($defs, $matches[1]);
        }
    }

    if ($language === 'javascript') {
        if (preg_match_all('/\bfunction\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $content, $matches) > 0) {
            $defs = array_merge($defs, $matches[1]);
        }
        if (preg_match_all('/\b(?:const|let|var)\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(?:async\s*)?\([^)]*\)\s*=>/', $content, $matches2) > 0) {
            $defs = array_merge($defs, $matches2[1]);
        }
    }

    return array_values(array_unique($defs));
}

/**
 * Extract function-like calls.
 * استخراج استدعاءات الدوال بشكل تقريبي.
 *
 * @return array<int, string>
 */
function extract_function_calls(string $content): array
{
    $calls = [];
    if (preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $content, $matches) > 0) {
        $ignore = [
            'if', 'for', 'while', 'switch', 'catch', 'function', 'echo', 'isset', 'empty',
            'return', 'include', 'require', 'array', 'list', 'new',
        ];
        $ignoreMap = array_fill_keys($ignore, true);
        foreach ($matches[1] as $name) {
            $lower = strtolower($name);
            if (!isset($ignoreMap[$lower])) {
                $calls[] = $name;
            }
        }
    }
    return array_values(array_unique($calls));
}

/**
 * Extract API connections (HTTP URLs or local API endpoints).
 * استخراج اتصالات الـ API (روابط HTTP أو نقاط ربط محلية).
 *
 * @return array<int, string>
 */
function extract_api_connections(string $content): array
{
    $connections = [];
    $patterns = [
        '/\bfetch\s*\(\s*[\'"]([^\'"]+)[\'"]/',
        '/\baxios\.(?:get|post|put|delete|patch)\s*\(\s*[\'"]([^\'"]+)[\'"]/',
        '/\bcurl_init\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
        '/\bhttps?:\/\/[^\s\'"]+/',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $content, $matches) > 0) {
            if (isset($matches[1])) {
                foreach ($matches[1] as $m) {
                    $connections[] = trim((string)$m);
                }
            } elseif (isset($matches[0])) {
                foreach ($matches[0] as $m) {
                    $connections[] = trim((string)$m);
                }
            }
        }
    }

    return array_values(array_unique(array_filter($connections, static fn(string $v): bool => $v !== '')));
}

/**
 * Resolve local import path to an actual file.
 * حل مسار الاستيراد المحلي إلى ملف فعلي.
 */
function resolve_local_import(string $importPath, string $sourceFile, string $root): ?string
{
    $normalized = str_replace('\\', '/', trim($importPath));
    if ($normalized === '' || preg_match('/^[a-zA-Z]+:\/\//', $normalized) === 1) {
        return null;
    }

    $sourceDir = dirname($sourceFile);
    $candidates = [];

    if (str_starts_with($normalized, './') || str_starts_with($normalized, '../')) {
        $candidates[] = $sourceDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    } else {
        $candidates[] = $root . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $normalized), DIRECTORY_SEPARATOR);
        $candidates[] = $sourceDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    }

    $extensions = ['', '.php', '.js', '.css', '.json', '.md'];
    foreach ($candidates as $candidate) {
        foreach ($extensions as $ext) {
            $path = $candidate . $ext;
            $real = realpath($path);
            if ($real !== false && is_file($real)) {
                $normRoot = str_replace('\\', '/', $root);
                $normReal = str_replace('\\', '/', $real);
                if (str_starts_with($normReal, $normRoot . '/')) {
                    return rel_path($real, $root);
                }
            }
        }
    }

    return null;
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($coreaiRoot, FilesystemIterator::SKIP_DOTS)
);

$files = [];
foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }

    $absolutePath = $fileInfo->getPathname();
    $relativePath = rel_path($absolutePath, $coreaiRoot);

    // Skip generated logs and graph output itself.
    // تخطي السجلات الناتجة وملف المخطط نفسه.
    if ($relativePath === 'project_graph.json' || str_starts_with($relativePath, 'context/logs/')) {
        continue;
    }

    $content = file_get_contents($absolutePath);
    if ($content === false) {
        continue;
    }

    $language = detect_language($absolutePath);
    $imports = extract_imports($content);
    $apiConnections = extract_api_connections($content);
    $functionDefinitions = extract_function_definitions($content, $language);
    $functionCalls = extract_function_calls($content);

    $importEdges = [];
    foreach ($imports as $importPath) {
        $resolved = resolve_local_import($importPath, $absolutePath, $coreaiRoot);
        if ($resolved !== null) {
            $importEdges[] = $resolved;
        }
    }

    $files[$relativePath] = [
        'path' => $relativePath,
        'language' => $language,
        'imports' => $imports,
        'import_edges' => array_values(array_unique($importEdges)),
        'function_definitions' => $functionDefinitions,
        'function_calls' => $functionCalls,
        'api_connections' => $apiConnections,
    ];
}

// Build global function index to map usage across files.
// إنشاء فهرس عام للدوال لربط الاستدعاءات بين الملفات.
$functionToFiles = [];
foreach ($files as $file) {
    foreach ($file['function_definitions'] as $fn) {
        if (!isset($functionToFiles[$fn])) {
            $functionToFiles[$fn] = [];
        }
        $functionToFiles[$fn][] = $file['path'];
    }
}

$edges = [];
foreach ($files as $sourcePath => $file) {
    foreach ($file['import_edges'] as $targetPath) {
        $edges[] = [
            'from' => $sourcePath,
            'to' => $targetPath,
            'type' => 'import',
        ];
    }

    foreach ($file['function_calls'] as $call) {
        foreach (($functionToFiles[$call] ?? []) as $targetPath) {
            if ($targetPath === $sourcePath) {
                continue;
            }
            $edges[] = [
                'from' => $sourcePath,
                'to' => $targetPath,
                'type' => 'function_usage',
                'symbol' => $call,
            ];
        }
    }

    foreach ($file['api_connections'] as $conn) {
        $localTarget = null;
        if (str_starts_with($conn, 'php/') || str_starts_with($conn, '/coreai/php/')) {
            $normalized = ltrim(str_replace('/coreai/', '', $conn), '/');
            if (isset($files[$normalized])) {
                $localTarget = $normalized;
            }
        }
        if ($localTarget !== null) {
            $edges[] = [
                'from' => $sourcePath,
                'to' => $localTarget,
                'type' => 'api_connection',
                'endpoint' => $conn,
            ];
        } else {
            $edges[] = [
                'from' => $sourcePath,
                'to' => $conn,
                'type' => 'api_connection_external',
            ];
        }
    }
}

$graph = [
    'generated_at' => gmdate(DATE_ATOM),
    'root' => rel_path($coreaiRoot, $coreaiRoot),
    'summary' => [
        'file_count' => count($files),
        'edge_count' => count($edges),
        'function_symbol_count' => count($functionToFiles),
    ],
    'files' => array_values($files),
    'edges' => $edges,
];

$json = json_encode($graph, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    fwrite(STDERR, "Failed to encode project graph JSON.\n");
    exit(1);
}

if (file_put_contents($outputFile, $json . PHP_EOL, LOCK_EX) === false) {
    fwrite(STDERR, "Failed to write project graph.\n");
    exit(1);
}

if (PHP_SAPI !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
    echo $json;
    exit;
}

echo "Project graph generated at: " . $outputFile . PHP_EOL;

<?php
declare(strict_types=1);

/**
 * CoreAI Semantic Code Graph Builder
 * منشئ المخطط الدلالي للكود في CoreAI
 *
 * Goal:
 * Build a meaning-aware graph (logic flow), not only file links.
 * بناء مخطط يعتمد على المعنى وتدفق المنطق وليس مجرد ارتباطات الملفات.
 *
 * Output:
 * /coreai/semantic_graph.json
 */

$coreaiRoot = realpath(dirname(__DIR__));
if ($coreaiRoot === false) {
    fwrite(STDERR, "Failed to resolve coreai root.\n");
    exit(1);
}
$outputFile = $coreaiRoot . DIRECTORY_SEPARATOR . 'semantic_graph.json';

function sg_rel_path(string $absolutePath, string $root): string
{
    $normalized = str_replace('\\', '/', $absolutePath);
    $normalizedRoot = str_replace('\\', '/', $root);
    if (str_starts_with($normalized, $normalizedRoot . '/')) {
        return substr($normalized, strlen($normalizedRoot) + 1);
    }
    return $normalized;
}

function sg_detect_language(string $path): string
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'php':
            return 'php';
        case 'js':
            return 'javascript';
        case 'css':
            return 'css';
        case 'json':
            return 'json';
        case 'md':
            return 'markdown';
        case 'html':
        case 'htm':
            return 'html';
        default:
            return 'other';
    }
}

/**
 * Tag file by architectural role.
 * وسم الملف حسب دوره المعماري.
 */
function sg_tag_file(string $relativePath, string $language): string
{
    $path = strtolower($relativePath);
    if (str_contains($path, 'config/') || str_ends_with($path, '.json') || str_contains($path, '.env')) {
        return 'CONFIG';
    }
    if (str_contains($path, '/php/') || str_ends_with($path, '.php')) {
        if (str_contains($path, 'ai.php') || str_contains($path, 'execute.php') || str_contains($path, 'plan.php') || str_contains($path, 'memory.php')) {
            return 'API_LAYER';
        }
        if (str_contains($path, 'bootstrap.php') || str_contains($path, 'helper') || str_contains($path, 'utils')) {
            return 'HELPER';
        }
        return 'CORE_LOGIC';
    }
    if ($language === 'javascript' || $language === 'css' || str_contains($path, 'frontend/') || str_contains($path, 'js/') || str_contains($path, 'css/')) {
        return 'UI_LAYER';
    }
    return 'HELPER';
}

/**
 * Extract class symbols with lightweight intent hints.
 * استخراج رموز الأصناف مع تلميحات دلالية خفيفة.
 *
 * @return array<int, array{name:string,kind:string,intent:string}>
 */
function sg_extract_classes(string $content): array
{
    $classes = [];
    if (preg_match_all('/\b(class|interface|trait)\s+([a-zA-Z_][a-zA-Z0-9_]*)/i', $content, $matches, PREG_SET_ORDER) > 0) {
        foreach ($matches as $m) {
            $kind = strtolower((string)$m[1]);
            $name = (string)$m[2];
            $intent = 'general';
            $lower = strtolower($name);
            if (str_contains($lower, 'service')) {
                $intent = 'service_logic';
            } elseif (str_contains($lower, 'engine')) {
                $intent = 'execution_engine';
            } elseif (str_contains($lower, 'controller')) {
                $intent = 'request_orchestration';
            }
            $classes[] = ['name' => $name, 'kind' => $kind, 'intent' => $intent];
        }
    }
    return $classes;
}

/**
 * Extract function symbols and infer semantic role from naming.
 * استخراج الدوال واستنتاج الدور الدلالي من الأسماء.
 *
 * @return array<int, array{name:string,semantic_role:string,tag:string}>
 */
function sg_extract_functions(string $content, string $language, string $fileTag): array
{
    $names = [];
    if ($language === 'php' || $language === 'javascript') {
        if (preg_match_all('/\bfunction\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $content, $matches) > 0) {
            $names = array_merge($names, $matches[1]);
        }
        if ($language === 'javascript' && preg_match_all('/\b(?:const|let|var)\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(?:async\s*)?\([^)]*\)\s*=>/', $content, $m2) > 0) {
            $names = array_merge($names, $m2[1]);
        }
    }
    $names = array_values(array_unique($names));

    $output = [];
    foreach ($names as $name) {
        $lower = strtolower($name);
        $role = 'general_logic';
        if (str_starts_with($lower, 'get') || str_starts_with($lower, 'load') || str_starts_with($lower, 'read')) {
            $role = 'data_read';
        } elseif (str_starts_with($lower, 'set') || str_starts_with($lower, 'write') || str_starts_with($lower, 'save')) {
            $role = 'data_write';
        } elseif (str_contains($lower, 'validate') || str_contains($lower, 'sanitize')) {
            $role = 'validation';
        } elseif (str_contains($lower, 'execute') || str_contains($lower, 'run')) {
            $role = 'execution';
        } elseif (str_contains($lower, 'plan') || str_contains($lower, 'analyze')) {
            $role = 'analysis';
        }
        $tag = $fileTag;
        if (in_array($role, ['execution', 'analysis', 'validation'], true)) {
            $tag = 'CORE_LOGIC';
        } elseif (in_array($role, ['data_read', 'data_write'], true) && $fileTag === 'API_LAYER') {
            $tag = 'API_LAYER';
        } elseif ($fileTag === 'CONFIG') {
            $tag = 'CONFIG';
        }
        $output[] = ['name' => $name, 'semantic_role' => $role, 'tag' => $tag];
    }
    return $output;
}

/**
 * Extract function call references.
 * استخراج مراجع استدعاءات الدوال.
 *
 * @return array<int, string>
 */
function sg_extract_calls(string $content): array
{
    $calls = [];
    if (preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $content, $matches) > 0) {
        $ignore = array_fill_keys(
            ['if', 'for', 'while', 'switch', 'catch', 'function', 'return', 'new', 'echo', 'isset', 'empty', 'array'],
            true
        );
        foreach ($matches[1] as $name) {
            $key = strtolower((string)$name);
            if (!isset($ignore[$key])) {
                $calls[] = (string)$name;
            }
        }
    }
    return array_values(array_unique($calls));
}

/**
 * Extract API flow endpoints and classify direction.
 * استخراج نقاط تدفق الـ API وتصنيف الاتجاه.
 *
 * @return array<int, array{endpoint:string,flow_type:string}>
 */
function sg_extract_api_flows(string $content): array
{
    $flows = [];

    // Frontend/client outbound calls.
    // استدعاءات صادرة من الواجهة الأمامية.
    $patterns = [
        '/\bfetch\s*\(\s*[\'"]([^\'"]+)[\'"]/',
        '/\baxios\.(?:get|post|put|delete|patch)\s*\(\s*[\'"]([^\'"]+)[\'"]/',
        '/\bcurl_init\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
    ];

    foreach ($patterns as $p) {
        if (preg_match_all($p, $content, $matches) > 0) {
            foreach (($matches[1] ?? []) as $endpoint) {
                $ep = trim((string)$endpoint);
                if ($ep !== '') {
                    $flowType = str_starts_with($ep, 'http') ? 'external_api_call' : 'internal_api_call';
                    $flows[] = ['endpoint' => $ep, 'flow_type' => $flowType];
                }
            }
        }
    }

    // PHP endpoints handling incoming requests.
    // نقاط PHP التي تستقبل الطلبات.
    if (preg_match('/\$_SERVER\[[\'"]REQUEST_METHOD[\'"]\]/', $content) === 1) {
        $flows[] = ['endpoint' => '[self-endpoint]', 'flow_type' => 'api_handler'];
    }

    return array_values(array_unique($flows, SORT_REGULAR));
}

/**
 * Extract simple data flow signals (read/write/memory).
 * استخراج إشارات تدفق البيانات (قراءة/كتابة/ذاكرة).
 *
 * @return array<int, string>
 */
function sg_extract_data_flows(string $content): array
{
    $signals = [];
    $checks = [
        'file_read' => '/\b(file_get_contents|fopen|readfile)\s*\(/',
        'file_write' => '/\b(file_put_contents|fwrite|unlink|mkdir)\s*\(/',
        'json_transform' => '/\b(json_encode|json_decode)\s*\(/',
        'http_input' => '/php:\/\/input|\\$_(GET|POST|REQUEST|SERVER)\b/',
        'browser_storage' => '/\b(localStorage|sessionStorage)\b/',
        'network_io' => '/\b(fetch|curl_exec|axios)\b/',
    ];
    foreach ($checks as $signal => $pattern) {
        if (preg_match($pattern, $content) === 1) {
            $signals[] = $signal;
        }
    }
    return $signals;
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($coreaiRoot, FilesystemIterator::SKIP_DOTS)
);

$files = [];
foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }
    $abs = $fileInfo->getPathname();
    $rel = sg_rel_path($abs, $coreaiRoot);
    if (
        str_starts_with($rel, 'context/logs/')
        || $rel === 'project_graph.json'
        || $rel === 'semantic_graph.json'
    ) {
        continue;
    }

    $content = file_get_contents($abs);
    if ($content === false) {
        continue;
    }

    $lang = sg_detect_language($abs);
    $fileTag = sg_tag_file($rel, $lang);
    $classes = sg_extract_classes($content);
    $functions = sg_extract_functions($content, $lang, $fileTag);
    $calls = sg_extract_calls($content);
    $apiFlows = sg_extract_api_flows($content);
    $dataFlows = sg_extract_data_flows($content);

    $files[$rel] = [
        'path' => $rel,
        'language' => $lang,
        'file_tag' => $fileTag,
        'classes' => $classes,
        'functions' => $functions,
        'calls' => $calls,
        'api_flows' => $apiFlows,
        'data_flows' => $dataFlows,
    ];
}

// Build symbol index for semantic call flow mapping.
// بناء فهرس للرموز لرسم تدفق الاستدعاءات الدلالي.
$functionIndex = [];
$classIndex = [];
foreach ($files as $path => $meta) {
    foreach ($meta['functions'] as $fn) {
        $functionIndex[(string)$fn['name']][] = $path;
    }
    foreach ($meta['classes'] as $cl) {
        $classIndex[(string)$cl['name']][] = $path;
    }
}

$semanticEdges = [];
foreach ($files as $sourcePath => $meta) {
    foreach ($meta['calls'] as $call) {
        foreach (($functionIndex[$call] ?? []) as $targetPath) {
            if ($targetPath === $sourcePath) {
                continue;
            }
            $semanticEdges[] = [
                'from' => $sourcePath,
                'to' => $targetPath,
                'type' => 'logic_flow',
                'reason' => "calls function {$call}",
            ];
        }
    }

    foreach ($meta['api_flows'] as $flow) {
        $endpoint = (string)$flow['endpoint'];
        if ($endpoint === '') {
            continue;
        }
        if (str_starts_with($endpoint, 'php/')) {
            $target = ltrim($endpoint, '/');
            if (isset($files[$target])) {
                $semanticEdges[] = [
                    'from' => $sourcePath,
                    'to' => $target,
                    'type' => 'api_flow',
                    'reason' => 'internal endpoint connection',
                ];
            }
        } else {
            $semanticEdges[] = [
                'from' => $sourcePath,
                'to' => $endpoint,
                'type' => 'api_flow_external',
                'reason' => (string)$flow['flow_type'],
            ];
        }
    }
}

// Build higher-level system flow hints.
// بناء تلميحات عليا لمسار المنطق عبر النظام.
$flowHints = [];
foreach ($files as $path => $meta) {
    $signals = $meta['data_flows'];
    if (in_array('http_input', $signals, true) && in_array('file_write', $signals, true)) {
        $flowHints[] = [
            'file' => $path,
            'flow' => 'request_to_storage',
            'description' => 'Handles request input then writes to filesystem.',
        ];
    }
    if (in_array('browser_storage', $signals, true) && in_array('network_io', $signals, true)) {
        $flowHints[] = [
            'file' => $path,
            'flow' => 'client_state_to_api',
            'description' => 'Uses browser memory and communicates with API.',
        ];
    }
}

$semanticGraph = [
    'generated_at' => gmdate(DATE_ATOM),
    'root' => '.',
    'summary' => [
        'file_count' => count($files),
        'semantic_edge_count' => count($semanticEdges),
        'function_symbol_count' => count($functionIndex),
        'class_symbol_count' => count($classIndex),
        'flow_hint_count' => count($flowHints),
    ],
    'nodes' => array_values($files),
    'semantic_edges' => $semanticEdges,
    'flow_hints' => $flowHints,
];

$json = json_encode($semanticGraph, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    fwrite(STDERR, "Failed to encode semantic graph JSON.\n");
    exit(1);
}

if (file_put_contents($outputFile, $json . PHP_EOL, LOCK_EX) === false) {
    fwrite(STDERR, "Failed to write semantic graph file.\n");
    exit(1);
}

if (PHP_SAPI !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
    echo $json;
    exit;
}

echo "Semantic graph generated at: " . $outputFile . PHP_EOL;

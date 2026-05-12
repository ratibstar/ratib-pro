<?php
/**
 * Minimal safe Markdown → HTML for in-app Help Center (headings, lists, code, links, checklists).
 */
declare(strict_types=1);

if (!function_exists('cp_help_inline')) {
    function cp_help_inline(string $text): string
    {
        $tokens = [];
        $i = 0;
        $text = preg_replace_callback('/\[(.*?)\]\((.*?)\)/', static function (array $m) use (&$tokens, &$i): string {
            $key = '__CPHL_' . $i++ . '__';
            $label = htmlspecialchars((string) ($m[1] ?? ''), ENT_QUOTES, 'UTF-8');
            $hrefRaw = (string) ($m[2] ?? '');
            $href = htmlspecialchars($hrefRaw, ENT_QUOTES, 'UTF-8');
            $target = preg_match('#^https?://#i', $hrefRaw) ? ' target="_blank" rel="noopener noreferrer"' : '';
            $tokens[$key] = '<a href="' . $href . '"' . $target . '>' . $label . '</a>';
            return $key;
        }, $text) ?? $text;

        $text = preg_replace_callback('/`([^`]+)`/', static function (array $m) use (&$tokens, &$i): string {
            $key = '__CPHC_' . $i++ . '__';
            $code = htmlspecialchars((string) ($m[1] ?? ''), ENT_QUOTES, 'UTF-8');
            $tokens[$key] = '<code>' . $code . '</code>';
            return $key;
        }, $text) ?? $text;

        $text = preg_replace_callback('/\*\*(.+?)\*\*/', static function (array $m) use (&$tokens, &$i): string {
            $key = '__CPHB_' . $i++ . '__';
            $inner = htmlspecialchars((string) ($m[1] ?? ''), ENT_QUOTES, 'UTF-8');
            $tokens[$key] = '<strong>' . $inner . '</strong>';
            return $key;
        }, $text) ?? $text;

        $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        return !empty($tokens) ? strtr($safe, $tokens) : $safe;
    }
}

if (!function_exists('cp_help_slug')) {
    function cp_help_slug(string $text): string
    {
        $slug = mb_strtolower(trim($text), 'UTF-8');
        $slug = preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $slug) ?? $slug;
        $slug = preg_replace('/\s+/u', '-', $slug) ?? $slug;
        $slug = trim((string) $slug, '-');
        return $slug !== '' ? $slug : 'section';
    }
}

if (!function_exists('cp_help_render_markdown')) {
    /**
     * @return array{0: string, 1: list<string>} [html, toc ids for sidebar TOC optional]
     */
    function cp_help_render_markdown(string $md): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $md) ?: [];
        $out = [];
        $ids = [];
        $inList = false;
        $inCode = false;

        foreach ($lines as $line) {
            $trim = trim($line);

            if ($trim === '```') {
                if ($inList) {
                    $out[] = '</ul>';
                    $inList = false;
                }
                $out[] = $inCode ? '</code></pre>' : '<pre><code>';
                $inCode = !$inCode;
                continue;
            }

            if ($inCode) {
                $out[] = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
                continue;
            }

            if ($trim === '' || $trim === '---') {
                if ($inList) {
                    $out[] = '</ul>';
                    $inList = false;
                }
                if ($trim === '---') {
                    $out[] = '<hr class="help-hr">';
                }
                continue;
            }

            if (strpos($trim, '#### ') === 0) {
                if ($inList) {
                    $out[] = '</ul>';
                    $inList = false;
                }
                $h = substr($trim, 5);
                $id = cp_help_slug($h);
                $ids[] = $id;
                $out[] = '<h4 id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">' . cp_help_inline($h) . '</h4>';
                continue;
            }

            if (strpos($trim, '### ') === 0) {
                if ($inList) {
                    $out[] = '</ul>';
                    $inList = false;
                }
                $h = substr($trim, 4);
                $id = cp_help_slug($h);
                $ids[] = $id;
                $out[] = '<h3 id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">' . cp_help_inline($h) . '</h3>';
                continue;
            }

            if (strpos($trim, '## ') === 0) {
                if ($inList) {
                    $out[] = '</ul>';
                    $inList = false;
                }
                $h = substr($trim, 3);
                $id = cp_help_slug($h);
                $ids[] = $id;
                $out[] = '<h2 id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">' . cp_help_inline($h) . '</h2>';
                continue;
            }

            if (strpos($trim, '# ') === 0) {
                if ($inList) {
                    $out[] = '</ul>';
                    $inList = false;
                }
                $h = substr($trim, 2);
                $id = cp_help_slug($h);
                $ids[] = $id;
                $out[] = '<h1 id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">' . cp_help_inline($h) . '</h1>';
                continue;
            }

            if (preg_match('/^- \[( |x|X)\] (.+)$/', $trim, $m)) {
                if (!$inList) {
                    $out[] = '<ul class="help-checklist">';
                    $inList = true;
                }
                $checked = strtoupper((string) $m[1]) === 'X' ? ' checked' : '';
                $out[] = '<li><input type="checkbox" disabled' . $checked . '> <span>' . cp_help_inline($m[2]) . '</span></li>';
                continue;
            }

            if (strpos($trim, '- ') === 0) {
                if (!$inList) {
                    $out[] = '<ul class="help-ul">';
                    $inList = true;
                }
                $out[] = '<li>' . cp_help_inline(substr($trim, 2)) . '</li>';
                continue;
            }

            if ($inList) {
                $out[] = '</ul>';
                $inList = false;
            }
            $out[] = '<p>' . cp_help_inline($trim) . '</p>';
        }

        if ($inList) {
            $out[] = '</ul>';
        }
        if ($inCode) {
            $out[] = '</code></pre>';
        }
        return [implode("\n", $out), $ids];
    }
}

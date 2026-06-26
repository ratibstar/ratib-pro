<?php
declare(strict_types=1);

namespace Rateb\App\Core;

final class HtmlSanitizer
{
    /** CMS analytics embeds — no scripts, no event handlers. */
    public static function sanitizeAnalyticsEmbed(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? '';
        $html = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html) ?? '';
        $html = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/javascript\s*:/i', '', $html) ?? '';
        $html = preg_replace('/data\s*:\s*text\/html/i', '', $html) ?? '';
        $allowed = '<meta><link><noscript>';
        return strip_tags($html, $allowed);
    }

    /** Blog/CMS rich text — strip dangerous attributes and javascript: URLs. */
    public static function sanitizeRichHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? '';
        $html = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><a><h1><h2><h3><h4><h5><h6><img><blockquote><span>';
        $html = strip_tags($html, $allowed);
        $html = preg_replace('/href\s*=\s*["\']\s*javascript:[^"\']*["\']/i', 'href="#"', $html) ?? '';
        $html = preg_replace('/src\s*=\s*["\']\s*javascript:[^"\']*["\']/i', '', $html) ?? '';
        return $html;
    }

    /** Reject SVG payloads that may execute script when served inline. */
    public static function svgContainsDangerousContent(string $contents): bool
    {
        $lower = strtolower($contents);
        $needles = ['<script', 'onload=', 'onerror=', 'javascript:', '<foreignobject', 'xlink:href="javascript'];
        foreach ($needles as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }
        return false;
    }
}

<?php
declare(strict_types=1);

namespace Rateb\App\Website;

/**
 * Phase WEBSITE-04 — Catalog of builder block types (single registry; no duplicates).
 */
final class WebsiteBlockRegistry
{
    /**
     * @return array<string, array{label_en:string,label_ar:string,category:string,icon:string}>
     */
    public static function all(): array
    {
        return [
            'hero' => ['label_en' => 'Hero', 'label_ar' => 'بطل', 'category' => 'layout', 'icon' => 'fa-panorama'],
            'about' => ['label_en' => 'About', 'label_ar' => 'من نحن', 'category' => 'content', 'icon' => 'fa-circle-info'],
            'services' => ['label_en' => 'Services', 'label_ar' => 'خدمات', 'category' => 'content', 'icon' => 'fa-briefcase'],
            'features' => ['label_en' => 'Features', 'label_ar' => 'مميزات', 'category' => 'content', 'icon' => 'fa-stars'],
            'counters' => ['label_en' => 'Counters', 'label_ar' => 'عدادات', 'category' => 'content', 'icon' => 'fa-hashtag'],
            'cta' => ['label_en' => 'CTA', 'label_ar' => 'دعوة لاتخاذ إجراء', 'category' => 'layout', 'icon' => 'fa-bullhorn'],
            'team' => ['label_en' => 'Team', 'label_ar' => 'الفريق', 'category' => 'content', 'icon' => 'fa-users'],
            'testimonials' => ['label_en' => 'Testimonials', 'label_ar' => 'شهادات', 'category' => 'content', 'icon' => 'fa-quote-left'],
            'faq' => ['label_en' => 'FAQ', 'label_ar' => 'أسئلة شائعة', 'category' => 'content', 'icon' => 'fa-circle-question'],
            'pricing' => ['label_en' => 'Pricing', 'label_ar' => 'أسعار', 'category' => 'commerce', 'icon' => 'fa-tags'],
            'blog' => ['label_en' => 'Blog', 'label_ar' => 'مدونة', 'category' => 'content', 'icon' => 'fa-newspaper'],
            'news' => ['label_en' => 'News', 'label_ar' => 'أخبار', 'category' => 'content', 'icon' => 'fa-rss'],
            'careers' => ['label_en' => 'Careers', 'label_ar' => 'وظائف', 'category' => 'content', 'icon' => 'fa-user-tie'],
            'contact' => ['label_en' => 'Contact', 'label_ar' => 'تواصل', 'category' => 'forms', 'icon' => 'fa-envelope'],
            'gallery' => ['label_en' => 'Gallery', 'label_ar' => 'معرض', 'category' => 'media', 'icon' => 'fa-images'],
            'partners' => ['label_en' => 'Partners', 'label_ar' => 'شركاء', 'category' => 'content', 'icon' => 'fa-handshake'],
            'brands' => ['label_en' => 'Brands', 'label_ar' => 'علامات', 'category' => 'content', 'icon' => 'fa-copyright'],
            'map' => ['label_en' => 'Map', 'label_ar' => 'خريطة', 'category' => 'embed', 'icon' => 'fa-map-location-dot'],
            'whatsapp' => ['label_en' => 'WhatsApp', 'label_ar' => 'واتساب', 'category' => 'embed', 'icon' => 'fa-whatsapp'],
            'forms' => ['label_en' => 'Forms', 'label_ar' => 'نماذج', 'category' => 'forms', 'icon' => 'fa-list-check'],
            'custom_html' => ['label_en' => 'Custom HTML', 'label_ar' => 'HTML مخصص', 'category' => 'advanced', 'icon' => 'fa-code'],
            'spacer' => ['label_en' => 'Spacer', 'label_ar' => 'مسافة', 'category' => 'layout', 'icon' => 'fa-arrows-up-down'],
            'divider' => ['label_en' => 'Divider', 'label_ar' => 'فاصل', 'category' => 'layout', 'icon' => 'fa-minus'],
            'video' => ['label_en' => 'Video', 'label_ar' => 'فيديو', 'category' => 'media', 'icon' => 'fa-video'],
            'image' => ['label_en' => 'Image', 'label_ar' => 'صورة', 'category' => 'media', 'icon' => 'fa-image'],
            'slider' => ['label_en' => 'Slider', 'label_ar' => 'شريط صور', 'category' => 'media', 'icon' => 'fa-sliders'],
            'text' => ['label_en' => 'Text', 'label_ar' => 'نص', 'category' => 'content', 'icon' => 'fa-align-left'],
        ];
    }

    public static function isValid(string $type): bool
    {
        return isset(self::all()[$type]);
    }

    /** @return list<string> */
    public static function typeIds(): array
    {
        return array_keys(self::all());
    }

    /** @return array{title_en:string,title_ar:string,content_en:string,content_ar:string,settings:array<string,mixed>} */
    public static function defaults(string $type): array
    {
        $meta = self::all()[$type] ?? ['label_en' => $type, 'label_ar' => $type];

        return [
            'title_en' => (string) $meta['label_en'],
            'title_ar' => (string) $meta['label_ar'],
            'content_en' => '',
            'content_ar' => '',
            'settings' => match ($type) {
                'hero' => ['cta_label_en' => 'Get started', 'cta_label_ar' => 'ابدأ الآن', 'cta_url' => '#', 'overlay' => true],
                'counters' => ['items' => []],
                'pricing' => ['plans' => []],
                'faq' => ['items' => []],
                'gallery' => ['images' => []],
                'partners', 'brands' => ['logos' => []],
                'map' => ['embed_url' => '', 'height' => 360],
                'whatsapp' => ['phone' => '', 'message_en' => '', 'message_ar' => ''],
                'forms' => ['form_slug' => 'contact'],
                'spacer' => ['height' => 48],
                'divider' => ['style' => 'solid'],
                'video' => ['src' => '', 'poster' => '', 'autoplay' => false],
                'slider' => ['slides' => []],
                'custom_html' => ['allow_scripts' => false],
                default => [],
            },
        ];
    }
}

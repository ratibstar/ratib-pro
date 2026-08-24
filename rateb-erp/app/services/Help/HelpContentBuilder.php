<?php
declare(strict_types=1);

namespace Rateb\App\Services\Help;

/**
 * Builds full bilingual help articles from blueprints (fast, file-backed, no N+1).
 */
final class HelpContentBuilder
{
    /** @var list<array<string,mixed>>|null */
    private static ?array $articlesCache = null;

    /** @var array<string,array<string,mixed>>|null */
    private static ?array $modulesBySlug = null;

    /** @var array<string,array<string,mixed>>|null */
    private static ?array $blueprintsCache = null;

    /** @return list<array<string,mixed>> */
    public static function modules(): array
    {
        $path = RATEB_ROOT . '/config/help-center/modules.php';
        /** @var list<array<string,mixed>> $modules */
        $modules = is_file($path) ? (require $path) : [];
        usort($modules, static fn (array $a, array $b): int => ((int) ($a['sort'] ?? 0)) <=> ((int) ($b['sort'] ?? 0)));

        return $modules;
    }

    /** @return array<string,array<string,mixed>> */
    public static function modulesBySlug(): array
    {
        if (self::$modulesBySlug !== null) {
            return self::$modulesBySlug;
        }
        $map = [];
        foreach (self::modules() as $module) {
            $slug = (string) ($module['slug'] ?? '');
            if ($slug !== '') {
                $map[$slug] = $module;
            }
        }
        self::$modulesBySlug = $map;

        return $map;
    }

    /** @return array<string,array<string,mixed>> */
    public static function blueprints(): array
    {
        if (self::$blueprintsCache !== null) {
            return self::$blueprintsCache;
        }
        $path = RATEB_ROOT . '/config/help-center/blueprints.php';
        /** @var array<string,array<string,mixed>> $data */
        $data = is_file($path) ? (require $path) : [];
        self::$blueprintsCache = $data;

        return $data;
    }

    /** @return list<array<string,mixed>> */
    public static function faqs(): array
    {
        $path = RATEB_ROOT . '/config/help-center/faq.php';
        /** @var list<array<string,mixed>> $faqs */
        $faqs = is_file($path) ? (require $path) : [];

        return $faqs;
    }

    /** @return list<array<string,mixed>> */
    public static function articles(): array
    {
        if (self::$articlesCache !== null) {
            return self::$articlesCache;
        }

        $out = [];
        $sortBase = 0;
        foreach (self::blueprints() as $moduleSlug => $bp) {
            $flowAr = array_values(array_map('strval', $bp['flow_ar'] ?? []));
            $flowEn = array_values(array_map('strval', $bp['flow_en'] ?? []));
            $articles = is_array($bp['articles'] ?? null) ? $bp['articles'] : [];
            $i = 0;
            foreach ($articles as $raw) {
                if (!is_array($raw)) {
                    continue;
                }
                $slug = (string) ($raw['slug'] ?? '');
                if ($slug === '') {
                    continue;
                }
                $titleAr = (string) ($raw['title_ar'] ?? $slug);
                $titleEn = (string) ($raw['title_en'] ?? $slug);
                $difficulty = (string) ($raw['difficulty'] ?? 'beginner');
                $minutes = max(1, (int) ($raw['minutes'] ?? 3));
                $audience = (string) ($raw['audience'] ?? 'all');
                $keywords = array_values(array_unique(array_filter(array_map('strval', $raw['keywords'] ?? []))));
                $icon = (string) ($raw['icon'] ?? 'fa-circle-question');
                $related = [];
                foreach ($articles as $other) {
                    if (!is_array($other)) {
                        continue;
                    }
                    $otherSlug = (string) ($other['slug'] ?? '');
                    if ($otherSlug !== '' && $otherSlug !== $slug) {
                        $related[] = $otherSlug;
                    }
                }
                $related = array_slice($related, 0, 4);

                $out[] = [
                    'slug' => $slug,
                    'module' => (string) $moduleSlug,
                    'title_ar' => $titleAr,
                    'title_en' => $titleEn,
                    'summary_ar' => self::summaryAr($titleAr, $moduleSlug, $flowAr),
                    'summary_en' => self::summaryEn($titleEn, $moduleSlug, $flowEn),
                    'keywords' => $keywords,
                    'difficulty' => $difficulty,
                    'minutes' => $minutes,
                    'audience' => $audience,
                    'icon' => $icon,
                    'related' => $related,
                    'sort' => $sortBase + (++$i),
                    'status' => 'published',
                    'sections' => self::buildSections($titleAr, $titleEn, (string) $moduleSlug, $flowAr, $flowEn, $i),
                ];
            }
            $sortBase += 100;
        }

        self::$articlesCache = $out;

        return $out;
    }

    /** @param list<string> $flowAr @param list<string> $flowEn */
    private static function summaryAr(string $title, string $module, array $flowAr): string
    {
        $flow = $flowAr !== [] ? implode(' ← ', array_reverse($flowAr)) : $module;

        return 'شرح عملي لـ «' . $title . '» ضمن مسار: ' . $flow . '.';
    }

    /** @param list<string> $flowEn */
    private static function summaryEn(string $title, string $module, array $flowEn): string
    {
        $flow = $flowEn !== [] ? implode(' → ', $flowEn) : $module;

        return 'Practical guide for “' . $title . '” along: ' . $flow . '.';
    }

    /**
     * @param list<string> $flowAr
     * @param list<string> $flowEn
     * @return array<string,mixed>
     */
    private static function buildSections(
        string $titleAr,
        string $titleEn,
        string $module,
        array $flowAr,
        array $flowEn,
        int $index
    ): array {
        $stepFocusAr = $flowAr[$index - 1] ?? ($flowAr[0] ?? $titleAr);
        $stepFocusEn = $flowEn[$index - 1] ?? ($flowEn[0] ?? $titleEn);
        $moduleTitleAr = (string) (self::modulesBySlug()[$module]['title_ar'] ?? $module);
        $moduleTitleEn = (string) (self::modulesBySlug()[$module]['title_en'] ?? $module);

        $stepsAr = [
            'من القائمة الجانبية افتح وحدة «' . $moduleTitleAr . '».',
            'حدّد العملية المتعلقة بـ «' . $stepFocusAr . '» ثم اضغط إنشاء / جديد.',
            'أكمل الحقول الإلزامية بدقة (التواريخ، الكميات، والمرجع).',
            'احفظ المسودة ثم راجعها قبل الإرسال أو الاعتماد.',
            'تابع الحالة من قائمة الوحدة أو من الموافقات إن لزم.',
        ];
        $stepsEn = [
            'From the sidebar, open the “' . $moduleTitleEn . '” module.',
            'Choose the action related to “' . $stepFocusEn . '” and click Create / New.',
            'Complete required fields carefully (dates, quantities, and references).',
            'Save a draft, then review before submit or approval.',
            'Track status from the module list or Approvals when required.',
        ];

        return [
            'what' => [
                'ar' => '«' . $titleAr . '» هي وظيفة داخل وحدة ' . $moduleTitleAr . ' تساعدك على تنفيذ خطوة «' . $stepFocusAr . '» ضمن دورة العمل اليومية بشكل منظم وقابل للتتبع.',
                'en' => '“' . $titleEn . '” is a capability in ' . $moduleTitleEn . ' that helps you complete “' . $stepFocusEn . '” within the daily operational cycle in a structured, traceable way.',
            ],
            'when' => [
                'ar' => 'استخدمها عندما تحتاج تنفيذ أو متابعة خطوة «' . $stepFocusAr . '»، أو عند تدريب مستخدم جديد على وحدة ' . $moduleTitleAr . '.',
                'en' => 'Use it when you need to perform or follow “' . $stepFocusEn . '”, or when onboarding a new user on ' . $moduleTitleEn . '.',
            ],
            'steps' => [
                'ar' => $stepsAr,
                'en' => $stepsEn,
            ],
            'example' => [
                'ar' => 'مثال: فريق العمل يبدأ من «' . ($flowAr[0] ?? $titleAr) . '» ثم يكمل المسار ' . implode(' ← ', array_reverse($flowAr)) . ' حتى الإغلاق.',
                'en' => 'Example: the team starts at “' . ($flowEn[0] ?? $titleEn) . '” then follows ' . implode(' → ', $flowEn) . ' until completion.',
            ],
            'tips' => [
                'ar' => [
                    'تحقق من الفرع/المستودع الصحيح قبل الحفظ.',
                    'استخدم البحث داخل الوحدة برقم المستند أو الاسم.',
                    'راجع الصلاحيات إذا لم يظهر زر الإنشاء.',
                ],
                'en' => [
                    'Confirm the correct branch/warehouse before saving.',
                    'Search inside the module by document number or name.',
                    'Check permissions if the Create button is missing.',
                ],
            ],
            'mistakes' => [
                'ar' => [
                    'حفظ المستند على فرع خاطئ.',
                    'ترك حقول إلزامية فارغة ثم التوقف عند الاعتماد.',
                    'تكرار العملية مرتين بسبب عدم تحديث الصفحة.',
                ],
                'en' => [
                    'Saving the document under the wrong branch.',
                    'Leaving required fields empty and getting stuck at approval.',
                    'Duplicating the action because the page was not refreshed.',
                ],
            ],
        ];
    }
}

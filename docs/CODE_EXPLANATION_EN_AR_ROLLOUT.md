# Code Explanation Rollout (EN/AR)

## Goal
- EN: Add concise bilingual explanations across PHP/JS/CSS without turning code into noise.
- AR: إضافة شروحات ثنائية اللغة بشكل مختصر في ملفات PHP/JS/CSS بدون جعل الكود مزدحمًا.

## Scope Reality
- EN: The project has hundreds of source files; updating all files in one pass is risky and hard to review.
- AR: المشروع يحتوي على مئات الملفات؛ تعديل جميع الملفات دفعة واحدة يحمل مخاطرة عالية ويصعب مراجعته.

## Recommended Strategy
1. **File Header First**
   - EN: Add a short bilingual header at the top of each file explaining purpose/responsibility.
   - AR: إضافة ترويسة قصيرة ثنائية اللغة أعلى كل ملف توضح وظيفته ومسؤوليته.
2. **Section Comments Second**
   - EN: Add bilingual comments only before complex sections/functions.
   - AR: إضافة تعليقات ثنائية اللغة قبل الأجزاء/الدوال المعقدة فقط.
3. **No Line-by-Line Commentary**
   - EN: Avoid commenting every line; it harms readability and maintenance.
   - AR: تجنب التعليق على كل سطر؛ لأنه يضر القراءة والصيانة.

## Comment Templates

### PHP file header
```php
/**
 * EN: <file purpose in one sentence>
 * AR: <وصف وظيفة الملف بجملة واحدة>
 */
```

### JS section comment
```js
// EN: <what this block does + why>
// AR: <ماذا يفعل هذا الجزء + لماذا>
```

### CSS section comment
```css
/* EN: <UI area/component>
 * AR: <اسم الجزء/المكوّن في الواجهة>
 */
```

## Batch Plan
- Batch 1: `pages/`, `js/pages/`, `css/pages/` (public user-facing pages)
- Batch 2: `control-panel/pages/`, `control-panel/js/`, `control-panel/css/`
- Batch 3: `admin/` modules
- Batch 4: `api/` and shared `includes/`/`core/`

## Done in this chat
- Added bilingual file-purpose headers to:
  - `pages/home.php`
  - `js/pages/home-page.js`
  - `css/pages/home-public.css`
  - `admin/assets/css/control-center.css`

## Next Step
- EN: Continue batch-by-batch. Each batch should stay reviewable.
- AR: نكمل على دفعات قابلة للمراجعة حتى تكون التغييرات آمنة وواضحة.

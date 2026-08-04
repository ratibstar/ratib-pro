<?php

declare(strict_types=1);

namespace Rateb\App\GuestMenu\Services;

/**
 * Comprehensive Saudi retail catalog seed — categories + published products.
 * SKU prefix RC-* is reserved for this seed (idempotent / reversible).
 */
final class PlatformRetailCatalogSeedData
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function run(): void
    {
        try {
            $this->pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (\Throwable) {
            // ignore — connection helper already sets charset when available
        }

        $unitId = $this->unitIdPcs();
        if ($unitId < 1) {
            throw new \RuntimeException('PCS unit missing — run 002_reference_data first');
        }

        $categories = $this->seedCategories();
        $this->seedBrands();
        $this->seedProducts($categories, $unitId);
    }

    /**
     * @return array<string, array{name_ar:string, name_en:string, sort:int}>
     */
    public static function categoryMetaBySlug(): array
    {
        $map = [];
        foreach (self::categoryDefs() as [$slug, $ar, $en, $sort]) {
            $map[$slug] = ['name_ar' => $ar, 'name_en' => $en, 'sort' => $sort];
        }

        return $map;
    }

    /**
     * SKU => authoritative seed fields (names never contain ??).
     *
     * @return array<string, array{sku:string, barcode:string, name_ar:string, name_en:string, price:float, category_slug:string, category_name_ar:string, category_name_en:string}>
     */
    public static function authoritativeSkuMap(): array
    {
        $cats = self::categoryMetaBySlug();
        $out = [];
        foreach (self::productRows() as $row) {
            $sku = (string) ($row['sku'] ?? '');
            if ($sku === '') {
                continue;
            }
            $slug = (string) ($row['cat'] ?? '');
            $meta = $cats[$slug] ?? ['name_ar' => 'عام', 'name_en' => 'General', 'sort' => 0];
            $out[$sku] = [
                'sku' => $sku,
                'barcode' => (string) ($row['barcode'] ?? ''),
                'name_ar' => (string) ($row['name_ar'] ?? ''),
                'name_en' => (string) ($row['name_en'] ?? ''),
                'price' => (float) ($row['price'] ?? 0),
                'category_slug' => $slug,
                'category_name_ar' => (string) ($meta['name_ar'] ?? 'عام'),
                'category_name_en' => (string) ($meta['name_en'] ?? 'General'),
            ];
        }

        return $out;
    }

    /**
     * Industry / business-type packs for selective import into a company menu.
     *
     * @return array<string, array{label_ar:string, label_en:string, cats:list<string>|null}>
     */
    public static function industryPacks(): array
    {
        return [
            'restaurant' => [
                'label_ar' => 'مطعم',
                'label_en' => 'Restaurant',
                'cats' => ['retail-restaurants', 'retail-cafe', 'retail-beverages', 'retail-bakery'],
            ],
            'cafe' => [
                'label_ar' => 'كافيه',
                'label_en' => 'Cafe',
                'cats' => ['retail-cafe', 'retail-bakery', 'retail-beverages'],
            ],
            'clothing' => [
                'label_ar' => 'ملابس وأحذية',
                'label_en' => 'Clothing & Shoes',
                'cats' => ['retail-clothing-men', 'retail-clothing-women', 'retail-shoes'],
            ],
            'grocery' => [
                'label_ar' => 'بقالة وتموين',
                'label_en' => 'Grocery',
                'cats' => ['retail-groceries', 'retail-provisions', 'retail-beverages', 'retail-dairy', 'retail-bakery'],
            ],
            'electronics' => [
                'label_ar' => 'جوالات وإلكترونيات',
                'label_en' => 'Electronics',
                'cats' => ['retail-mobiles', 'retail-accessories', 'retail-electronics'],
            ],
            'pharmacy' => [
                'label_ar' => 'صيدلية وعناية',
                'label_en' => 'Pharmacy',
                'cats' => ['retail-pharmacy', 'retail-personal-care', 'retail-baby'],
            ],
            'factory' => [
                'label_ar' => 'مصنع / صناعي',
                'label_en' => 'Factory / Industrial',
                'cats' => [
                    'retail-factory-raw',
                    'retail-factory-packaging',
                    'retail-factory-tools',
                    'retail-factory-safety',
                ],
            ],
            'automotive' => [
                'label_ar' => 'سيارات',
                'label_en' => 'Automotive',
                'cats' => ['retail-automotive'],
            ],
            'sports' => [
                'label_ar' => 'رياضة',
                'label_en' => 'Sports',
                'cats' => ['retail-sports'],
            ],
            'office' => [
                'label_ar' => 'مكتبية',
                'label_en' => 'Office',
                'cats' => ['retail-office'],
            ],
            'household' => [
                'label_ar' => 'منزلية',
                'label_en' => 'Household',
                'cats' => ['retail-household', 'retail-personal-care'],
            ],
            'all' => [
                'label_ar' => 'كل القطاعات',
                'label_en' => 'All industries',
                'cats' => null,
            ],
        ];
    }

    public function down(): void
    {
        $this->exec(
            'DELETE pp FROM product_prices pp
             INNER JOIN products p ON p.id = pp.product_id
             WHERE p.sku LIKE "RC-%"'
        );
        $this->exec(
            'DELETE pt FROM product_translations pt
             INNER JOIN products p ON p.id = pt.product_id
             WHERE p.sku LIKE "RC-%"'
        );
        $this->exec('DELETE FROM products WHERE sku LIKE "RC-%"');
        $this->exec(
            'DELETE ct FROM category_translations ct
             INNER JOIN categories c ON c.id = ct.category_id
             WHERE c.slug LIKE "retail-%"'
        );
        $this->exec('DELETE FROM categories WHERE slug LIKE "retail-%"');
        $this->exec(
            'DELETE bt FROM brand_translations bt
             INNER JOIN brands b ON b.id = bt.brand_id
             WHERE b.slug LIKE "retail-%"'
        );
        $this->exec('DELETE FROM brands WHERE slug LIKE "retail-%"');
    }

    /**
     * @return list<array{0:string,1:string,2:string,3:int}>
     */
    public static function categoryDefs(): array
    {
        return [
            ['retail-groceries', 'بقالات', 'Groceries', 10],
            ['retail-provisions', 'تموينات', 'Provisions', 20],
            ['retail-beverages', 'مشروبات', 'Beverages', 30],
            ['retail-dairy', 'ألبان وأجبان', 'Dairy & Cheese', 40],
            ['retail-bakery', 'مخبوزات', 'Bakery', 50],
            ['retail-mobiles', 'جوالات', 'Mobiles', 60],
            ['retail-accessories', 'إكسسوارات الجوال', 'Mobile Accessories', 70],
            ['retail-electronics', 'إلكترونيات', 'Electronics', 80],
            ['retail-clothing-men', 'ملابس رجالية', 'Men\'s Clothing', 90],
            ['retail-clothing-women', 'ملابس نسائية', 'Women\'s Clothing', 100],
            ['retail-shoes', 'أحذية', 'Shoes', 110],
            ['retail-pharmacy', 'صيدليات ومستلزمات', 'Pharmacy & Wellness', 120],
            ['retail-restaurants', 'مطاعم', 'Restaurants', 130],
            ['retail-cafe', 'كافيه ومشروبات ساخنة', 'Cafe', 140],
            ['retail-household', 'منزلية', 'Household', 150],
            ['retail-personal-care', 'عناية شخصية', 'Personal Care', 160],
            ['retail-baby', 'مستلزمات أطفال', 'Baby Care', 170],
            ['retail-automotive', 'سيارات', 'Automotive', 180],
            ['retail-sports', 'رياضة', 'Sports', 190],
            ['retail-office', 'مكتبية', 'Office Supplies', 200],
            ['retail-factory-raw', 'مواد خام صناعية', 'Factory Raw Materials', 210],
            ['retail-factory-packaging', 'تعبئة وتغليف', 'Packaging', 220],
            ['retail-factory-tools', 'أدوات ومعدات صناعية', 'Industrial Tools', 230],
            ['retail-factory-safety', 'سلامة مهنية', 'Workplace Safety', 240],
        ];
    }

    /** @return array<string, int> slug => id */
    private function seedCategories(): array
    {
        $map = [];
        foreach (self::categoryDefs() as [$slug, $nameAr, $nameEn, $sort]) {
            $map[$slug] = $this->upsertCategory($slug, $nameAr, $nameEn, $sort);
        }

        return $map;
    }

    private function seedBrands(): void
    {
        $brands = [
            ['retail-brand-rateb', 'رتب', 'RATEB'],
            ['retail-brand-almarai', 'المراعي', 'Almarai'],
            ['retail-brand-nada', 'ندى', 'Nada'],
            ['retail-brand-samsung', 'سامسونج', 'Samsung'],
            ['retail-brand-apple', 'آبل', 'Apple'],
            ['retail-brand-nike', 'نايك', 'Nike'],
            ['retail-brand-generic', 'عام', 'Generic'],
        ];
        foreach ($brands as [$slug, $ar, $en]) {
            $this->upsertBrand($slug, $ar, $en);
        }
    }

    /** @param array<string, int> $categories */
    private function seedProducts(array $categories, int $unitId): void
    {
        foreach (self::productRows() as $row) {
            $catId = $categories[$row['cat']] ?? 0;
            if ($catId < 1) {
                continue;
            }
            $this->upsertProduct($row, $catId, $unitId);
        }
    }

    /**
     * Authoritative UTF-8 catalog rows from this PHP seed (never trust DB ?? copies).
     *
     * @return list<array{cat:string,sku:string,barcode:string,name_ar:string,name_en:string,price:float,brand?:string}>
     */
    public static function productRows(): array
    {
        return [
            // بقالات
            ['cat' => 'retail-groceries', 'sku' => 'RC-GRC-001', 'barcode' => '6281000001001', 'name_ar' => 'أرز بسمتي 5 كجم', 'name_en' => 'Basmati Rice 5kg', 'price' => 39.0],
            ['cat' => 'retail-groceries', 'sku' => 'RC-GRC-002', 'barcode' => '6281000001002', 'name_ar' => 'سكر أبيض 2 كجم', 'name_en' => 'White Sugar 2kg', 'price' => 9.5],
            ['cat' => 'retail-groceries', 'sku' => 'RC-GRC-003', 'barcode' => '6281000001003', 'name_ar' => 'زيت ذرة 1.8 لتر', 'name_en' => 'Corn Oil 1.8L', 'price' => 22.0],
            ['cat' => 'retail-groceries', 'sku' => 'RC-GRC-004', 'barcode' => '6281000001004', 'name_ar' => 'معكرونة إسباجيتي 500غ', 'name_en' => 'Spaghetti 500g', 'price' => 4.5],
            ['cat' => 'retail-groceries', 'sku' => 'RC-GRC-005', 'barcode' => '6281000001005', 'name_ar' => 'دقيق أبيض 1 كجم', 'name_en' => 'White Flour 1kg', 'price' => 5.0],
            // تموينات
            ['cat' => 'retail-provisions', 'sku' => 'RC-PRV-001', 'barcode' => '6281000002001', 'name_ar' => 'عدس أحمر 1 كجم', 'name_en' => 'Red Lentils 1kg', 'price' => 12.0],
            ['cat' => 'retail-provisions', 'sku' => 'RC-PRV-002', 'barcode' => '6281000002002', 'name_ar' => 'حمص حب 1 كجم', 'name_en' => 'Chickpeas 1kg', 'price' => 11.0],
            ['cat' => 'retail-provisions', 'sku' => 'RC-PRV-003', 'barcode' => '6281000002003', 'name_ar' => 'معجون طماطم 400غ', 'name_en' => 'Tomato Paste 400g', 'price' => 3.5],
            ['cat' => 'retail-provisions', 'sku' => 'RC-PRV-004', 'barcode' => '6281000002004', 'name_ar' => 'فول مدمس علبة', 'name_en' => 'Canned Fava Beans', 'price' => 4.0],
            ['cat' => 'retail-provisions', 'sku' => 'RC-PRV-005', 'barcode' => '6281000002005', 'name_ar' => 'شاي أحمر 100 كيس', 'name_en' => 'Black Tea 100 bags', 'price' => 18.0],
            // مشروبات
            ['cat' => 'retail-beverages', 'sku' => 'RC-BEV-001', 'barcode' => '6281000003001', 'name_ar' => 'مياه معدنية 600مل', 'name_en' => 'Mineral Water 600ml', 'price' => 1.5],
            ['cat' => 'retail-beverages', 'sku' => 'RC-BEV-002', 'barcode' => '6281000003002', 'name_ar' => 'عصير برتقال 1 لتر', 'name_en' => 'Orange Juice 1L', 'price' => 8.0],
            ['cat' => 'retail-beverages', 'sku' => 'RC-BEV-003', 'barcode' => '6281000003003', 'name_ar' => 'مشروب غازي 330مل', 'name_en' => 'Soft Drink 330ml', 'price' => 2.5],
            ['cat' => 'retail-beverages', 'sku' => 'RC-BEV-004', 'barcode' => '6281000003004', 'name_ar' => 'طاقة 250مل', 'name_en' => 'Energy Drink 250ml', 'price' => 6.0],
            // ألبان
            ['cat' => 'retail-dairy', 'sku' => 'RC-DRY-001', 'barcode' => '6281000004001', 'name_ar' => 'حليب طازج كامل الدسم 1 لتر', 'name_en' => 'Fresh Full Cream Milk 1L', 'price' => 6.5, 'brand' => 'retail-brand-almarai'],
            ['cat' => 'retail-dairy', 'sku' => 'RC-DRY-002', 'barcode' => '6281000004002', 'name_ar' => 'لبن رائب 1 لتر', 'name_en' => 'Laban 1L', 'price' => 5.5, 'brand' => 'retail-brand-nada'],
            ['cat' => 'retail-dairy', 'sku' => 'RC-DRY-003', 'barcode' => '6281000004003', 'name_ar' => 'جبنة شرائح', 'name_en' => 'Cheese Slices', 'price' => 12.0],
            ['cat' => 'retail-dairy', 'sku' => 'RC-DRY-004', 'barcode' => '6281000004004', 'name_ar' => 'زبادي طبيعي 170غ', 'name_en' => 'Natural Yogurt 170g', 'price' => 2.0],
            // مخبوزات
            ['cat' => 'retail-bakery', 'sku' => 'RC-BKY-001', 'barcode' => '6281000005001', 'name_ar' => 'خبز عربي طازج', 'name_en' => 'Fresh Arabic Bread', 'price' => 1.5],
            ['cat' => 'retail-bakery', 'sku' => 'RC-BKY-002', 'barcode' => '6281000005002', 'name_ar' => 'صامولي', 'name_en' => 'Samoli Roll', 'price' => 1.0],
            ['cat' => 'retail-bakery', 'sku' => 'RC-BKY-003', 'barcode' => '6281000005003', 'name_ar' => 'كرواسون سادة', 'name_en' => 'Plain Croissant', 'price' => 4.0],
            ['cat' => 'retail-bakery', 'sku' => 'RC-BKY-004', 'barcode' => '6281000005004', 'name_ar' => 'كعك بالتمر', 'name_en' => 'Date Cookie', 'price' => 3.0],
            // جوالات
            ['cat' => 'retail-mobiles', 'sku' => 'RC-MOB-001', 'barcode' => '6281000006001', 'name_ar' => 'هاتف ذكي A54 128GB', 'name_en' => 'Smartphone A54 128GB', 'price' => 1299.0, 'brand' => 'retail-brand-samsung'],
            ['cat' => 'retail-mobiles', 'sku' => 'RC-MOB-002', 'barcode' => '6281000006002', 'name_ar' => 'هاتف ذكي 13 128GB', 'name_en' => 'Smartphone 13 128GB', 'price' => 2499.0, 'brand' => 'retail-brand-apple'],
            ['cat' => 'retail-mobiles', 'sku' => 'RC-MOB-003', 'barcode' => '6281000006003', 'name_ar' => 'هاتف اقتصادي 64GB', 'name_en' => 'Budget Phone 64GB', 'price' => 499.0],
            ['cat' => 'retail-mobiles', 'sku' => 'RC-MOB-004', 'barcode' => '6281000006004', 'name_ar' => 'تابلت 10 إنش 64GB', 'name_en' => 'Tablet 10\" 64GB', 'price' => 899.0],
            // إكسسوارات
            ['cat' => 'retail-accessories', 'sku' => 'RC-ACC-001', 'barcode' => '6281000007001', 'name_ar' => 'شاحن سريع USB-C', 'name_en' => 'Fast Charger USB-C', 'price' => 79.0],
            ['cat' => 'retail-accessories', 'sku' => 'RC-ACC-002', 'barcode' => '6281000007002', 'name_ar' => 'سماعة لاسلكية', 'name_en' => 'Wireless Earbuds', 'price' => 149.0],
            ['cat' => 'retail-accessories', 'sku' => 'RC-ACC-003', 'barcode' => '6281000007003', 'name_ar' => 'غطاء حماية شفاف', 'name_en' => 'Clear Phone Case', 'price' => 29.0],
            ['cat' => 'retail-accessories', 'sku' => 'RC-ACC-004', 'barcode' => '6281000007004', 'name_ar' => 'كابل شحن 1م', 'name_en' => 'Charging Cable 1m', 'price' => 25.0],
            // إلكترونيات
            ['cat' => 'retail-electronics', 'sku' => 'RC-ELC-001', 'barcode' => '6281000008001', 'name_ar' => 'سماعات رأس', 'name_en' => 'Headphones', 'price' => 199.0],
            ['cat' => 'retail-electronics', 'sku' => 'RC-ELC-002', 'barcode' => '6281000008002', 'name_ar' => 'ماوس لاسلكي', 'name_en' => 'Wireless Mouse', 'price' => 69.0],
            ['cat' => 'retail-electronics', 'sku' => 'RC-ELC-003', 'barcode' => '6281000008003', 'name_ar' => 'لوحة مفاتيح', 'name_en' => 'Keyboard', 'price' => 89.0],
            ['cat' => 'retail-electronics', 'sku' => 'RC-ELC-004', 'barcode' => '6281000008004', 'name_ar' => 'باور بانك 10000mAh', 'name_en' => 'Power Bank 10000mAh', 'price' => 99.0],
            // ملابس رجالية
            ['cat' => 'retail-clothing-men', 'sku' => 'RC-CLM-001', 'barcode' => '6281000009001', 'name_ar' => 'ثوب رجالي أبيض', 'name_en' => 'Men White Thobe', 'price' => 120.0],
            ['cat' => 'retail-clothing-men', 'sku' => 'RC-CLM-002', 'barcode' => '6281000009002', 'name_ar' => 'تيشيرت قطني', 'name_en' => 'Cotton T-Shirt', 'price' => 49.0],
            ['cat' => 'retail-clothing-men', 'sku' => 'RC-CLM-003', 'barcode' => '6281000009003', 'name_ar' => 'بنطلون جينز', 'name_en' => 'Men Jeans', 'price' => 129.0],
            ['cat' => 'retail-clothing-men', 'sku' => 'RC-CLM-004', 'barcode' => '6281000009004', 'name_ar' => 'قميص رسمي', 'name_en' => 'Formal Shirt', 'price' => 99.0],
            // ملابس نسائية
            ['cat' => 'retail-clothing-women', 'sku' => 'RC-CLW-001', 'barcode' => '6281000010001', 'name_ar' => 'عباية سوداء كلاسيك', 'name_en' => 'Classic Black Abaya', 'price' => 180.0],
            ['cat' => 'retail-clothing-women', 'sku' => 'RC-CLW-002', 'barcode' => '6281000010002', 'name_ar' => 'بلوزة كاجوال', 'name_en' => 'Casual Blouse', 'price' => 79.0],
            ['cat' => 'retail-clothing-women', 'sku' => 'RC-CLW-003', 'barcode' => '6281000010003', 'name_ar' => 'فستان يومي', 'name_en' => 'Day Dress', 'price' => 149.0],
            ['cat' => 'retail-clothing-women', 'sku' => 'RC-CLW-004', 'barcode' => '6281000010004', 'name_ar' => 'طرحة سوداء', 'name_en' => 'Black Scarf', 'price' => 35.0],
            // أحذية
            ['cat' => 'retail-shoes', 'sku' => 'RC-SHO-001', 'barcode' => '6281000011001', 'name_ar' => 'حذاء رياضي رجالي', 'name_en' => 'Men Sports Shoes', 'price' => 249.0, 'brand' => 'retail-brand-nike'],
            ['cat' => 'retail-shoes', 'sku' => 'RC-SHO-002', 'barcode' => '6281000011002', 'name_ar' => 'صندل رجالي', 'name_en' => 'Men Sandals', 'price' => 89.0],
            ['cat' => 'retail-shoes', 'sku' => 'RC-SHO-003', 'barcode' => '6281000011003', 'name_ar' => 'حذاء نسائي كاجوال', 'name_en' => 'Women Casual Shoes', 'price' => 159.0],
            ['cat' => 'retail-shoes', 'sku' => 'RC-SHO-004', 'barcode' => '6281000011004', 'name_ar' => 'شبشب منزلي', 'name_en' => 'House Slippers', 'price' => 29.0],
            // صيدليات
            ['cat' => 'retail-pharmacy', 'sku' => 'RC-PHR-001', 'barcode' => '6281000012001', 'name_ar' => 'مسكن ألم أقراص', 'name_en' => 'Pain Relief Tablets', 'price' => 12.0],
            ['cat' => 'retail-pharmacy', 'sku' => 'RC-PHR-002', 'barcode' => '6281000012002', 'name_ar' => 'فيتامين سي', 'name_en' => 'Vitamin C', 'price' => 25.0],
            ['cat' => 'retail-pharmacy', 'sku' => 'RC-PHR-003', 'barcode' => '6281000012003', 'name_ar' => 'كمامات طبية 50حبة', 'name_en' => 'Medical Masks 50pcs', 'price' => 15.0],
            ['cat' => 'retail-pharmacy', 'sku' => 'RC-PHR-004', 'barcode' => '6281000012004', 'name_ar' => 'معقم يد 500مل', 'name_en' => 'Hand Sanitizer 500ml', 'price' => 18.0],
            // مطاعم
            ['cat' => 'retail-restaurants', 'sku' => 'RC-RST-001', 'barcode' => '6281000013001', 'name_ar' => 'برجر لحم كلاسيك', 'name_en' => 'Classic Beef Burger', 'price' => 28.0],
            ['cat' => 'retail-restaurants', 'sku' => 'RC-RST-002', 'barcode' => '6281000013002', 'name_ar' => 'دجاج مشوي وجبة', 'name_en' => 'Grilled Chicken Meal', 'price' => 32.0],
            ['cat' => 'retail-restaurants', 'sku' => 'RC-RST-003', 'barcode' => '6281000013003', 'name_ar' => 'بيتزا مارغريتا', 'name_en' => 'Margherita Pizza', 'price' => 35.0],
            ['cat' => 'retail-restaurants', 'sku' => 'RC-RST-004', 'barcode' => '6281000013004', 'name_ar' => 'شاورما دجاج', 'name_en' => 'Chicken Shawarma', 'price' => 18.0],
            ['cat' => 'retail-restaurants', 'sku' => 'RC-RST-005', 'barcode' => '6281000013005', 'name_ar' => 'بطاطس مقلية', 'name_en' => 'French Fries', 'price' => 10.0],
            // كافيه
            ['cat' => 'retail-cafe', 'sku' => 'RC-CAF-001', 'barcode' => '6281000014001', 'name_ar' => 'قهوة أمريكية', 'name_en' => 'Americano', 'price' => 12.0],
            ['cat' => 'retail-cafe', 'sku' => 'RC-CAF-002', 'barcode' => '6281000014002', 'name_ar' => 'لاتيه', 'name_en' => 'Latte', 'price' => 16.0],
            ['cat' => 'retail-cafe', 'sku' => 'RC-CAF-003', 'barcode' => '6281000014003', 'name_ar' => 'كابتشينو', 'name_en' => 'Cappuccino', 'price' => 15.0],
            ['cat' => 'retail-cafe', 'sku' => 'RC-CAF-004', 'barcode' => '6281000014004', 'name_ar' => 'شاي أخضر', 'name_en' => 'Green Tea', 'price' => 10.0],
            // منزلية
            ['cat' => 'retail-household', 'sku' => 'RC-HSH-001', 'barcode' => '6281000015001', 'name_ar' => 'منظف أرضيات 3 لتر', 'name_en' => 'Floor Cleaner 3L', 'price' => 18.0],
            ['cat' => 'retail-household', 'sku' => 'RC-HSH-002', 'barcode' => '6281000015002', 'name_ar' => 'مناديل مطبخ', 'name_en' => 'Kitchen Tissues', 'price' => 9.0],
            ['cat' => 'retail-household', 'sku' => 'RC-HSH-003', 'barcode' => '6281000015003', 'name_ar' => 'أكياس قمامة كبيرة', 'name_en' => 'Large Trash Bags', 'price' => 14.0],
            ['cat' => 'retail-household', 'sku' => 'RC-HSH-004', 'barcode' => '6281000015004', 'name_ar' => 'منظف زجاج', 'name_en' => 'Glass Cleaner', 'price' => 11.0],
            // عناية شخصية
            ['cat' => 'retail-personal-care', 'sku' => 'RC-PCR-001', 'barcode' => '6281000016001', 'name_ar' => 'شامبو 400مل', 'name_en' => 'Shampoo 400ml', 'price' => 22.0],
            ['cat' => 'retail-personal-care', 'sku' => 'RC-PCR-002', 'barcode' => '6281000016002', 'name_ar' => 'معجون أسنان', 'name_en' => 'Toothpaste', 'price' => 8.0],
            ['cat' => 'retail-personal-care', 'sku' => 'RC-PCR-003', 'barcode' => '6281000016003', 'name_ar' => 'صابون سائل لليدين', 'name_en' => 'Hand Soap', 'price' => 10.0],
            ['cat' => 'retail-personal-care', 'sku' => 'RC-PCR-004', 'barcode' => '6281000016004', 'name_ar' => 'مزيل عرق', 'name_en' => 'Deodorant', 'price' => 16.0],
            // أطفال
            ['cat' => 'retail-baby', 'sku' => 'RC-BBY-001', 'barcode' => '6281000017001', 'name_ar' => 'حفاضات مقاس 4', 'name_en' => 'Diapers Size 4', 'price' => 65.0],
            ['cat' => 'retail-baby', 'sku' => 'RC-BBY-002', 'barcode' => '6281000017002', 'name_ar' => 'مناديل مبللة للأطفال', 'name_en' => 'Baby Wipes', 'price' => 14.0],
            ['cat' => 'retail-baby', 'sku' => 'RC-BBY-003', 'barcode' => '6281000017003', 'name_ar' => 'حليب أطفال مرحلة 1', 'name_en' => 'Infant Formula Stage 1', 'price' => 85.0],
            ['cat' => 'retail-baby', 'sku' => 'RC-BBY-004', 'barcode' => '6281000017004', 'name_ar' => 'رضّاعة بلاستيك', 'name_en' => 'Baby Bottle', 'price' => 25.0],
            // سيارات
            ['cat' => 'retail-automotive', 'sku' => 'RC-ATV-001', 'barcode' => '6281000018001', 'name_ar' => 'زيت محرك 5W-30', 'name_en' => 'Engine Oil 5W-30', 'price' => 95.0],
            ['cat' => 'retail-automotive', 'sku' => 'RC-ATV-002', 'barcode' => '6281000018002', 'name_ar' => 'منظف زجاج سيارة', 'name_en' => 'Car Glass Cleaner', 'price' => 18.0],
            ['cat' => 'retail-automotive', 'sku' => 'RC-ATV-003', 'barcode' => '6281000018003', 'name_ar' => 'معطر سيارة', 'name_en' => 'Car Freshener', 'price' => 12.0],
            ['cat' => 'retail-automotive', 'sku' => 'RC-ATV-004', 'barcode' => '6281000018004', 'name_ar' => 'شاحن سيارة USB', 'name_en' => 'Car USB Charger', 'price' => 35.0],
            // رياضة
            ['cat' => 'retail-sports', 'sku' => 'RC-SPT-001', 'barcode' => '6281000019001', 'name_ar' => 'كرة قدم مقاس 5', 'name_en' => 'Football Size 5', 'price' => 79.0],
            ['cat' => 'retail-sports', 'sku' => 'RC-SPT-002', 'barcode' => '6281000019002', 'name_ar' => 'سجادة يوغا', 'name_en' => 'Yoga Mat', 'price' => 69.0],
            ['cat' => 'retail-sports', 'sku' => 'RC-SPT-003', 'barcode' => '6281000019003', 'name_ar' => 'دمبل 5 كجم', 'name_en' => 'Dumbbell 5kg', 'price' => 55.0],
            ['cat' => 'retail-sports', 'sku' => 'RC-SPT-004', 'barcode' => '6281000019004', 'name_ar' => 'قارورة رياضة 750مل', 'name_en' => 'Sports Bottle 750ml', 'price' => 29.0],
            // مكتبية
            ['cat' => 'retail-office', 'sku' => 'RC-OFC-001', 'barcode' => '6281000020001', 'name_ar' => 'دفتر A4 مسطر', 'name_en' => 'A4 Lined Notebook', 'price' => 8.0],
            ['cat' => 'retail-office', 'sku' => 'RC-OFC-002', 'barcode' => '6281000020002', 'name_ar' => 'قلم حبر أزرق (علبة 12)', 'name_en' => 'Blue Pens Pack 12', 'price' => 12.0],
            ['cat' => 'retail-office', 'sku' => 'RC-OFC-003', 'barcode' => '6281000020003', 'name_ar' => 'ورق طباعة A4 500 ورقة', 'name_en' => 'A4 Paper 500 sheets', 'price' => 22.0],
            ['cat' => 'retail-office', 'sku' => 'RC-OFC-004', 'barcode' => '6281000020004', 'name_ar' => 'دباسة مكتبية', 'name_en' => 'Office Stapler', 'price' => 18.0],
            // مصنع — مواد خام
            ['cat' => 'retail-factory-raw', 'sku' => 'RC-FRAW-001', 'barcode' => '6281000021001', 'name_ar' => 'صاج مجلفن لفة', 'name_en' => 'Galvanized Steel Coil', 'price' => 850.0],
            ['cat' => 'retail-factory-raw', 'sku' => 'RC-FRAW-002', 'barcode' => '6281000021002', 'name_ar' => 'بلاستيك خام HDPE 25كجم', 'name_en' => 'HDPE Resin 25kg', 'price' => 180.0],
            ['cat' => 'retail-factory-raw', 'sku' => 'RC-FRAW-003', 'barcode' => '6281000021003', 'name_ar' => 'أسمنت بورتلاند 50كجم', 'name_en' => 'Portland Cement 50kg', 'price' => 18.0],
            ['cat' => 'retail-factory-raw', 'sku' => 'RC-FRAW-004', 'barcode' => '6281000021004', 'name_ar' => 'خشب MDF لوح', 'name_en' => 'MDF Board', 'price' => 95.0],
            // مصنع — تعبئة
            ['cat' => 'retail-factory-packaging', 'sku' => 'RC-FPKG-001', 'barcode' => '6281000022001', 'name_ar' => 'كراتين شحن متوسطة', 'name_en' => 'Medium Shipping Cartons', 'price' => 2.5],
            ['cat' => 'retail-factory-packaging', 'sku' => 'RC-FPKG-002', 'barcode' => '6281000022002', 'name_ar' => 'شريط لاصق تغليف', 'name_en' => 'Packing Tape', 'price' => 8.0],
            ['cat' => 'retail-factory-packaging', 'sku' => 'RC-FPKG-003', 'barcode' => '6281000022003', 'name_ar' => 'أكياس بلاستيك صناعي', 'name_en' => 'Industrial Plastic Bags', 'price' => 45.0],
            ['cat' => 'retail-factory-packaging', 'sku' => 'RC-FPKG-004', 'barcode' => '6281000022004', 'name_ar' => 'فيلم تغليف حراري', 'name_en' => 'Shrink Wrap Film', 'price' => 120.0],
            // مصنع — أدوات
            ['cat' => 'retail-factory-tools', 'sku' => 'RC-FTOL-001', 'barcode' => '6281000023001', 'name_ar' => 'مثقاب كهربائي صناعي', 'name_en' => 'Industrial Drill', 'price' => 450.0],
            ['cat' => 'retail-factory-tools', 'sku' => 'RC-FTOL-002', 'barcode' => '6281000023002', 'name_ar' => 'مفتاح ربط طقم', 'name_en' => 'Wrench Set', 'price' => 160.0],
            ['cat' => 'retail-factory-tools', 'sku' => 'RC-FTOL-003', 'barcode' => '6281000023003', 'name_ar' => 'منشار معدني', 'name_en' => 'Metal Hacksaw', 'price' => 55.0],
            ['cat' => 'retail-factory-tools', 'sku' => 'RC-FTOL-004', 'barcode' => '6281000023004', 'name_ar' => 'مقياس رقمي', 'name_en' => 'Digital Caliper', 'price' => 89.0],
            // مصنع — سلامة
            ['cat' => 'retail-factory-safety', 'sku' => 'RC-FSAF-001', 'barcode' => '6281000024001', 'name_ar' => 'خوذة سلامة', 'name_en' => 'Safety Helmet', 'price' => 35.0],
            ['cat' => 'retail-factory-safety', 'sku' => 'RC-FSAF-002', 'barcode' => '6281000024002', 'name_ar' => 'قفازات مقاومة للحرارة', 'name_en' => 'Heat-Resistant Gloves', 'price' => 28.0],
            ['cat' => 'retail-factory-safety', 'sku' => 'RC-FSAF-003', 'barcode' => '6281000024003', 'name_ar' => 'نظارة واقية', 'name_en' => 'Safety Goggles', 'price' => 22.0],
            ['cat' => 'retail-factory-safety', 'sku' => 'RC-FSAF-004', 'barcode' => '6281000024004', 'name_ar' => 'سترة عاكسة', 'name_en' => 'High-Vis Vest', 'price' => 30.0],
        ];
    }

    private function upsertCategory(string $slug, string $nameAr, string $nameEn, int $sort): int
    {
        $uuid = $this->uuid('cat:' . $slug);
        $this->exec(
            'INSERT INTO categories (uuid, parent_id, slug, depth, path, sort_order, status)
             SELECT ' . $this->q($uuid) . ', NULL, ' . $this->q($slug) . ', 0, ' . $this->q('/' . $slug) . ', '
            . (int) $sort . ', "active"
             WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = ' . $this->q($slug) . ' AND deleted_at IS NULL)'
        );

        $id = $this->fetchId('SELECT id FROM categories WHERE slug = :s AND deleted_at IS NULL LIMIT 1', ['s' => $slug]);
        if ($id < 1) {
            return 0;
        }

        $this->upsertCategoryTranslation($id, 'ar', $nameAr);
        $this->upsertCategoryTranslation($id, 'en', $nameEn);

        return $id;
    }

    private function upsertCategoryTranslation(int $categoryId, string $lang, string $name): void
    {
        $uuid = $this->uuid('cat-tr:' . $categoryId . ':' . $lang);
        $this->exec(
            'INSERT INTO category_translations (uuid, category_id, language_code, name)
             SELECT ' . $this->q($uuid) . ', ' . $categoryId . ', ' . $this->q($lang) . ', ' . $this->q($name) . '
             WHERE NOT EXISTS (
                SELECT 1 FROM category_translations
                WHERE category_id = ' . $categoryId . ' AND language_code = ' . $this->q($lang) . ' AND deleted_at IS NULL
             )'
        );
        $this->exec(
            'UPDATE category_translations
             SET name = ' . $this->q($name) . '
             WHERE category_id = ' . $categoryId . '
               AND language_code = ' . $this->q($lang) . '
               AND deleted_at IS NULL'
        );
    }

    private function upsertBrand(string $slug, string $nameAr, string $nameEn): int
    {
        $uuid = $this->uuid('brand:' . $slug);
        $this->exec(
            'INSERT INTO brands (uuid, slug, country_code, status)
             SELECT ' . $this->q($uuid) . ', ' . $this->q($slug) . ', "SA", "active"
             WHERE NOT EXISTS (SELECT 1 FROM brands WHERE slug = ' . $this->q($slug) . ' AND deleted_at IS NULL)'
        );
        $id = $this->fetchId('SELECT id FROM brands WHERE slug = :s AND deleted_at IS NULL LIMIT 1', ['s' => $slug]);
        if ($id < 1) {
            return 0;
        }
        foreach (['ar' => $nameAr, 'en' => $nameEn] as $lang => $name) {
            $tuuid = $this->uuid('brand-tr:' . $slug . ':' . $lang);
            $this->exec(
                'INSERT INTO brand_translations (uuid, brand_id, language_code, name)
                 SELECT ' . $this->q($tuuid) . ', ' . $id . ', ' . $this->q($lang) . ', ' . $this->q($name) . '
                 WHERE NOT EXISTS (
                    SELECT 1 FROM brand_translations
                    WHERE brand_id = ' . $id . ' AND language_code = ' . $this->q($lang) . ' AND deleted_at IS NULL
                 )'
            );
            $this->exec(
                'UPDATE brand_translations
                 SET name = ' . $this->q($name) . '
                 WHERE brand_id = ' . $id . '
                   AND language_code = ' . $this->q($lang) . '
                   AND deleted_at IS NULL'
            );
        }

        return $id;
    }

    /** @param array{cat:string,sku:string,barcode:string,name_ar:string,name_en:string,price:float,brand?:string} $row */
    private function upsertProduct(array $row, int $categoryId, int $unitId): void
    {
        $sku = $row['sku'];
        $existing = $this->fetchId('SELECT id FROM products WHERE sku = :s AND deleted_at IS NULL LIMIT 1', ['s' => $sku]);
        if ($existing > 0) {
            // Always re-bind category — null/wrong category_id collapses guest menu to «عام».
            $this->exec(
                'UPDATE products
                 SET category_id = ' . (int) $categoryId . ',
                     status = "published",
                     primary_barcode = ' . $this->q((string) $row['barcode']) . '
                 WHERE id = ' . (int) $existing . ' AND deleted_at IS NULL'
            );
            $this->ensurePrice($existing, (float) $row['price']);
            $this->ensureProductTranslations($existing, $row['name_ar'], $row['name_en']);

            return;
        }

        $brandId = null;
        if (!empty($row['brand'])) {
            $bid = $this->fetchId('SELECT id FROM brands WHERE slug = :s AND deleted_at IS NULL LIMIT 1', ['s' => (string) $row['brand']]);
            $brandId = $bid > 0 ? $bid : null;
        }

        $uuid = $this->uuid('product:' . $sku);
        $barcode = $row['barcode'];
        $brandSql = $brandId !== null ? (string) $brandId : 'NULL';
        $this->exec(
            'INSERT INTO products (
                uuid, sku, brand_id, category_id, unit_id, primary_barcode,
                status, publish_at, published_at, approved_at, tax_class
             ) VALUES (
                ' . $this->q($uuid) . ',
                ' . $this->q($sku) . ',
                ' . $brandSql . ',
                ' . $categoryId . ',
                ' . $unitId . ',
                ' . $this->q($barcode) . ',
                "published",
                CURRENT_TIMESTAMP(6),
                CURRENT_TIMESTAMP(6),
                CURRENT_TIMESTAMP(6),
                "standard"
             )'
        );

        $productId = $this->fetchId('SELECT id FROM products WHERE sku = :s AND deleted_at IS NULL LIMIT 1', ['s' => $sku]);
        if ($productId < 1) {
            return;
        }

        $this->ensureProductTranslations($productId, $row['name_ar'], $row['name_en']);
        $this->ensurePrice($productId, (float) $row['price']);
    }

    private function ensureProductTranslations(int $productId, string $nameAr, string $nameEn): void
    {
        foreach (['ar' => $nameAr, 'en' => $nameEn] as $lang => $name) {
            $uuid = $this->uuid('ptr:' . $productId . ':' . $lang);
            $this->exec(
                'INSERT INTO product_translations (uuid, product_id, language_code, name, short_description)
                 SELECT ' . $this->q($uuid) . ', ' . $productId . ', ' . $this->q($lang) . ', '
                . $this->q($name) . ', ' . $this->q($name) . '
                 WHERE NOT EXISTS (
                    SELECT 1 FROM product_translations
                    WHERE product_id = ' . $productId . ' AND language_code = ' . $this->q($lang) . ' AND deleted_at IS NULL
                 )'
            );
            $this->exec(
                'UPDATE product_translations
                 SET name = ' . $this->q($name) . ',
                     short_description = ' . $this->q($name) . '
                 WHERE product_id = ' . $productId . '
                   AND language_code = ' . $this->q($lang) . '
                   AND deleted_at IS NULL'
            );
        }
    }

    private function ensurePrice(int $productId, float $price): void
    {
        $uuid = $this->uuid('price:' . $productId . ':SAR');
        $amount = number_format(max(0, $price), 4, '.', '');
        $this->exec(
            'INSERT INTO product_prices (uuid, product_id, currency_code, cost, msrp, default_price, is_active)
             SELECT ' . $this->q($uuid) . ', ' . $productId . ', "SAR", '
            . $amount . ', ' . $amount . ', ' . $amount . ', 1
             WHERE NOT EXISTS (
                SELECT 1 FROM product_prices
                WHERE product_id = ' . $productId . ' AND currency_code = "SAR" AND deleted_at IS NULL
             )'
        );
        $this->exec(
            'UPDATE product_prices
             SET default_price = ' . $amount . ', msrp = ' . $amount . ', is_active = 1
             WHERE product_id = ' . $productId . ' AND currency_code = "SAR" AND deleted_at IS NULL'
        );
    }


    private function exec(string $sql): void
    {
        $statements = preg_split('/;\s*\n/', $sql) ?: [];
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            try {
                $this->pdo->exec($statement);
            } catch (\PDOException $e) {
                $code = (int) $e->getCode();
                $msg = $e->getMessage();
                $benign = in_array($code, [1050, 1060, 1061, 1062, 1091], true)
                    || str_contains($msg, 'Duplicate')
                    || str_contains($msg, 'already exists');
                if (!$benign) {
                    throw $e;
                }
            }
        }
    }

    private function unitIdPcs(): int
    {
        return $this->fetchId('SELECT id FROM units WHERE code = :c AND deleted_at IS NULL LIMIT 1', ['c' => 'PCS']);
    }

    /** @param array<string, scalar> $params */
    private function fetchId(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return is_array($row) ? (int) ($row['id'] ?? 0) : 0;
    }

    private function q(string $value): string
    {
        return $this->pdo->quote($value);
    }

    private function uuid(string $seed): string
    {
        $h = md5('rateb-retail-seed-v1|' . $seed);

        return sprintf(
            '%s-%s-4%s-8%s-%s',
            substr($h, 0, 8),
            substr($h, 8, 4),
            substr($h, 13, 3),
            substr($h, 17, 3),
            substr($h, 20, 12)
        );
    }
}

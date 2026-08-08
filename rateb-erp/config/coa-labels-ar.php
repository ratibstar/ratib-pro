<?php
declare(strict_types=1);

/** code => [name_en, name_ar] — Saudi COA + legacy aliases for repair */
return [
    // Roots
    '100' => ['Assets', 'الأصول'],
    '200' => ['Liabilities', 'الخصوم'],
    '300' => ['Equity', 'حقوق الملكية'],
    '400' => ['Revenue', 'الإيرادات'],
    '500' => ['Expenses', 'المصروفات'],

    // Assets
    '101' => ['Current Assets', 'الأصول المتداولة'],
    '10101' => ['Cash and Cash Equivalents', 'النقدية ومافي حكمها'],
    '1010101' => ['Petty Cash', 'العهد النقدية'],
    '1010104' => ['Customer Transfer Differences', 'الفروقات حوالات العملاء'],
    '1010105' => ['SiFi Wallet', 'محفظة SiFi'],
    '10102' => ['Current Bank Accounts', 'البنوك الجارية'],
    '10103' => ['Payment Gateways', 'بوابات الدفع'],
    '10104' => ['Accounts Receivable', 'الذمم المدينة'],
    '1010410' => ['VAT Recoverable', 'ضريبة قابلة للاسترداد'],
    '1010411' => ['Due From Branches', 'مدينون للفروع'],
    '10106' => ['Inventory', 'المخزون'],
    '10107' => ['Prepaid Expenses', 'المصروفات المدفوعة مقدماً'],
    '10108' => ['Payment Gateway Differences', 'فروقات بوابات الدفع'],
    '10109' => ['Temporary Settlements', 'تسويات مؤقته'],
    '10110' => ['Advances / Prepayments', 'دفعات مقدمة'],
    '102' => ['Non-current Assets', 'الأصول غير المتداولة'],
    '10201' => ['Fixed Assets', 'الأصول الثابتة'],
    '1020101' => ['Equipment', 'معدات'],
    '1020102' => ['Vehicles', 'مركبات'],
    '1020103' => ['Buildings', 'مباني'],
    '1020109' => ['Accumulated Depreciation', 'مجمع الإهلاك'],
    '1020113' => ['Current Account', 'الجاري'],

    // Liabilities
    '201' => ['Current Liabilities', 'الخصوم المتداولة'],
    '20101' => ['Accounts Payable', 'ذمم دائنة'],
    '20102' => ['Customer Advances', 'دفعات مقدمة من العملاء'],
    '20103' => ['VAT Payable', 'ضريبة مستحقة'],
    '20104' => ['Accrued Expenses', 'مصروفات مستحقة'],
    '20105' => ['Salaries Payable', 'رواتب مستحقة'],
    '20106' => ['Short-term Loans', 'قروض قصيرة الأجل'],
    '20107' => ['Due To Branches', 'دائنون للفروع'],
    '202' => ['Non-current Liabilities', 'الخصوم غير المتداولة'],

    // Equity
    '301' => ['Share Capital', 'رأس المال'],
    '302' => ['Statutory Reserve', 'احتياطي نظامي'],
    '303' => ['Other Reserves', 'احتياطيات أخرى'],
    '304' => ['Retained Earnings (Accumulated Losses)', 'الأرباح المبقاة (الخسائر المتراكمة)'],
    '305' => ['Current Period Profit (Loss)', 'أرباح (خسائر) الفترة الحالية'],
    '306' => ['Dividends', 'توزيعات الأرباح'],

    // Revenue
    '401' => ['Operating Revenue', 'الإيرادات التشغيلية'],
    '40102' => ['Service Revenue', 'إيرادات الخدمات'],
    '40109' => ['Sales Returns & Allowances', 'مردودات ومسموحات المبيعات'],
    '402' => ['Other Income', 'الإيرادات الأخرى'],

    // Expenses
    '501' => ['Cost of Sales', 'تكلفة المبيعات'],
    '502' => ['Operating Expenses', 'المصروفات التشغيلية'],
    '50201' => ['General & Administrative Expenses', 'المصروفات الإدارية والعمومية'],
    '5020101' => ['Salaries & Wages', 'الرواتب والأجور'],
    '5020102' => ['Rent Expense', 'مصروف الإيجار'],
    '5020103' => ['Utilities', 'مرافق (كهرباء، ماء، …)'],
    '5020104' => ['Depreciation Expense', 'مصروف الإهلاك'],
    '50202' => ['Procurement Expense', 'مصروفات المشتريات'],
    '50203' => ['Marketing & Sales', 'مصروفات تسويق ومبيعات'],
    '503' => ['Non-operating Expenses', 'المصروفات غير التشغيلية'],
    '50301' => ['Bank & Finance Charges', 'عمولات بنكية ومالية'],
    '504' => ['Brokerage & Follow-up Fees', 'اتعاب السعي والتعقيب'],

    // Legacy aliases (pre-Saudi tree)
    '1000' => ['Assets', 'الأصول'],
    '1100' => ['Petty Cash', 'العهد النقدية'],
    '1150' => ['Current Bank Accounts', 'البنوك الجارية'],
    '1200' => ['Accounts Receivable', 'الذمم المدينة'],
    '1210' => ['VAT Recoverable', 'ضريبة قابلة للاسترداد'],
    '1220' => ['Advances / Prepayments', 'دفعات مقدمة'],
    '1300' => ['Inventory', 'المخزون'],
    '1400' => ['Prepaid Expenses', 'المصروفات المدفوعة مقدماً'],
    '1500' => ['Fixed Assets', 'الأصول الثابتة'],
    '2000' => ['Liabilities', 'الخصوم'],
    '2100' => ['Accounts Payable', 'ذمم دائنة'],
    '2200' => ['VAT Payable', 'ضريبة مستحقة'],
    '3000' => ['Equity', 'حقوق الملكية'],
    '3100' => ['Retained Earnings (Accumulated Losses)', 'الأرباح المبقاة (الخسائر المتراكمة)'],
    '3200' => ['Share Capital', 'رأس المال'],
    '3300' => ['Current Period Profit (Loss)', 'أرباح (خسائر) الفترة الحالية'],
    '4000' => ['Revenue', 'الإيرادات'],
    '4100' => ['Operating Revenue', 'الإيرادات التشغيلية'],
    '5000' => ['Expenses', 'المصروفات'],
    '5200' => ['Cost of Sales', 'تكلفة المبيعات'],
    '5800' => ['General & Administrative Expenses', 'المصروفات الإدارية والعمومية'],
];

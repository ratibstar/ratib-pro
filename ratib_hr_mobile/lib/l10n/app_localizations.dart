/// Manual localization — Arabic RTL-first, English supported.
library;

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';

class AppLocalizations {
  AppLocalizations(this.locale);

  final Locale locale;

  static AppLocalizations of(BuildContext context) {
    final value = Localizations.of<AppLocalizations>(context, AppLocalizations);
    assert(value != null, 'No AppLocalizations found in context');
    return value!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  static const supportedLocales = [Locale('ar'), Locale('en')];

  bool get isArabic => locale.languageCode == 'ar';

  static const Map<String, Map<String, String>> _text = {
    'ar': {
      'appTitle': 'راتب — الموارد البشرية',
      'tabHome': 'الرئيسية',
      'tabAttendance': 'الحضور',
      'tabLeave': 'الإجازات',
      'tabRequests': 'الطلبات',
      'tabMore': 'المزيد',
      'navLogin': 'تسجيل الدخول',
      'navHome': 'لوحة الموظف',
      'navAttendance': 'الحضور',
      'navCheckIn': 'حضور',
      'navCheckOut': 'انصراف',
      'navAttendanceHistory': 'سجل الحضور',
      'navLeaveBalance': 'رصيد الإجازات',
      'navApplyLeave': 'طلب إجازة',
      'navLeaveStatus': 'حالة الإجازة',
      'navPermissionRequests': 'طلبات الاستئذان',
      'navEmployeeRequests': 'طلبات الموظف',
      'navDocuments': 'المستندات',
      'navPayslips': 'كشوف الرواتب',
      'navNotifications': 'الإشعارات',
      'navRatings': 'التقييمات',
      'navInquiries': 'الشكاوى والاستفسارات',
      'navPayments': 'طرق الدفع',
      'navSettings': 'الإعدادات',
      'navProfile': 'ملفي',
      'navApprovals': 'الموافقات',
      'phase0Placeholder': 'مرحلة ٠ — واجهة فقط. لا منطق أعمال بعد.',
      'phase0Subtitle': 'المصدر الوحيد للحقيقة: نظام راتب ERP',
      'language': 'اللغة',
      'arabic': 'العربية',
      'english': 'English',
      'continueDemo': 'متابعة الهيكل (مرحلة ٠)',
      'more': 'المزيد',
      'leave': 'الإجازات',
      'requests': 'الطلبات',
      'attendance': 'الحضور',
      'loginSubtitle': 'تسجيل الدخول بحساب راتب ERP',
      'loginEmailLabel': 'البريد الإلكتروني',
      'loginEmailHint': 'email@company.com',
      'loginPasswordLabel': 'كلمة المرور',
      'loginSubmit': 'تسجيل الدخول',
      'loginFieldsRequired': 'يرجى إدخال البريد وكلمة المرور',
      'loginConfigMissing': 'لم يُضبط عنوان ERP (ERP_BASE_URL)',
      'loginNetworkError': 'تعذر الاتصال بالخادم',
      'loginInvalidCredentials': 'بيانات الدخول غير صحيحة',
      'loginForbidden': 'الوصول مرفوض',
      'loginCompanyAccessDenied': 'الشركة غير مفعّلة أو الاشتراك منتهٍ',
      'loginNoCompany': 'لا توجد شركة مرتبطة بهذا الحساب',
      'loginMobileDisabled': 'تطبيق الجوال غير مفعّل لهذه الشركة',
      'loginRateLimited': 'محاولات كثيرة. حاول لاحقاً',
      'loginFailed': 'فشل تسجيل الدخول',
      'loginErpOnlyHint': 'المصادقة عبر نظام راتب ERP فقط',
      'loginEmployeeUnbound': 'لا يوجد موظف مرتبط بهذا الحساب',
      'loginEmployeeAmbiguous': 'أكثر من موظف مرتبط بهذا الحساب',
      'loginPlatformSuperAdmin':
          'حساب مشرف المنصة لا يدعم تطبيق الجوال — استخدم حساب شركة مرتبط بموظف',
      'signOut': 'تسجيل الخروج',
      'homeLoading': 'جاري تحميل لوحة الموظف…',
      'homeLoadFailed': 'تعذر تحميل الرئيسية',
      'homeRetry': 'إعادة المحاولة',
      'homeTodayAttendance': 'حضور اليوم',
      'homeLeaveBalance': 'رصيد الإجازات',
      'homeRecentNotifications': 'أحدث الإشعارات',
      'homeQuickActions': 'إجراءات سريعة',
      'homeNoAttendanceToday': 'لا يوجد سجل حضور لهذا اليوم',
      'homeNoLeaveBalances': 'لا توجد أرصدة إجازات',
      'homeNoNotifications': 'لا توجد إشعارات',
      'homeEntitled': 'المستحق',
      'homeUsed': 'المستخدم',
      'homePendingRequests': 'طلبات معلّقة',
      'homeNoPendingRequests': 'لا توجد طلبات معلّقة',
      'homeUnreadNotifications': 'إشعارات غير مقروءة',
      'homePayrollSummary': 'ملخص الرواتب',
      'homePayrollPlaceholder': 'تفاصيل الراتب متاحة عند تفعيل وحدة الرواتب',
      'genericLoading': 'جاري التحميل…',
      'genericLoadFailed': 'تعذر التحميل',
      'requestsEmpty': 'لا توجد طلبات',
      'requestDetailTitle': 'تفاصيل الطلب',
      'requestStatus': 'الحالة',
      'requestType': 'النوع',
      'requestNumber': 'الرقم',
      'requestDate': 'التاريخ',
      'requestHistory': 'السجل',
      'requestHistoryEmpty': 'لا يوجد سجل محادثة بعد',
      'notifMarkAllRead': 'تعليم الكل كمقروء',
      'notifCatAll': 'الكل',
      'notifCatGeneral': 'عام',
      'notifCatAttendance': 'حضور',
      'notifCatLeave': 'إجازات',
      'notifCatPayroll': 'رواتب',
      'notifCatSystem': 'نظام',
      'notifCatCustomer': 'عملاء',
      'ratingsScore': 'درجة الأداء',
      'ratingsMonthly': 'التقييم الشهري',
      'ratingsNoMonthly': 'لا يوجد تقييم شهري',
      'ratingsKpi': 'ملخص مؤشرات الأداء',
      'ratingsNoKpi': 'لا توجد مؤشرات بعد',
      'ratingsReviews': 'التقييمات',
      'ratingsEmpty': 'لا توجد تقييمات',
      'inquirySubmit': 'إرسال',
      'inquiryTypeInquiry': 'استفسار',
      'inquiryTypeComplaint': 'شكوى',
      'inquiryMessageHint': 'اكتب رسالتك…',
      'inquiryMessageRequired': 'الرسالة مطلوبة',
      'inquirySubmitted': 'تم الإرسال',
      'inquiryHistory': 'السجل',
      'inquiryEmpty': 'لا توجد شكاوى أو استفسارات',
      'paymentsSalary': 'معلومات صرف الراتب',
      'paymentsBanks': 'الحسابات البنكية',
      'paymentsWallet': 'المحفظة',
      'paymentsGateways': 'بوابات الدفع (مستقبلاً)',
      'paymentsUnavailable': 'طرق الدفع غير مفعّلة لهذه الشركة بعد',
      'paymentsReady': 'بيانات الدفع متاحة',
      'paymentsBanksPlaceholder': 'سيتم عرض الحسابات البنكية عند توفرها من ERP',
      'paymentsWalletPlaceholder': 'المحفظة غير متاحة حالياً',
      'paymentsGatewaysPlaceholder': 'لا معالجة دفع في هذه المرحلة',
      'settingsPreferences': 'التفضيلات',
      'settingsTheme': 'المظهر',
      'settingsThemeSystem': 'حسب النظام',
      'settingsThemeLight': 'فاتح',
      'settingsThemeDark': 'داكن',
      'settingsNotifications': 'الإشعارات',
      'settingsBiometric': 'تسجيل الدخول بالبصمة',
      'settingsAccount': 'الحساب',
      'settingsChangePassword': 'تغيير كلمة المرور',
      'settingsCurrentPassword': 'كلمة المرور الحالية',
      'settingsNewPassword': 'كلمة المرور الجديدة',
      'settingsCancel': 'إلغاء',
      'settingsSave': 'حفظ',
      'settingsClose': 'إغلاق',
      'settingsPasswordChanged': 'تم تغيير كلمة المرور',
      'settingsAboutSection': 'حول التطبيق',
      'settingsAbout': 'حول',
      'settingsPrivacy': 'سياسة الخصوصية',
      'settingsTerms': 'الشروط والأحكام',
      'settingsPrivacyBody':
          'تُدار بياناتك وفق سياسات الخصوصية الخاصة بشركتك ونظام راتب ERP.',
      'settingsTermsBody':
          'باستخدام التطبيق فإنك توافق على شروط الاستخدام الخاصة بشركتك ونظام راتب ERP.',
    },
    'en': {
      'appTitle': 'RATIB HR',
      'tabHome': 'Home',
      'tabAttendance': 'Attendance',
      'tabLeave': 'Leave',
      'tabRequests': 'Requests',
      'tabMore': 'More',
      'navLogin': 'Sign in',
      'navHome': 'Employee home',
      'navAttendance': 'Attendance',
      'navCheckIn': 'Check in',
      'navCheckOut': 'Check out',
      'navAttendanceHistory': 'Attendance history',
      'navLeaveBalance': 'Leave balance',
      'navApplyLeave': 'Apply leave',
      'navLeaveStatus': 'Leave status',
      'navPermissionRequests': 'Permission requests',
      'navEmployeeRequests': 'Employee requests',
      'navDocuments': 'Documents',
      'navPayslips': 'Payslips',
      'navNotifications': 'Notifications',
      'navRatings': 'Workforce ratings',
      'navInquiries': 'Complaints & inquiries',
      'navPayments': 'Payment methods',
      'navSettings': 'Settings',
      'navProfile': 'My profile',
      'navApprovals': 'Approvals',
      'phase0Placeholder': 'Phase 0 — shell only. No business logic yet.',
      'phase0Subtitle': 'Single source of truth: RATIB ERP',
      'language': 'Language',
      'arabic': 'العربية',
      'english': 'English',
      'continueDemo': 'Continue shell (Phase 0)',
      'more': 'More',
      'leave': 'Leave',
      'requests': 'Requests',
      'attendance': 'Attendance',
      'loginSubtitle': 'Sign in with your RATIB ERP account',
      'loginEmailLabel': 'Email',
      'loginEmailHint': 'email@company.com',
      'loginPasswordLabel': 'Password',
      'loginSubmit': 'Sign in',
      'loginFieldsRequired': 'Email and password are required',
      'loginConfigMissing': 'ERP_BASE_URL is not configured',
      'loginNetworkError': 'Could not reach the server',
      'loginInvalidCredentials': 'Invalid credentials',
      'loginForbidden': 'Access denied',
      'loginCompanyAccessDenied': 'Company is inactive or subscription expired',
      'loginNoCompany': 'No company is linked to this account',
      'loginMobileDisabled': 'Mobile app is not enabled for this company',
      'loginRateLimited': 'Too many attempts. Try again later',
      'loginFailed': 'Sign-in failed',
      'loginErpOnlyHint': 'Authentication via RATIB ERP only',
      'loginEmployeeUnbound': 'No employee is linked to this account',
      'loginEmployeeAmbiguous': 'Multiple employees are linked to this account',
      'loginPlatformSuperAdmin':
          'Platform super-admin cannot use the mobile app — use a company employee account',
      'signOut': 'Sign out',
      'homeLoading': 'Loading employee home…',
      'homeLoadFailed': 'Could not load home',
      'homeRetry': 'Retry',
      'homeTodayAttendance': "Today's attendance",
      'homeLeaveBalance': 'Leave balance',
      'homeRecentNotifications': 'Recent notifications',
      'homeQuickActions': 'Quick actions',
      'homeNoAttendanceToday': 'No attendance record for today',
      'homeNoLeaveBalances': 'No leave balances',
      'homeNoNotifications': 'No notifications',
      'homeEntitled': 'Entitled',
      'homeUsed': 'Used',
      'homePendingRequests': 'Pending requests',
      'homeNoPendingRequests': 'No pending requests',
      'homeUnreadNotifications': 'Unread notifications',
      'homePayrollSummary': 'Payroll summary',
      'homePayrollPlaceholder':
          'Payslip detail is available when payroll is enabled',
      'genericLoading': 'Loading…',
      'genericLoadFailed': 'Could not load',
      'requestsEmpty': 'No requests',
      'requestDetailTitle': 'Request details',
      'requestStatus': 'Status',
      'requestType': 'Type',
      'requestNumber': 'Number',
      'requestDate': 'Date',
      'requestHistory': 'History',
      'requestHistoryEmpty': 'No conversation history yet',
      'notifMarkAllRead': 'Mark all read',
      'notifCatAll': 'All',
      'notifCatGeneral': 'General',
      'notifCatAttendance': 'Attendance',
      'notifCatLeave': 'Leave',
      'notifCatPayroll': 'Payroll',
      'notifCatSystem': 'System',
      'notifCatCustomer': 'Customer',
      'ratingsScore': 'Performance score',
      'ratingsMonthly': 'Monthly evaluation',
      'ratingsNoMonthly': 'No monthly evaluation',
      'ratingsKpi': 'KPI summary',
      'ratingsNoKpi': 'No KPIs yet',
      'ratingsReviews': 'Ratings',
      'ratingsEmpty': 'No ratings',
      'inquirySubmit': 'Submit',
      'inquiryTypeInquiry': 'Inquiry',
      'inquiryTypeComplaint': 'Complaint',
      'inquiryMessageHint': 'Write your message…',
      'inquiryMessageRequired': 'Message is required',
      'inquirySubmitted': 'Submitted',
      'inquiryHistory': 'History',
      'inquiryEmpty': 'No complaints or inquiries',
      'paymentsSalary': 'Salary payment information',
      'paymentsBanks': 'Bank accounts',
      'paymentsWallet': 'Wallet',
      'paymentsGateways': 'Payment gateways (future)',
      'paymentsUnavailable':
          'Payment methods are not enabled for this company yet',
      'paymentsReady': 'Payment data available',
      'paymentsBanksPlaceholder':
          'Bank accounts will appear when ERP provides them',
      'paymentsWalletPlaceholder': 'Wallet is not available yet',
      'paymentsGatewaysPlaceholder': 'No payment processing in this phase',
      'settingsPreferences': 'Preferences',
      'settingsTheme': 'Theme',
      'settingsThemeSystem': 'System',
      'settingsThemeLight': 'Light',
      'settingsThemeDark': 'Dark',
      'settingsNotifications': 'Notifications',
      'settingsBiometric': 'Biometric login',
      'settingsAccount': 'Account',
      'settingsChangePassword': 'Change password',
      'settingsCurrentPassword': 'Current password',
      'settingsNewPassword': 'New password',
      'settingsCancel': 'Cancel',
      'settingsSave': 'Save',
      'settingsClose': 'Close',
      'settingsPasswordChanged': 'Password changed',
      'settingsAboutSection': 'About',
      'settingsAbout': 'About',
      'settingsPrivacy': 'Privacy policy',
      'settingsTerms': 'Terms of service',
      'settingsPrivacyBody':
          'Your data is handled under your company privacy policy and RATIB ERP.',
      'settingsTermsBody':
          'By using this app you agree to your company terms and RATIB ERP terms.',
    },
  };

  String _t(String key) {
    final lang = isArabic ? 'ar' : 'en';
    return _text[lang]![key] ?? _text['en']![key] ?? key;
  }

  String get appTitle => _t('appTitle');
  String get tabHome => _t('tabHome');
  String get tabAttendance => _t('tabAttendance');
  String get tabLeave => _t('tabLeave');
  String get tabRequests => _t('tabRequests');
  String get tabMore => _t('tabMore');
  String get navLogin => _t('navLogin');
  String get navHome => _t('navHome');
  String get navAttendance => _t('navAttendance');
  String get navCheckIn => _t('navCheckIn');
  String get navCheckOut => _t('navCheckOut');
  String get navAttendanceHistory => _t('navAttendanceHistory');
  String get navLeaveBalance => _t('navLeaveBalance');
  String get navApplyLeave => _t('navApplyLeave');
  String get navLeaveStatus => _t('navLeaveStatus');
  String get navPermissionRequests => _t('navPermissionRequests');
  String get navEmployeeRequests => _t('navEmployeeRequests');
  String get navDocuments => _t('navDocuments');
  String get navPayslips => _t('navPayslips');
  String get navNotifications => _t('navNotifications');
  String get navRatings => _t('navRatings');
  String get navInquiries => _t('navInquiries');
  String get navPayments => _t('navPayments');
  String get navSettings => _t('navSettings');
  String get navProfile => _t('navProfile');
  String get navApprovals => _t('navApprovals');
  String get phase0Placeholder => _t('phase0Placeholder');
  String get phase0Subtitle => _t('phase0Subtitle');
  String get language => _t('language');
  String get arabic => _t('arabic');
  String get english => _t('english');
  String get continueDemo => _t('continueDemo');
  String get more => _t('more');
  String get leave => _t('leave');
  String get requests => _t('requests');
  String get attendance => _t('attendance');
  String get loginSubtitle => _t('loginSubtitle');
  String get loginEmailLabel => _t('loginEmailLabel');
  String get loginEmailHint => _t('loginEmailHint');
  String get loginPasswordLabel => _t('loginPasswordLabel');
  String get loginSubmit => _t('loginSubmit');
  String get loginFieldsRequired => _t('loginFieldsRequired');
  String get loginConfigMissing => _t('loginConfigMissing');
  String get loginNetworkError => _t('loginNetworkError');
  String get loginInvalidCredentials => _t('loginInvalidCredentials');
  String get loginForbidden => _t('loginForbidden');
  String get loginCompanyAccessDenied => _t('loginCompanyAccessDenied');
  String get loginNoCompany => _t('loginNoCompany');
  String get loginMobileDisabled => _t('loginMobileDisabled');
  String get loginRateLimited => _t('loginRateLimited');
  String get loginFailed => _t('loginFailed');
  String get loginErpOnlyHint => _t('loginErpOnlyHint');
  String get loginEmployeeUnbound => _t('loginEmployeeUnbound');
  String get loginEmployeeAmbiguous => _t('loginEmployeeAmbiguous');
  String get loginPlatformSuperAdmin => _t('loginPlatformSuperAdmin');
  String get signOut => _t('signOut');
  String get homeLoading => _t('homeLoading');
  String get homeLoadFailed => _t('homeLoadFailed');
  String get homeRetry => _t('homeRetry');
  String get homeTodayAttendance => _t('homeTodayAttendance');
  String get homeLeaveBalance => _t('homeLeaveBalance');
  String get homeRecentNotifications => _t('homeRecentNotifications');
  String get homeQuickActions => _t('homeQuickActions');
  String get homeNoAttendanceToday => _t('homeNoAttendanceToday');
  String get homeNoLeaveBalances => _t('homeNoLeaveBalances');
  String get homeNoNotifications => _t('homeNoNotifications');
  String get homeEntitled => _t('homeEntitled');
  String get homeUsed => _t('homeUsed');
  String get homePendingRequests => _t('homePendingRequests');
  String get homeNoPendingRequests => _t('homeNoPendingRequests');
  String get homeUnreadNotifications => _t('homeUnreadNotifications');
  String get homePayrollSummary => _t('homePayrollSummary');
  String get homePayrollPlaceholder => _t('homePayrollPlaceholder');
  String get genericLoading => _t('genericLoading');
  String get genericLoadFailed => _t('genericLoadFailed');
  String get requestsEmpty => _t('requestsEmpty');
  String get requestDetailTitle => _t('requestDetailTitle');
  String get requestStatus => _t('requestStatus');
  String get requestType => _t('requestType');
  String get requestNumber => _t('requestNumber');
  String get requestDate => _t('requestDate');
  String get requestHistory => _t('requestHistory');
  String get requestHistoryEmpty => _t('requestHistoryEmpty');
  String get notifMarkAllRead => _t('notifMarkAllRead');
  String get notifCatAll => _t('notifCatAll');
  String get notifCatGeneral => _t('notifCatGeneral');
  String get notifCatAttendance => _t('notifCatAttendance');
  String get notifCatLeave => _t('notifCatLeave');
  String get notifCatPayroll => _t('notifCatPayroll');
  String get notifCatSystem => _t('notifCatSystem');
  String get notifCatCustomer => _t('notifCatCustomer');
  String get ratingsScore => _t('ratingsScore');
  String get ratingsMonthly => _t('ratingsMonthly');
  String get ratingsNoMonthly => _t('ratingsNoMonthly');
  String get ratingsKpi => _t('ratingsKpi');
  String get ratingsNoKpi => _t('ratingsNoKpi');
  String get ratingsReviews => _t('ratingsReviews');
  String get ratingsEmpty => _t('ratingsEmpty');
  String get inquirySubmit => _t('inquirySubmit');
  String get inquiryTypeInquiry => _t('inquiryTypeInquiry');
  String get inquiryTypeComplaint => _t('inquiryTypeComplaint');
  String get inquiryMessageHint => _t('inquiryMessageHint');
  String get inquiryMessageRequired => _t('inquiryMessageRequired');
  String get inquirySubmitted => _t('inquirySubmitted');
  String get inquiryHistory => _t('inquiryHistory');
  String get inquiryEmpty => _t('inquiryEmpty');
  String get paymentsSalary => _t('paymentsSalary');
  String get paymentsBanks => _t('paymentsBanks');
  String get paymentsWallet => _t('paymentsWallet');
  String get paymentsGateways => _t('paymentsGateways');
  String get paymentsUnavailable => _t('paymentsUnavailable');
  String get paymentsReady => _t('paymentsReady');
  String get paymentsBanksPlaceholder => _t('paymentsBanksPlaceholder');
  String get paymentsWalletPlaceholder => _t('paymentsWalletPlaceholder');
  String get paymentsGatewaysPlaceholder => _t('paymentsGatewaysPlaceholder');
  String get settingsPreferences => _t('settingsPreferences');
  String get settingsTheme => _t('settingsTheme');
  String get settingsThemeSystem => _t('settingsThemeSystem');
  String get settingsThemeLight => _t('settingsThemeLight');
  String get settingsThemeDark => _t('settingsThemeDark');
  String get settingsNotifications => _t('settingsNotifications');
  String get settingsBiometric => _t('settingsBiometric');
  String get settingsAccount => _t('settingsAccount');
  String get settingsChangePassword => _t('settingsChangePassword');
  String get settingsCurrentPassword => _t('settingsCurrentPassword');
  String get settingsNewPassword => _t('settingsNewPassword');
  String get settingsCancel => _t('settingsCancel');
  String get settingsSave => _t('settingsSave');
  String get settingsClose => _t('settingsClose');
  String get settingsPasswordChanged => _t('settingsPasswordChanged');
  String get settingsAboutSection => _t('settingsAboutSection');
  String get settingsAbout => _t('settingsAbout');
  String get settingsPrivacy => _t('settingsPrivacy');
  String get settingsTerms => _t('settingsTerms');
  String get settingsPrivacyBody => _t('settingsPrivacyBody');
  String get settingsTermsBody => _t('settingsTermsBody');
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  bool isSupported(Locale locale) =>
      locale.languageCode == 'ar' || locale.languageCode == 'en';

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(AppLocalizations(locale));
  }

  @override
  bool shouldReload(covariant LocalizationsDelegate<AppLocalizations> old) =>
      false;
}

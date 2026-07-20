/// Manual localization (Phase 0) — Arabic RTL-first, English supported.
///
/// When Flutter SDK is available, `flutter gen-l10n` may replace this with
/// generated delegates from `lib/l10n/*.arb`. Until then this stub is the source.
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
      'loginMobileDisabled': 'تطبيق الجوال غير مفعّل لهذه الشركة',
      'loginRateLimited': 'محاولات كثيرة. حاول لاحقاً',
      'loginFailed': 'فشل تسجيل الدخول',
      'loginErpOnlyHint': 'المصادقة عبر نظام راتب ERP فقط',
      'loginEmployeeUnbound': 'لا يوجد موظف مرتبط بهذا الحساب',
      'loginEmployeeAmbiguous': 'أكثر من موظف مرتبط بهذا الحساب',
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
      'loginMobileDisabled': 'Mobile app is not enabled for this company',
      'loginRateLimited': 'Too many attempts. Try again later',
      'loginFailed': 'Sign-in failed',
      'loginErpOnlyHint': 'Authentication via RATIB ERP only',
      'loginEmployeeUnbound': 'No employee is linked to this account',
      'loginEmployeeAmbiguous': 'Multiple employees are linked to this account',
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
  String get loginMobileDisabled => _t('loginMobileDisabled');
  String get loginRateLimited => _t('loginRateLimited');
  String get loginFailed => _t('loginFailed');
  String get loginErpOnlyHint => _t('loginErpOnlyHint');
  String get loginEmployeeUnbound => _t('loginEmployeeUnbound');
  String get loginEmployeeAmbiguous => _t('loginEmployeeAmbiguous');
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

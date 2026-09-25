import 'package:flutter/material.dart';

import '../core/models/user_role.dart';

class AppLocalizations {
  AppLocalizations(this.locale);

  final Locale locale;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  static const supportedLocales = [Locale('ar'), Locale('en')];

  bool get isArabic => locale.languageCode == 'ar';

  static const Map<String, Map<String, String>> _text = {
    'ar': {
      'appTagline': 'بوابة إدارة القوى العاملة',
      'otherLanguage': 'English',
      'otherLanguageShort': 'EN',
      'changeLanguage': 'تغيير اللغة',
      'somethingWentWrong': 'حدث خطأ ما',
      'retry': 'إعادة المحاولة',
      'tryAgain': 'حاول مرة أخرى',
      'signOut': 'تسجيل الخروج',
      'offersInfo': 'العروض والمعلومات',
      'close': 'إغلاق',
      'done': 'تم',
      'workerPortal': 'بوابة العامل',
      'companyPortal': 'بوابة الشركة',
      'agencyPortal': 'بوابة الوكالة',
      'navDashboard': 'الرئيسية',
      'navProfile': 'الملف الشخصي',
      'navTasks': 'المهام',
      'navWorkers': 'العمال',
      'navRequests': 'الطلبات',
      'navPipeline': 'مسار التوظيف',
      'navAssignments': 'التعيينات',
      'roleWorker': 'عامل',
      'roleCompany': 'شركة',
      'roleAgency': 'وكالة',
      'restoringSession': 'جارٍ استعادة الجلسة…',
      'loginIntro': 'دخول آمن للقوى العاملة باستخدام بطاقة هوية راتب.',
      'emailOrUsername': 'البريد الإلكتروني أو اسم المستخدم',
      'enterEmailOrUsername': 'أدخل البريد الإلكتروني أو اسم المستخدم',
      'password': 'كلمة المرور',
      'enterPassword': 'أدخل كلمة المرور',
      'signIn': 'تسجيل الدخول',
      'identityLogin': 'الدخول ببطاقة الهوية',
      'scanBadgeHint': 'امسح رمز QR لبطاقتك من إعدادات نظام راتب',
      'pilotTools': 'أدوات تجريبية (داخلية)',
      'couldNotLoadData': 'تعذّر تحميل البيانات',
      'offlineBanner': 'أنت غير متصل. يتم عرض البيانات المحفوظة عند توفرها.',
      'workforceStatus': 'حالة القوى العاملة',
      'companyHomeSubtitle':
          'عرض مباشر لقائمة العمال والموافقات والطلبات المفتوحة.',
      'activeWorkers': 'العمال النشطون',
      'openRequests': 'الطلبات المفتوحة',
      'totalRoster': 'إجمالي العمال',
      'todaysOverview': 'نظرة عامة على اليوم',
      'workerHomeSubtitle': 'مهام اليوم وحالتك الحالية في القوى العاملة.',
      'dueToday': 'مستحقة اليوم',
      'pendingTasks': 'المهام المعلّقة',
      'yourStatus': 'حالتك',
      'activeAccount': 'حساب نشط',
      'pipelineFlow': 'سير التوظيف',
      'agencyHomeSubtitle': 'تابع المرشحين وعمليات الإلحاق وتعيينات العملاء.',
      'candidatesInPipeline': 'المرشحون في المسار',
      'activeAssignments': 'التعيينات النشطة',
      'cvPool': 'بنك السير الذاتية',
      'requestsTitle': 'الطلبات',
      'newRequest': 'جديد',
      'workersTitle': 'العمال',
      'tasksTitle': 'المهام',
      'assignmentsTitle': 'التعيينات',
      'recruitmentPipelineTitle': 'مسار التوظيف',
      'workerAccount': 'حساب عامل',
      'profileRole': 'الدور',
      'profileStatus': 'الحالة',
      'profileEmail': 'البريد الإلكتروني',
      'profilePhone': 'الجوال',
      'profileCountry': 'الدولة',
      'profilePortal': 'البوابة',
      'mobileWorkforce': 'القوى العاملة عبر الجوال',
      'statusActive': 'نشط',
      'workforceIdentity': 'هوية القوى العاملة',
      'turnOffFlashlight': 'إطفاء الفلاش',
      'turnOnFlashlight': 'تشغيل الفلاش',
      'previewBadge': 'معاينة بطاقة الهوية',
      'verifyingIdentity': 'جارٍ التحقق من الهوية…',
      'pastePayloadTitle': 'الصق بيانات بطاقة الهوية',
      'pastePayloadHint': 'استخدم بيانات رمز QR المُنشأة من إعدادات نظام راتب.',
      'identityPayload': 'بيانات الهوية',
      'verifyAndSignIn': 'تحقق وسجّل الدخول',
      'scanAgain': 'امسح مرة أخرى',
      'scanFrameSemantics': 'امسح رمز QR لبطاقة الهوية داخل الإطار',
      'alignQr': 'ضع رمز QR داخل الإطار',
      'holdSteady': 'ثبّت الجوال — يتم تسجيل الدخول تلقائيًا',
      'identityVerified': 'تم التحقق من الهوية',
      'badgeTitle': 'بطاقة الهوية',
      'badgePreviewTitle': 'معاينة بطاقة الهوية',
      'badgePreviewHint':
          'تُصدر البطاقات الفعلية من إعدادات نظام راتب على rateb.sa.',
      'badgeCredential': 'بطاقة هوية القوى العاملة',
      'badgeScanHint': 'امسح لتسجيل الدخول إلى تطبيق راتب',
    },
    'en': {
      'appTagline': 'Workforce Management Portal',
      'otherLanguage': 'العربية',
      'otherLanguageShort': 'عربي',
      'changeLanguage': 'Change language',
      'somethingWentWrong': 'Something went wrong',
      'retry': 'Retry',
      'tryAgain': 'Try again',
      'signOut': 'Sign out',
      'offersInfo': 'Offers & info',
      'close': 'Close',
      'done': 'Done',
      'workerPortal': 'Worker Portal',
      'companyPortal': 'Company Portal',
      'agencyPortal': 'Agency Portal',
      'navDashboard': 'Dashboard',
      'navProfile': 'Profile',
      'navTasks': 'Tasks',
      'navWorkers': 'Workers',
      'navRequests': 'Requests',
      'navPipeline': 'Pipeline',
      'navAssignments': 'Assignments',
      'roleWorker': 'Worker',
      'roleCompany': 'Company',
      'roleAgency': 'Agency',
      'restoringSession': 'Restoring session…',
      'loginIntro': 'Secure workforce access using your RATEB identity badge.',
      'emailOrUsername': 'Email or username',
      'enterEmailOrUsername': 'Enter your email or username',
      'password': 'Password',
      'enterPassword': 'Enter your password',
      'signIn': 'Sign in',
      'identityLogin': 'Workforce identity login',
      'scanBadgeHint': 'Scan your badge QR code from RATEB System Settings',
      'pilotTools': 'Pilot tools (internal)',
      'couldNotLoadData': 'Could not load data',
      'offlineBanner': 'You are offline. Showing saved data where available.',
      'workforceStatus': 'Workforce status',
      'companyHomeSubtitle':
          'Live view of your roster, approvals, and open requests.',
      'activeWorkers': 'Active workers',
      'openRequests': 'Open requests',
      'totalRoster': 'Total roster',
      'todaysOverview': "Today's overview",
      'workerHomeSubtitle': 'Tasks due today and your current workforce status.',
      'dueToday': 'Due today',
      'pendingTasks': 'Pending tasks',
      'yourStatus': 'Your status',
      'activeAccount': 'Active account',
      'pipelineFlow': 'Pipeline flow',
      'agencyHomeSubtitle':
          'Track candidates, deployments, and client assignments.',
      'candidatesInPipeline': 'Candidates in pipeline',
      'activeAssignments': 'Active assignments',
      'cvPool': 'CV pool',
      'requestsTitle': 'Requests',
      'newRequest': 'New',
      'workersTitle': 'Workers',
      'tasksTitle': 'Tasks',
      'assignmentsTitle': 'Assignments',
      'recruitmentPipelineTitle': 'Recruitment pipeline',
      'workerAccount': 'Worker account',
      'profileRole': 'Role',
      'profileStatus': 'Status',
      'profileEmail': 'Email',
      'profilePhone': 'Phone',
      'profileCountry': 'Country',
      'profilePortal': 'Portal',
      'mobileWorkforce': 'Mobile workforce',
      'statusActive': 'Active',
      'workforceIdentity': 'Workforce identity',
      'turnOffFlashlight': 'Turn off flashlight',
      'turnOnFlashlight': 'Turn on flashlight',
      'previewBadge': 'Preview workforce badge',
      'verifyingIdentity': 'Verifying workforce identity…',
      'pastePayloadTitle': 'Paste workforce identity payload',
      'pastePayloadHint':
          'Use the QR payload generated from RATEB System Settings.',
      'identityPayload': 'Identity payload',
      'verifyAndSignIn': 'Verify and sign in',
      'scanAgain': 'Scan again',
      'scanFrameSemantics': 'Scan workforce identity QR code inside the frame',
      'alignQr': 'Align QR inside frame',
      'holdSteady': 'Hold steady — sign-in is automatic',
      'identityVerified': 'Identity verified',
      'badgeTitle': 'Workforce badge',
      'badgePreviewTitle': 'Workforce identity preview',
      'badgePreviewHint':
          'Live badges are issued from RATEB System Settings on rateb.sa.',
      'badgeCredential': 'Workforce identity credential',
      'badgeScanHint': 'Scan to sign in to RATEB Mobile',
    },
  };

  String _t(String key) {
    final lang = isArabic ? 'ar' : 'en';
    return _text[lang]![key] ?? _text['en']![key] ?? key;
  }

  String get appTagline => _t('appTagline');
  String get otherLanguage => _t('otherLanguage');
  String get otherLanguageShort => _t('otherLanguageShort');
  String get changeLanguage => _t('changeLanguage');
  String get somethingWentWrong => _t('somethingWentWrong');
  String get retry => _t('retry');
  String get tryAgain => _t('tryAgain');
  String get signOut => _t('signOut');
  String get offersInfo => _t('offersInfo');
  String get close => _t('close');
  String get done => _t('done');
  String get workerPortal => _t('workerPortal');
  String get companyPortal => _t('companyPortal');
  String get agencyPortal => _t('agencyPortal');
  String get navDashboard => _t('navDashboard');
  String get navProfile => _t('navProfile');
  String get navTasks => _t('navTasks');
  String get navWorkers => _t('navWorkers');
  String get navRequests => _t('navRequests');
  String get navPipeline => _t('navPipeline');
  String get navAssignments => _t('navAssignments');
  String get restoringSession => _t('restoringSession');
  String get loginIntro => _t('loginIntro');
  String get emailOrUsername => _t('emailOrUsername');
  String get enterEmailOrUsername => _t('enterEmailOrUsername');
  String get password => _t('password');
  String get enterPassword => _t('enterPassword');
  String get signIn => _t('signIn');
  String get identityLogin => _t('identityLogin');
  String get scanBadgeHint => _t('scanBadgeHint');
  String get pilotTools => _t('pilotTools');
  String get couldNotLoadData => _t('couldNotLoadData');
  String get offlineBanner => _t('offlineBanner');
  String get workforceStatus => _t('workforceStatus');
  String get companyHomeSubtitle => _t('companyHomeSubtitle');
  String get activeWorkers => _t('activeWorkers');
  String get openRequests => _t('openRequests');
  String get totalRoster => _t('totalRoster');
  String get todaysOverview => _t('todaysOverview');
  String get workerHomeSubtitle => _t('workerHomeSubtitle');
  String get dueToday => _t('dueToday');
  String get pendingTasks => _t('pendingTasks');
  String get yourStatus => _t('yourStatus');
  String get activeAccount => _t('activeAccount');
  String get pipelineFlow => _t('pipelineFlow');
  String get agencyHomeSubtitle => _t('agencyHomeSubtitle');
  String get candidatesInPipeline => _t('candidatesInPipeline');
  String get activeAssignments => _t('activeAssignments');
  String get cvPool => _t('cvPool');
  String get requestsTitle => _t('requestsTitle');
  String get newRequest => _t('newRequest');
  String get workersTitle => _t('workersTitle');
  String get tasksTitle => _t('tasksTitle');
  String get assignmentsTitle => _t('assignmentsTitle');
  String get recruitmentPipelineTitle => _t('recruitmentPipelineTitle');
  String get workerAccount => _t('workerAccount');
  String get profileRole => _t('profileRole');
  String get profileStatus => _t('profileStatus');
  String get profileEmail => _t('profileEmail');
  String get profilePhone => _t('profilePhone');
  String get profileCountry => _t('profileCountry');
  String get profilePortal => _t('profilePortal');
  String get mobileWorkforce => _t('mobileWorkforce');
  String get statusActive => _t('statusActive');
  String get workforceIdentity => _t('workforceIdentity');
  String get turnOffFlashlight => _t('turnOffFlashlight');
  String get turnOnFlashlight => _t('turnOnFlashlight');
  String get previewBadge => _t('previewBadge');
  String get verifyingIdentity => _t('verifyingIdentity');
  String get pastePayloadTitle => _t('pastePayloadTitle');
  String get pastePayloadHint => _t('pastePayloadHint');
  String get identityPayload => _t('identityPayload');
  String get verifyAndSignIn => _t('verifyAndSignIn');
  String get scanAgain => _t('scanAgain');
  String get scanFrameSemantics => _t('scanFrameSemantics');
  String get alignQr => _t('alignQr');
  String get holdSteady => _t('holdSteady');
  String get identityVerified => _t('identityVerified');
  String get badgeTitle => _t('badgeTitle');
  String get badgePreviewTitle => _t('badgePreviewTitle');
  String get badgePreviewHint => _t('badgePreviewHint');
  String get badgeCredential => _t('badgeCredential');
  String get badgeScanHint => _t('badgeScanHint');

  String roleName(UserRole role) {
    switch (role) {
      case UserRole.worker:
        return _t('roleWorker');
      case UserRole.company:
        return _t('roleCompany');
      case UserRole.agency:
        return _t('roleAgency');
    }
  }

  String welcome(String name) => isArabic ? 'مرحبًا، $name' : 'Welcome, $name';

  String retryingAttempt(int attempt) => isArabic
      ? 'جارٍ إعادة المحاولة… (المحاولة $attempt/2)'
      : 'Retrying… (attempt $attempt/2)';

  String activeWorkersDetail(int active, int pending) => isArabic
      ? 'في مهمة: $active · بانتظار الموافقة: $pending'
      : '$active on assignment · $pending pending approval';

  String openRequestsDetail(int n) => isArabic
      ? 'طلبات توظيف قيد التنفيذ: $n'
      : '$n recruitment request${n == 1 ? '' : 's'} in progress';

  String totalRosterDetail(int n) => isArabic
      ? 'عدد العمال لديك: $n'
      : '$n worker${n == 1 ? '' : 's'} in your workforce';

  String dueTodayDetail(int n) => isArabic
      ? 'مهام تحتاج انتباهك اليوم: $n'
      : '$n task${n == 1 ? '' : 's'} need attention today';

  String pendingTasksDetail(int n) => isArabic
      ? 'عناصر مفتوحة في قائمتك: $n'
      : '$n open item${n == 1 ? '' : 's'} in your queue';

  String candidatesDetail(int total, int deployed) => isArabic
      ? 'الإجمالي: $total · تم الإلحاق: $deployed'
      : '$total total · $deployed deployed';

  String activeAssignmentsDetail(int n) => isArabic
      ? 'وجهات عملاء لديها عمال: $n'
      : '$n client destination${n == 1 ? '' : 's'} with workers';

  String cvPoolDetail(int n) => isArabic
      ? 'سير ذاتية جاهزة للعملاء: $n'
      : '$n shared CV${n == 1 ? '' : 's'} ready for clients';

  String stageCandidates(int n) =>
      isArabic ? 'المرشحون: $n' : '$n candidates';

  /// Translates a known English app/API message (errors, banners, empty
  /// states). Unknown text is returned unchanged so no detail is lost.
  String message(String text) {
    if (!isArabic || text.isEmpty) return text;
    return _messagesArIndex[_normalize(text)] ?? text;
  }

  /// Translates English labels built by the mobile API (statuses, priorities,
  /// task titles, relative times). Names and other data pass through as-is.
  String server(String text) {
    if (!isArabic || text.isEmpty) return text;
    if (text.contains(' · ')) {
      return text.split(' · ').map(_serverPart).join(' · ');
    }
    return _serverPart(text);
  }

  String _serverPart(String part) {
    final trimmed = part.trim();
    final exact = _serverArIndex[_normalize(trimmed)];
    if (exact != null) return exact;
    for (final rule in _serverPatterns) {
      final match = rule.$1.firstMatch(trimmed);
      if (match != null) return rule.$2(match);
    }
    return part;
  }

  static String _normalize(String text) {
    var t = text.trim().toLowerCase();
    while (t.endsWith('.')) {
      t = t.substring(0, t.length - 1);
    }
    return t;
  }

  static String _arCount(
    int n, {
    required String one,
    required String two,
    required String few,
    required String many,
  }) {
    if (n == 1) return one;
    if (n == 2) return two;
    if (n >= 3 && n <= 10) return '$n $few';
    return '$n $many';
  }

  static const _monthsAr = {
    'jan': 'يناير',
    'feb': 'فبراير',
    'mar': 'مارس',
    'apr': 'أبريل',
    'may': 'مايو',
    'jun': 'يونيو',
    'jul': 'يوليو',
    'aug': 'أغسطس',
    'sep': 'سبتمبر',
    'oct': 'أكتوبر',
    'nov': 'نوفمبر',
    'dec': 'ديسمبر',
  };

  static final List<(RegExp, String Function(RegExpMatch))> _serverPatterns = [
    (
      RegExp(r'^(\d+) min ago$', caseSensitive: false),
      (m) => 'قبل ${_arCount(int.parse(m[1]!), one: 'دقيقة', two: 'دقيقتين', few: 'دقائق', many: 'دقيقة')}',
    ),
    (
      RegExp(r'^(\d+) hours? ago$', caseSensitive: false),
      (m) => 'قبل ${_arCount(int.parse(m[1]!), one: 'ساعة', two: 'ساعتين', few: 'ساعات', many: 'ساعة')}',
    ),
    (
      RegExp(r'^(\d+) days? ago$', caseSensitive: false),
      (m) => 'قبل ${_arCount(int.parse(m[1]!), one: 'يوم', two: 'يومين', few: 'أيام', many: 'يومًا')}',
    ),
    (
      RegExp(r'^([A-Za-z]{3}) (\d{1,2}), (\d{4})$'),
      (m) {
        final month = _monthsAr[m[1]!.toLowerCase()];
        return month == null ? m[0]! : '${m[2]} $month ${m[3]}';
      },
    ),
    (
      RegExp(r'^Case #(.+)$', caseSensitive: false),
      (m) => 'حالة #${m[1]}',
    ),
    (
      RegExp(r'^(\d+) deployed$', caseSensitive: false),
      (m) => 'تم الإلحاق: ${m[1]}',
    ),
    (
      RegExp(r'^(\d+) processing$', caseSensitive: false),
      (m) => 'قيد المعالجة: ${m[1]}',
    ),
    (
      RegExp(r'^(\d+) issues?$', caseSensitive: false),
      (m) => 'مشكلات: ${m[1]}',
    ),
    (
      RegExp(r'^(\d+) workers?$', caseSensitive: false),
      (m) => 'العمال: ${m[1]}',
    ),
  ];

  static final Map<String, String> _serverArIndex = {
    for (final e in _serverAr.entries) _normalize(e.key): e.value,
  };

  static const Map<String, String> _serverAr = {
    'Pending': 'قيد الانتظار',
    'Active': 'نشط',
    'Inactive': 'غير نشط',
    'Approved': 'معتمد',
    'Processing': 'قيد المعالجة',
    'Deployed': 'تم الإلحاق',
    'Issue': 'مشكلة',
    'Issues': 'مشكلات',
    'Returned': 'مُعاد',
    'Transferred': 'منقول',
    'Open': 'مفتوح',
    'Closed': 'مغلق',
    'In Progress': 'قيد التنفيذ',
    'Rejected': 'مرفوض',
    'Completed': 'مكتمل',
    'Complete': 'مكتمل',
    'Cancelled': 'ملغى',
    'Canceled': 'ملغى',
    'New': 'جديد',
    'Draft': 'مسودة',
    'Resolved': 'تم الحل',
    'On Hold': 'معلّق',
    'Suspended': 'موقوف',
    'Terminated': 'منتهي',
    'Expired': 'منتهي الصلاحية',
    'Submitted': 'مُرسل',
    'Under Review': 'قيد المراجعة',
    'Waiting': 'بالانتظار',
    'Hired': 'تم التعيين',
    'Available': 'متاح',
    'Archived': 'مؤرشف',
    'Done': 'منجز',
    'Overdue': 'متأخر',
    'Failed': 'فشل',
    'Unknown': 'غير معروف',
    'Unassigned': 'غير محدد',
    'High': 'عالية',
    'Medium': 'متوسطة',
    'Low': 'منخفضة',
    'Urgent': 'عاجلة',
    'Normal': 'عادية',
    'Critical': 'حرجة',
    'CV Pool': 'بنك السير الذاتية',
    'Sourcing': 'الاستقطاب',
    'Screening': 'الفرز',
    'Deployment': 'الإلحاق',
    'Recently': 'مؤخرًا',
    'Upload passport copy': 'رفع نسخة من جواز السفر',
    'Complete visa documentation': 'استكمال مستندات التأشيرة',
    'Submit medical certificate': 'تقديم الشهادة الطبية',
    'Complete onboarding documents': 'استكمال مستندات الانضمام',
    'Verify contact information': 'التحقق من معلومات التواصل',
    'Deployment in progress': 'الإلحاق قيد التنفيذ',
    'Link your worker profile': 'اربط ملفك كعامل',
    'Update contact information': 'تحديث معلومات التواصل',
    'Required document': 'مستند مطلوب',
    'Keep your profile up to date': 'حافظ على تحديث ملفك الشخصي',
    'Ask HR to match your account email with your worker record':
        'اطلب من الموارد البشرية مطابقة بريد حسابك مع سجل العامل',
    'This week': 'هذا الأسبوع',
    'Action needed': 'إجراء مطلوب',
    'Field Worker': 'عامل ميداني',
    'RATEB Workforce': 'راتب للقوى العاملة',
  };

  static final Map<String, String> _messagesArIndex = {
    for (final e in _messagesAr.entries) _normalize(e.key): e.value,
  };

  static const Map<String, String> _messagesAr = {
    // App-side errors (ApiClient, AuthProvider, ResilientLoader).
    'Simulated offline (pilot mode)': 'وضع عدم الاتصال التجريبي',
    'Empty response from server': 'استجابة فارغة من الخادم',
    'Request failed': 'فشل الطلب',
    'Network error': 'خطأ في الشبكة',
    'Connection timed out': 'انتهت مهلة الاتصال',
    'Unable to reach server': 'تعذّر الوصول إلى الخادم',
    'Unexpected network error': 'خطأ غير متوقع في الشبكة',
    'Login timed out. Check your connection and try again.':
        'انتهت مهلة تسجيل الدخول. تحقق من اتصالك وحاول مرة أخرى.',
    'Unexpected server response. Please try again.':
        'استجابة غير متوقعة من الخادم. حاول مرة أخرى.',
    'Login failed. Please try again.': 'فشل تسجيل الدخول. حاول مرة أخرى.',
    'Could not save session.': 'تعذّر حفظ الجلسة.',
    'Session expired. Please sign in again.':
        'انتهت الجلسة. يرجى تسجيل الدخول مرة أخرى.',
    'Something went wrong. Please try again.': 'حدث خطأ ما. حاول مرة أخرى.',
    'Offline — showing saved data': 'غير متصل — يتم عرض البيانات المحفوظة',
    // Mobile API messages.
    'Invalid username or password.': 'اسم المستخدم أو كلمة المرور غير صحيحة.',
    'Account is inactive.': 'الحساب غير نشط.',
    'Access denied for this tenant.': 'تم رفض الوصول لهذه الجهة.',
    'Email and password are required': 'البريد الإلكتروني وكلمة المرور مطلوبان',
    'Login failed': 'فشل تسجيل الدخول',
    'POST required': 'طلب غير صالح',
    'GET required': 'طلب غير صالح',
    'Unauthorized': 'غير مصرح بالدخول',
    'Forbidden': 'غير مسموح',
    'Server configuration error': 'خطأ في إعدادات الخادم',
    'Tenant isolation unavailable': 'عزل بيانات الجهة غير متاح حاليًا',
    'Partner agency account required': 'يتطلب حساب وكالة شريكة',
    'Dashboard unavailable': 'لوحة المعلومات غير متاحة',
    'Workers unavailable': 'بيانات العمال غير متاحة',
    'Requests unavailable': 'الطلبات غير متاحة',
    'Tasks unavailable': 'المهام غير متاحة',
    'Pipeline unavailable': 'مسار التوظيف غير متاح',
    'Assignments unavailable': 'التعيينات غير متاحة',
    'Profile unavailable': 'الملف الشخصي غير متاح',
    'User not found': 'المستخدم غير موجود',
    'Agency not found': 'الوكالة غير موجودة',
    'QR login failed': 'فشل الدخول برمز QR',
    'QR payload is required.': 'بيانات رمز QR مطلوبة.',
    'QR code already used.': 'تم استخدام رمز QR مسبقًا.',
    'QR code has expired.': 'انتهت صلاحية رمز QR.',
    'Invalid QR signature.': 'توقيع رمز QR غير صالح.',
    'Invalid subject.': 'بيانات الهوية غير صالحة.',
    'Empty QR payload.': 'بيانات رمز QR فارغة.',
    'Unrecognized QR format.': 'صيغة رمز QR غير معروفة.',
    'Malformed QR payload.': 'بيانات رمز QR تالفة.',
    'Invalid QR data.': 'بيانات رمز QR غير صالحة.',
    'Unsupported QR version.': 'إصدار رمز QR غير مدعوم.',
    'Invalid QR credentials.': 'بيانات اعتماد رمز QR غير صالحة.',
    'This badge requires a PIN. Use password login on mobile.':
        'هذه البطاقة تتطلب رمز PIN. استخدم الدخول بكلمة المرور على الجوال.',
    // QR login copy (friendlyQrErrorMessage, controller, camera).
    'Empty QR code.': 'رمز QR فارغ.',
    'Unable to verify your workforce identity. Please try again.':
        'تعذّر التحقق من هويتك. حاول مرة أخرى.',
    'This workforce badge has expired. Request a new QR from RATEB System Settings.':
        'انتهت صلاحية بطاقة الهوية. اطلب رمز QR جديدًا من إعدادات نظام راتب.',
    'This badge could not be authenticated. Use a current QR from RATEB System Settings.':
        'تعذّر التحقق من هذه البطاقة. استخدم رمز QR حديثًا من إعدادات نظام راتب.',
    'Unrecognized workforce badge. Check the QR or use password sign-in.':
        'بطاقة هوية غير معروفة. تحقق من رمز QR أو سجّل الدخول بكلمة المرور.',
    'This identity badge has already been used. Request a new QR from your administrator.':
        'تم استخدام بطاقة الهوية هذه مسبقًا. اطلب رمز QR جديدًا من المسؤول.',
    'This badge is not authorized for mobile access.':
        'هذه البطاقة غير مصرح لها بالدخول عبر الجوال.',
    'Workforce identity service is temporarily unavailable. Try again shortly or use password sign-in.':
        'خدمة التحقق من الهوية غير متاحة مؤقتًا. حاول بعد قليل أو سجّل الدخول بكلمة المرور.',
    'No network connection. Check your internet and try again.':
        'لا يوجد اتصال بالشبكة. تحقق من الإنترنت وحاول مرة أخرى.',
    'Workforce badge could not be verified. It may be expired or invalid.':
        'تعذّر التحقق من بطاقة الهوية. قد تكون منتهية الصلاحية أو غير صالحة.',
    'Connection timed out. Check your network and try again.':
        'انتهت مهلة الاتصال. تحقق من الشبكة وحاول مرة أخرى.',
    'Camera access is required to scan your workforce badge. Enable camera permission in Settings, then tap Try again.':
        'يلزم الوصول إلى الكاميرا لمسح بطاقة الهوية. فعّل إذن الكاميرا من الإعدادات ثم اضغط «حاول مرة أخرى».',
    'Unable to start the camera. Check permissions and try again.':
        'تعذّر تشغيل الكاميرا. تحقق من الأذونات وحاول مرة أخرى.',
    // Empty states.
    'No tasks assigned yet': 'لا توجد مهام مسندة بعد',
    'When your employer assigns work, tasks will appear here.':
        'ستظهر المهام هنا عندما يسند إليك صاحب العمل مهام.',
    'We could not load your profile details right now.':
        'تعذّر تحميل بيانات ملفك الشخصي حاليًا.',
    'No workers available': 'لا يوجد عمال',
    'Your workforce roster is empty. Workers will appear once added to RATEB.':
        'قائمة العمال فارغة. سيظهر العمال بعد إضافتهم إلى راتب.',
    'No requests yet': 'لا توجد طلبات بعد',
    'Recruitment and case requests will show here when created.':
        'ستظهر طلبات التوظيف والحالات هنا عند إنشائها.',
    'No pipeline data found': 'لا توجد بيانات في مسار التوظيف',
    'Candidates and deployments will appear as your agency processes recruitment.':
        'سيظهر المرشحون وعمليات الإلحاق مع تقدّم وكالتك في التوظيف.',
    'No assignments yet': 'لا توجد تعيينات بعد',
    'Client assignments will appear when workers are deployed.':
        'ستظهر تعيينات العملاء عند إلحاق العمال.',
  };
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  bool isSupported(Locale locale) =>
      AppLocalizations.supportedLocales
          .any((l) => l.languageCode == locale.languageCode);

  @override
  Future<AppLocalizations> load(Locale locale) async =>
      AppLocalizations(locale);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

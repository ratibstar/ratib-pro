import 'package:dio/dio.dart';

import '../config/app_config.dart';

/// Picks the Arabic or English variant, falling back to whichever is filled.
String pickLocalized(String ar, String en, {required bool arabic}) {
  if (arabic) return ar.isNotEmpty ? ar : en;
  return en.isNotEmpty ? en : ar;
}

class AppOffer {
  const AppOffer({
    required this.id,
    required this.titleAr,
    required this.titleEn,
    required this.bodyAr,
    required this.bodyEn,
    required this.imageUrl,
    required this.discountLabel,
  });

  final int id;
  final String titleAr;
  final String titleEn;
  final String bodyAr;
  final String bodyEn;
  final String imageUrl;
  final String discountLabel;

  factory AppOffer.fromJson(Map<String, dynamic> j) => AppOffer(
        id: int.tryParse('${j['id']}') ?? 0,
        titleAr: '${j['title_ar'] ?? ''}'.trim(),
        titleEn: '${j['title_en'] ?? ''}'.trim(),
        bodyAr: '${j['body_ar'] ?? ''}'.trim(),
        bodyEn: '${j['body_en'] ?? ''}'.trim(),
        imageUrl: '${j['image'] ?? ''}'.trim(),
        discountLabel: '${j['discount_label'] ?? ''}'.trim(),
      );
}

class AppPage {
  const AppPage({
    required this.slug,
    required this.titleAr,
    required this.titleEn,
    required this.bodyAr,
    required this.bodyEn,
  });

  final String slug;
  final String titleAr;
  final String titleEn;
  final String bodyAr;
  final String bodyEn;

  factory AppPage.fromJson(Map<String, dynamic> j) => AppPage(
        slug: '${j['slug'] ?? ''}',
        titleAr: '${j['title_ar'] ?? ''}'.trim(),
        titleEn: '${j['title_en'] ?? ''}'.trim(),
        bodyAr: '${j['body_ar'] ?? ''}'.trim(),
        bodyEn: '${j['body_en'] ?? ''}'.trim(),
      );
}

class AppContentBundle {
  const AppContentBundle({required this.offers, required this.pages});

  static const empty = AppContentBundle(offers: [], pages: []);

  final List<AppOffer> offers;
  final List<AppPage> pages;

  bool get isEmpty => offers.isEmpty && pages.isEmpty;
}

/// Offers + content pages published for the Customer app in ERP Admin
/// (Oversight → Mobile Apps). Public read-only endpoint — no auth token sent.
class AppContentService {
  AppContentService._();

  static final AppContentService instance = AppContentService._();

  final Dio _dio = Dio(
    BaseOptions(
      connectTimeout: AppConfig.connectTimeout,
      receiveTimeout: AppConfig.receiveTimeout,
      responseType: ResponseType.json,
    ),
  );

  AppContentBundle? _cache;

  Future<AppContentBundle> load({bool refresh = false}) async {
    final cached = _cache;
    if (cached != null && !refresh) return cached;
    final res = await _dio.get<dynamic>(
      '${AppConfig.erpBaseUrl}/api/mobile/app-content',
      queryParameters: {
        'app': 'customer',
        if (AppConfig.companySlug.isNotEmpty) 'company': AppConfig.companySlug,
      },
    );
    final data = res.data;
    if (data is! Map) return AppContentBundle.empty;
    List<Map<String, dynamic>> rows(Object? raw) => raw is List
        ? raw.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
        : const [];
    final bundle = AppContentBundle(
      offers: rows(data['offers']).map(AppOffer.fromJson).toList(),
      pages: rows(data['content'])
          .map(AppPage.fromJson)
          .where((p) => p.bodyAr.isNotEmpty || p.bodyEn.isNotEmpty)
          .toList(),
    );
    _cache = bundle;
    return bundle;
  }
}

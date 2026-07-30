/// Tenant mobile configuration from ERP `GET /api/mobile/config`.
///
/// Presentation-only model. No HR business rules.
library;

import 'package:ratib_hr_mobile/core/brand/brand_display.dart';

/// Workspace role for future shell variants (Manager / HR / Supervisor / CEO).
/// Today ESS uses [employee] only — ERP may supply a role later without redesign.
enum AppWorkspaceRole {
  employee,
  manager,
  hr,
  supervisor,
  ceo,
}

/// Extensible feature keys — matches ERP `enabled_features` + forward-compatible keys.
abstract final class MobileFeatureKey {
  static const attendance = 'attendance';
  static const leave = 'leave';
  static const profile = 'profile';
  static const documents = 'documents';
  static const payroll = 'payroll';
  /// ESS payslips gate — aliases legacy [payroll] when only one is set.
  static const payslips = 'payslips';
  static const notifications = 'notifications';
  static const requests = 'requests';
  static const approvals = 'approvals';
  static const ratings = 'ratings';
  static const inquiries = 'inquiries';
  static const payments = 'payments';
  static const settings = 'settings';
  static const manager = 'manager';
  static const supervisor = 'supervisor';
  static const ceoDashboard = 'ceo_dashboard';
}

/// Promotional offer from ERP mobile config.
final class MobileAppOffer {
  const MobileAppOffer({
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

  String titleFor(String languageCode) =>
      languageCode.startsWith('ar')
          ? (titleAr.isNotEmpty ? titleAr : titleEn)
          : (titleEn.isNotEmpty ? titleEn : titleAr);

  String bodyFor(String languageCode) =>
      languageCode.startsWith('ar')
          ? (bodyAr.isNotEmpty ? bodyAr : bodyEn)
          : (bodyEn.isNotEmpty ? bodyEn : bodyAr);

  Map<String, Object?> toJson() => {
        'id': id,
        'title_ar': titleAr,
        'title_en': titleEn,
        'body_ar': bodyAr,
        'body_en': bodyEn,
        'image': imageUrl,
        'discount_label': discountLabel,
      };

  static MobileAppOffer? fromMap(Object? raw) {
    if (raw is! Map) return null;
    final m = raw.map((k, v) => MapEntry(k.toString(), v));
    return MobileAppOffer(
      id: MobileAppConfiguration._asInt(m['id']),
      titleAr: (m['title_ar'] ?? '').toString(),
      titleEn: (m['title_en'] ?? '').toString(),
      bodyAr: (m['body_ar'] ?? '').toString(),
      bodyEn: (m['body_en'] ?? '').toString(),
      imageUrl: (m['image'] ?? m['image_path'] ?? '').toString(),
      discountLabel: (m['discount_label'] ?? '').toString(),
    );
  }
}

/// Immutable runtime config — single source for branding + feature flags.
final class MobileAppConfiguration {
  const MobileAppConfiguration({
    required this.companyId,
    required this.companyName,
    required this.appName,
    required this.logoUrl,
    required this.iconUrl,
    required this.splashUrl,
    required this.themeColorHex,
    required this.features,
    required this.mobileActive,
    required this.role,
    required this.fetchedAt,
    this.fromCache = false,
    this.extensions = const {},
    this.offers = const [],
  });

  final int companyId;
  final String companyName;
  final String appName;
  final String logoUrl;
  final String iconUrl;
  final String splashUrl;
  final String themeColorHex;
  final Map<String, bool> features;
  final bool mobileActive;
  final AppWorkspaceRole role;
  final DateTime fetchedAt;
  final bool fromCache;

  /// Reserved for future localization packs, custom home layouts, etc.
  final Map<String, Object?> extensions;

  /// Active promotional offers from ERP.
  final List<MobileAppOffer> offers;

  String get displayName {
    final n = BrandDisplay.normalizeAppName(appName);
    if (n.isNotEmpty) return n;
    return BrandDisplay.normalizeAppName(companyName.trim());
  }

  bool isFeatureEnabled(String key) {
    if (features[key] == true) return true;
    // Alias: payslips ↔ payroll for backward-compatible MobileConfig.
    if (key == MobileFeatureKey.payslips) {
      return features[MobileFeatureKey.payroll] == true;
    }
    if (key == MobileFeatureKey.payroll) {
      return features[MobileFeatureKey.payslips] == true;
    }
    return false;
  }

  MobileAppConfiguration copyWith({
    bool? fromCache,
    Map<String, bool>? features,
    Map<String, Object?>? extensions,
    List<MobileAppOffer>? offers,
  }) {
    return MobileAppConfiguration(
      companyId: companyId,
      companyName: companyName,
      appName: appName,
      logoUrl: logoUrl,
      iconUrl: iconUrl,
      splashUrl: splashUrl,
      themeColorHex: themeColorHex,
      features: features ?? this.features,
      mobileActive: mobileActive,
      role: role,
      fetchedAt: fetchedAt,
      fromCache: fromCache ?? this.fromCache,
      extensions: extensions ?? this.extensions,
      offers: offers ?? this.offers,
    );
  }

  Map<String, Object?> toJson() => {
        'company_id': companyId,
        'company_name': companyName,
        'app_name': appName,
        'logo': logoUrl,
        'icon': iconUrl,
        'splash': splashUrl,
        'theme_color': themeColorHex,
        'features': features,
        'mobile_active': mobileActive,
        'role': role.name,
        'fetched_at': fetchedAt.toIso8601String(),
        'extensions': extensions,
        'offers': offers.map((o) => o.toJson()).toList(),
      };

  static MobileAppConfiguration fromErpBody(
    Map<String, Object?> body, {
    required DateTime fetchedAt,
    bool fromCache = false,
  }) {
    final rawFeatures = body['features'];
    final features = <String, bool>{};
    if (rawFeatures is Map) {
      for (final e in rawFeatures.entries) {
        features[e.key.toString()] = _asBool(e.value);
      }
    }

    final roleRaw = (body['role'] ?? body['workspace_role'] ?? 'employee')
        .toString()
        .toLowerCase()
        .trim();
    final role = AppWorkspaceRole.values.firstWhere(
      (r) => r.name == roleRaw,
      orElse: () => AppWorkspaceRole.employee,
    );

    final extensions = <String, Object?>{};
    final rawExt = body['extensions'];
    if (rawExt is Map) {
      for (final e in rawExt.entries) {
        extensions[e.key.toString()] = e.value;
      }
    }

    final offers = <MobileAppOffer>[];
    final rawOffers = body['offers'];
    if (rawOffers is List) {
      for (final item in rawOffers) {
        final offer = MobileAppOffer.fromMap(item);
        if (offer != null) offers.add(offer);
      }
    }

    return MobileAppConfiguration(
      companyId: _asInt(body['company_id']),
      companyName: BrandDisplay.normalizeAppName(
        (body['company_name'] ?? '').toString(),
      ),
      appName: BrandDisplay.normalizeAppName(
        (body['app_name'] ?? '').toString(),
      ),
      logoUrl: (body['logo'] ?? '').toString(),
      iconUrl: (body['icon'] ?? '').toString(),
      splashUrl: (body['splash'] ?? '').toString(),
      themeColorHex: _normalizeHex((body['theme_color'] ?? '#0D6EFD').toString()),
      features: features,
      mobileActive: body['success'] == true || body['mobile_active'] == true,
      role: role,
      fetchedAt: fetchedAt,
      fromCache: fromCache,
      extensions: extensions,
      offers: offers,
    );
  }

  static MobileAppConfiguration? fromJson(
    Map<String, Object?> json, {
    bool fromCache = true,
  }) {
    try {
      final fetched = DateTime.tryParse((json['fetched_at'] ?? '').toString()) ??
          DateTime.fromMillisecondsSinceEpoch(0);
      final withMeta = Map<String, Object?>.from(json)
        ..['success'] = json['mobile_active'] == true || json['success'] == true;
      return fromErpBody(withMeta, fetchedAt: fetched, fromCache: fromCache);
    } catch (_) {
      return null;
    }
  }

  static int _asInt(Object? v) {
    if (v is int) return v;
    return int.tryParse(v?.toString() ?? '') ?? 0;
  }

  static bool _asBool(Object? v) {
    if (v is bool) return v;
    if (v is num) return v != 0;
    final s = v?.toString().toLowerCase();
    return s == '1' || s == 'true' || s == 'yes' || s == 'on';
  }

  static String _normalizeHex(String raw) {
    var s = raw.trim();
    if (s.isEmpty) return '#0D6EFD';
    if (!s.startsWith('#')) s = '#$s';
    return s;
  }
}

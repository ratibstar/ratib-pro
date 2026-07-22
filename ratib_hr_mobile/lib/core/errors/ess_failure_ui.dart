/// ESS failure → user-facing copy + connectivity signal (presentation only).
library;

import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';

abstract final class EssFailureUi {
  static bool isConnectivity(AppFailure f) =>
      f.code == 'network' || f.code == 'timeout';

  static bool isConnectivityCode(String? code) =>
      code == 'network' || code == 'timeout';

  /// Marks shell offline banner when the failure is transport-level.
  static void signalIfOffline(AppFailure f) {
    if (!isConnectivity(f)) return;
    try {
      AppLocator.connectivity.markOffline(f.message);
    } catch (_) {
      // Locator may be unbound in pure unit tests.
    }
  }

  static AppFailure normalize(Object e) =>
      e is AppFailure ? e : AppFailure(code: 'unknown', message: '$e');

  static String message(AppLocalizations l10n, AppFailure f) {
    if (isConnectivity(f)) return l10n.offlineNeedsConnection;
    final m = f.message?.trim();
    if (m != null && m.isNotEmpty && !isConnectivityCode(m)) return m;
    return l10n.genericLoadFailed;
  }

  /// For screens that already stored [errorCode] / [errorMessage].
  static String fromStored(
    AppLocalizations l10n, {
    String? code,
    String? message,
  }) {
    if (isConnectivityCode(code) || isConnectivityCode(message)) {
      return l10n.offlineNeedsConnection;
    }
    final m = message?.trim();
    if (m != null && m.isNotEmpty) return m;
    return l10n.genericLoadFailed;
  }
}

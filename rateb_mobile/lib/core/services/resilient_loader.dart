import '../api/api_exception.dart';
import 'network_monitor.dart';
import 'screen_cache.dart';

class ScreenLoadResult<T> {
  const ScreenLoadResult({
    this.data,
    this.isLoading = false,
    this.isFromCache = false,
    this.error,
    this.autoRetryAttempt = 0,
    this.isAutoRetrying = false,
  });

  final T? data;
  final bool isLoading;
  final bool isFromCache;
  final String? error;
  final int autoRetryAttempt;
  final bool isAutoRetrying;

  bool get hasData => data != null;
  bool get showError => error != null && !hasData;
  bool get showStaleData => hasData && error != null;

  ScreenLoadResult<T> copyWith({
    T? data,
    bool? isLoading,
    bool? isFromCache,
    String? error,
    int? autoRetryAttempt,
    bool? isAutoRetrying,
    bool clearError = false,
  }) {
    return ScreenLoadResult<T>(
      data: data ?? this.data,
      isLoading: isLoading ?? this.isLoading,
      isFromCache: isFromCache ?? this.isFromCache,
      error: clearError ? null : (error ?? this.error),
      autoRetryAttempt: autoRetryAttempt ?? this.autoRetryAttempt,
      isAutoRetrying: isAutoRetrying ?? this.isAutoRetrying,
    );
  }
}

/// Fetch with cache, auto-retry (max 2), and offline fallback.
class ResilientLoader {
  ResilientLoader._();

  static const maxAutoRetries = 2;

  static Future<ScreenLoadResult<T>> execute<T>({
    required String cacheKey,
    required Future<T> Function() fetch,
    bool manualRetry = false,
  }) async {
    final cache = ScreenCache.instance;
    final cached = cache.get<T>(cacheKey);
    var autoRetryAttempt = manualRetry ? 0 : 0;

    if (cached != null && !manualRetry) {
      // Show cache immediately, refresh in background.
      _refresh<T>(
        cacheKey: cacheKey,
        fetch: fetch,
        cached: cached,
        autoRetryAttempt: 0,
      );
      return ScreenLoadResult(
        data: cached,
        isFromCache: true,
        isLoading: false,
      );
    }

    var result = ScreenLoadResult<T>(
      data: cached,
      isLoading: true,
      isFromCache: cached != null,
      autoRetryAttempt: autoRetryAttempt,
    );

    while (autoRetryAttempt <= maxAutoRetries) {
      if (autoRetryAttempt > 0) {
        result = result.copyWith(
          isAutoRetrying: true,
          autoRetryAttempt: autoRetryAttempt,
        );
        await Future<void>.delayed(
          Duration(milliseconds: 600 * autoRetryAttempt),
        );
      }

      try {
        final data = await fetch();
        cache.set(cacheKey, data);
        NetworkMonitor.instance.markOnline();
        return ScreenLoadResult(
          data: data,
          isLoading: false,
          isFromCache: false,
          autoRetryAttempt: autoRetryAttempt,
        );
      } on ApiException catch (e) {
        if (_isNetworkFailure(e)) {
          NetworkMonitor.instance.markOffline();
        }

        if (_shouldNotRetry(e) || autoRetryAttempt >= maxAutoRetries) {
          if (cached != null && _isNetworkFailure(e)) {
            return ScreenLoadResult(
              data: cached,
              isLoading: false,
              isFromCache: true,
              error: _offlineMessage(e.message),
              autoRetryAttempt: autoRetryAttempt,
            );
          }

          return ScreenLoadResult(
            isLoading: false,
            error: e.message,
            autoRetryAttempt: autoRetryAttempt,
          );
        }

        autoRetryAttempt++;
        continue;
      } catch (e) {
        NetworkMonitor.instance.markOffline();

        if (autoRetryAttempt < maxAutoRetries) {
          autoRetryAttempt++;
          continue;
        }

        if (cached != null) {
          return ScreenLoadResult(
            data: cached,
            isLoading: false,
            isFromCache: true,
            error: _offlineMessage(e.toString()),
            autoRetryAttempt: autoRetryAttempt,
          );
        }

        return ScreenLoadResult(
          isLoading: false,
          error: 'Something went wrong. Please try again.',
          autoRetryAttempt: autoRetryAttempt,
        );
      }
    }

    return result;
  }

  static Future<void> _refresh<T>({
    required String cacheKey,
    required Future<T> Function() fetch,
    required T cached,
    required int autoRetryAttempt,
  }) async {
    var attempt = 0;
    while (attempt <= maxAutoRetries) {
      if (attempt > 0) {
        await Future<void>.delayed(Duration(milliseconds: 600 * attempt));
      }
      try {
        final data = await fetch();
        ScreenCache.instance.set(cacheKey, data);
        NetworkMonitor.instance.markOnline();
        return;
      } on ApiException catch (e) {
        if (_shouldNotRetry(e)) {
          return;
        }
        if (_isNetworkFailure(e)) {
          NetworkMonitor.instance.markOffline();
        }
        if (attempt < maxAutoRetries) {
          attempt++;
          continue;
        }
        return;
      } catch (_) {
        NetworkMonitor.instance.markOffline();
        if (attempt < maxAutoRetries) {
          attempt++;
          continue;
        }
        return;
      }
    }
  }

  static bool _isNetworkFailure(ApiException e) {
    return e.statusCode == null ||
        e.statusCode == 0 ||
        e.statusCode == 408 ||
        e.message.toLowerCase().contains('reach server') ||
        e.message.toLowerCase().contains('timed out');
  }

  static bool _shouldNotRetry(ApiException e) {
    final code = e.statusCode;
    return code == 401 || code == 403 || code == 400 || code == 404;
  }

  static String _offlineMessage(String detail) {
    return 'Offline — showing saved data';
  }
}

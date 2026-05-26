import 'package:flutter/foundation.dart';

/// Tracks online/offline state based on API outcomes.
class NetworkMonitor extends ChangeNotifier {
  NetworkMonitor._();

  static final NetworkMonitor instance = NetworkMonitor._();

  bool _isOnline = true;
  bool _simulateOffline = false;

  bool get isOnline => _isOnline;

  bool get simulateOffline => _simulateOffline && kDebugMode;

  bool get isEffectivelyOnline => !_simulateOffline && _isOnline;

  void setSimulateOffline(bool value) {
    if (!kDebugMode) return;
    if (_simulateOffline == value) return;
    _simulateOffline = value;
    notifyListeners();
  }

  void markOnline() {
    if (!_isOnline) {
      _isOnline = true;
      notifyListeners();
    }
  }

  void markOffline() {
    if (_isOnline) {
      _isOnline = false;
      notifyListeners();
    }
  }
}

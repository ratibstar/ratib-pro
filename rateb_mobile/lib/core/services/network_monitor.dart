import 'package:flutter/foundation.dart';

/// Tracks online/offline state based on API outcomes.
class NetworkMonitor extends ChangeNotifier {
  NetworkMonitor._();

  static final NetworkMonitor instance = NetworkMonitor._();

  bool _isOnline = true;

  bool get isOnline => _isOnline;

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

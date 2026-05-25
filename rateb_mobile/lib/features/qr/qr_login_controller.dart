import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/models/auth_response.dart';
import 'qr_service.dart';

enum QrLoginStatus { idle, scanning, processing, success, error }

class QrLoginController extends ChangeNotifier {
  QrLoginController({QrService? service}) : _service = service ?? QrService();

  final QrService _service;

  QrLoginStatus _status = QrLoginStatus.idle;
  String? _errorMessage;
  AuthResponse? _lastAuth;
  bool _handledScan = false;

  QrLoginStatus get status => _status;
  String? get errorMessage => _errorMessage;
  AuthResponse? get lastAuth => _lastAuth;
  bool get isBusy =>
      _status == QrLoginStatus.processing ||
      _status == QrLoginStatus.scanning;

  void startScanning() {
    _handledScan = false;
    _errorMessage = null;
    _status = QrLoginStatus.scanning;
    notifyListeners();
  }

  void reset() {
    _handledScan = false;
    _errorMessage = null;
    _lastAuth = null;
    _status = QrLoginStatus.idle;
    notifyListeners();
  }

  Future<AuthResponse?> submitPayload(String rawPayload) async {
    final payload = rawPayload.trim();
    if (payload.isEmpty) {
      _errorMessage = 'Empty QR code.';
      _status = QrLoginStatus.error;
      notifyListeners();
      return null;
    }

    if (_handledScan && _status == QrLoginStatus.processing) {
      return null;
    }

    _handledScan = true;
    _status = QrLoginStatus.processing;
    _errorMessage = null;
    notifyListeners();

    try {
      final auth = await _service.loginWithPayload(payload);
      _lastAuth = auth;
      _status = QrLoginStatus.success;
      notifyListeners();
      return auth;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _status = QrLoginStatus.error;
      _handledScan = false;
      notifyListeners();
      return null;
    } catch (e) {
      _errorMessage = kDebugMode
          ? 'QR login failed: $e'
          : 'QR login failed. Try again or use password.';
      _status = QrLoginStatus.error;
      _handledScan = false;
      notifyListeners();
      return null;
    }
  }
}

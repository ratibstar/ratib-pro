/// Connectivity + last sync outcome — presentation only (no HR rules).
library;

import 'package:flutter/foundation.dart';
import 'package:ratib_hr_mobile/core/contracts/erp_http_client.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';

enum SyncOutcome { idle, waiting, completed, failed }

class ConnectivityController extends ChangeNotifier {
  ConnectivityController({ErpHttpClient? http}) : _http = http;

  ErpHttpClient? _http;

  bool online = true;
  SyncOutcome lastOutcome = SyncOutcome.idle;
  String? lastMessage;
  DateTime? lastSyncAt;

  void bindHttp(ErpHttpClient http) {
    _http = http;
  }

  void markOffline([String? message]) {
    online = false;
    lastOutcome = SyncOutcome.waiting;
    if (message != null) lastMessage = message;
    notifyListeners();
  }

  void markOnline() {
    online = true;
    notifyListeners();
  }

  void reportCompleted([String? message]) {
    online = true;
    lastOutcome = SyncOutcome.completed;
    lastMessage = message;
    lastSyncAt = DateTime.now().toUtc();
    notifyListeners();
  }

  void reportFailed([String? message]) {
    lastOutcome = SyncOutcome.failed;
    lastMessage = message;
    lastSyncAt = DateTime.now().toUtc();
    notifyListeners();
  }

  /// Lightweight probe — does not interpret HR domain payloads.
  Future<bool> probe() async {
    final http = _http;
    if (http == null) return online;
    try {
      await http.get('/api/v1/hr/me');
      markOnline();
      return true;
    } on AppFailure catch (e) {
      if (e.code == 'network' || e.code == 'timeout') {
        markOffline(e.message);
        return false;
      }
      // Auth/domain errors still mean the network reached ERP.
      markOnline();
      return true;
    } catch (_) {
      markOffline();
      return false;
    }
  }
}

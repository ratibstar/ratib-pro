/// Payslips UI state — presentation only.
library;

import 'package:flutter/foundation.dart';
import 'package:ratib_hr_mobile/core/errors/ess_failure_ui.dart';
import 'package:ratib_hr_mobile/features/payslips/payslip_repository.dart';

enum PayslipLoadStatus { idle, loading, ready, error }

class PayslipState extends ChangeNotifier {
  PayslipState({required PayslipRepository repository})
      : _repository = repository;

  final PayslipRepository _repository;

  PayslipLoadStatus status = PayslipLoadStatus.idle;
  String? errorCode;
  String? errorMessage;
  List<Map<String, Object?>> items = const [];
  Map<String, Object?> detail = const {};
  bool downloading = false;
  bool offlineDegraded = false;
  bool fromCache = false;
  List<int>? previewBytes;
  String? previewContentType;
  String? previewFilename;

  Future<void> loadList() async {
    final keepReady = status == PayslipLoadStatus.ready;
    if (!keepReady) {
      status = PayslipLoadStatus.loading;
    }
    errorCode = null;
    errorMessage = null;
    notifyListeners();
    try {
      final snap = await _repository.loadList();
      items = snap.items;
      offlineDegraded = snap.offlineDegraded;
      fromCache = snap.fromCache;
      status = PayslipLoadStatus.ready;
    } catch (e) {
      if (keepReady && EssFailureUi.isConnectivity(EssFailureUi.normalize(e))) {
        offlineDegraded = true;
        status = PayslipLoadStatus.ready;
      } else {
        _setError(e);
      }
    }
    notifyListeners();
  }

  Future<void> loadDetail(String id) async {
    final keepReady = status == PayslipLoadStatus.ready;
    if (!keepReady) {
      status = PayslipLoadStatus.loading;
    }
    errorCode = null;
    errorMessage = null;
    previewBytes = null;
    notifyListeners();
    try {
      final snap = await _repository.loadDetail(id);
      detail = snap.data;
      offlineDegraded = snap.offlineDegraded;
      fromCache = snap.fromCache;
      status = PayslipLoadStatus.ready;
    } catch (e) {
      if (keepReady && EssFailureUi.isConnectivity(EssFailureUi.normalize(e))) {
        offlineDegraded = true;
        status = PayslipLoadStatus.ready;
      } else {
        _setError(e);
      }
    }
    notifyListeners();
  }

  Future<bool> openDownload(String id) async {
    downloading = true;
    notifyListeners();
    try {
      final file = await _repository.download(id);
      previewBytes = file.bytes;
      previewContentType = file.contentType;
      previewFilename = file.filename;
      return true;
    } catch (e) {
      _setError(e);
      return false;
    } finally {
      downloading = false;
      notifyListeners();
    }
  }

  void _setError(Object e) {
    final f = EssFailureUi.normalize(e);
    EssFailureUi.signalIfOffline(f);
    if (EssFailureUi.isConnectivity(f)) {
      offlineDegraded = true;
      status = PayslipLoadStatus.ready;
      return;
    }
    errorCode = f.code;
    errorMessage = f.message;
    status = PayslipLoadStatus.error;
  }
}

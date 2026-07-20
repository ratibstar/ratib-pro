/// Payslips UI state — presentation only.
library;

import 'package:flutter/foundation.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
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
  List<int>? previewBytes;
  String? previewContentType;
  String? previewFilename;

  Future<void> loadList() async {
    status = PayslipLoadStatus.loading;
    errorCode = null;
    errorMessage = null;
    notifyListeners();
    try {
      items = await _repository.loadList();
      status = PayslipLoadStatus.ready;
    } catch (e) {
      _setError(e);
    }
    notifyListeners();
  }

  Future<void> loadDetail(String id) async {
    status = PayslipLoadStatus.loading;
    errorCode = null;
    errorMessage = null;
    previewBytes = null;
    notifyListeners();
    try {
      detail = await _repository.loadDetail(id);
      status = PayslipLoadStatus.ready;
    } catch (e) {
      _setError(e);
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
    final f = e is AppFailure ? e : AppFailure(code: 'unknown', message: '$e');
    errorCode = f.code;
    errorMessage = f.message;
    status = PayslipLoadStatus.error;
  }
}

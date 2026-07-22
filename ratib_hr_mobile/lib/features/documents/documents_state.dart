/// Documents UI state — presentation only.
library;

import 'package:flutter/foundation.dart';
import 'package:ratib_hr_mobile/core/errors/ess_failure_ui.dart';
import 'package:ratib_hr_mobile/features/documents/documents_repository.dart';

enum DocumentsLoadStatus { idle, loading, ready, error }

class DocumentsState extends ChangeNotifier {
  DocumentsState({required DocumentsRepository repository})
      : _repository = repository;

  final DocumentsRepository _repository;

  DocumentsLoadStatus status = DocumentsLoadStatus.idle;
  String? errorCode;
  String? errorMessage;
  List<Map<String, Object?>> items = const [];
  Map<String, Object?> detail = const {};
  String? categoryFilter;
  bool opening = false;
  bool offlineDegraded = false;
  bool fromCache = false;
  List<int>? previewBytes;
  String? previewContentType;
  String? previewFilename;

  Future<void> loadList({String? category}) async {
    final keepReady = status == DocumentsLoadStatus.ready;
    if (!keepReady) {
      status = DocumentsLoadStatus.loading;
    }
    errorCode = null;
    errorMessage = null;
    categoryFilter = category;
    notifyListeners();
    try {
      final snap = await _repository.loadList(category: category);
      items = snap.items;
      offlineDegraded = snap.offlineDegraded;
      fromCache = snap.fromCache;
      status = DocumentsLoadStatus.ready;
    } catch (e) {
      if (keepReady && EssFailureUi.isConnectivity(EssFailureUi.normalize(e))) {
        offlineDegraded = true;
        status = DocumentsLoadStatus.ready;
      } else {
        _setError(e);
      }
    }
    notifyListeners();
  }

  Future<void> loadDetail(String id) async {
    final keepReady = status == DocumentsLoadStatus.ready;
    if (!keepReady) {
      status = DocumentsLoadStatus.loading;
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
      status = DocumentsLoadStatus.ready;
    } catch (e) {
      if (keepReady && EssFailureUi.isConnectivity(EssFailureUi.normalize(e))) {
        offlineDegraded = true;
        status = DocumentsLoadStatus.ready;
      } else {
        _setError(e);
      }
    }
    notifyListeners();
  }

  Future<bool> openFile(String id) async {
    opening = true;
    notifyListeners();
    try {
      final file = await _repository.download(id);
      if (file == null) {
        errorCode = 'no_file';
        errorMessage = 'no_file';
        return false;
      }
      previewBytes = file.bytes;
      previewContentType = file.contentType;
      previewFilename = file.filename;
      return true;
    } catch (e) {
      _setError(e);
      return false;
    } finally {
      opening = false;
      notifyListeners();
    }
  }

  void _setError(Object e) {
    final f = EssFailureUi.normalize(e);
    EssFailureUi.signalIfOffline(f);
    // Soft-open: connectivity never hard-blocks the documents list.
    if (EssFailureUi.isConnectivity(f)) {
      offlineDegraded = true;
      status = DocumentsLoadStatus.ready;
      return;
    }
    errorCode = f.code;
    errorMessage = f.message;
    status = DocumentsLoadStatus.error;
  }
}

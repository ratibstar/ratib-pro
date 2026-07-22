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
  List<int>? previewBytes;
  String? previewContentType;
  String? previewFilename;

  Future<void> loadList({String? category}) async {
    status = DocumentsLoadStatus.loading;
    errorCode = null;
    errorMessage = null;
    categoryFilter = category;
    notifyListeners();
    try {
      items = await _repository.loadList(category: category);
      status = DocumentsLoadStatus.ready;
    } catch (e) {
      _setError(e);
    }
    notifyListeners();
  }

  Future<void> loadDetail(String id) async {
    status = DocumentsLoadStatus.loading;
    errorCode = null;
    errorMessage = null;
    previewBytes = null;
    notifyListeners();
    try {
      detail = await _repository.loadDetail(id);
      status = DocumentsLoadStatus.ready;
    } catch (e) {
      _setError(e);
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
    errorCode = f.code;
    errorMessage = f.message;
    status = DocumentsLoadStatus.error;
  }
}

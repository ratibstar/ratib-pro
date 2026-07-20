/// Documents repository — presentation orchestration over ports only.
library;

import 'package:ratib_hr_mobile/core/contracts/documents_port.dart';

final class DocumentsRepository {
  DocumentsRepository({required DocumentsPort documents})
      : _documents = documents;

  final DocumentsPort _documents;

  Future<List<Map<String, Object?>>> loadList({String? category}) =>
      _documents.listMine(category: category);

  Future<Map<String, Object?>> loadDetail(String id) => _documents.detail(id);

  Future<({List<int> bytes, String? contentType, String? filename})?> download(
    String id,
  ) =>
      _documents.download(id);
}

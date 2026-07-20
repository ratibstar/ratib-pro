/// Employee documents — existing ERP HR documents only.
///
/// View-first. Online only — no offline document sync.
library;

abstract interface class DocumentsPort {
  Future<List<Map<String, Object?>>> listMine({String? category});

  Future<Map<String, Object?>> detail(String documentKey);

  /// Authenticated file bytes when ERP exposes file_url (online only).
  Future<({List<int> bytes, String? contentType, String? filename})?> download(
    String documentKey,
  );
}

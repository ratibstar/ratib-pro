/// Employee documents — existing ERP HR documents only.
///
/// View-first. Upload only if ERP already supports file attach.
/// Phase 0.6: interface only.
library;

abstract interface class DocumentsPort {
  Future<List<Map<String, Object?>>> listMine();

  /// Optional binary upload — must call existing ERP document write if available.
  Future<void> upload(Map<String, Object?> payload);
}

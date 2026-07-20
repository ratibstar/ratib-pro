/// Complaints & inquiries — ERP employee requests surface.
library;

abstract interface class InquiryPort {
  Future<List<Map<String, Object?>>> listMine({String? type});

  Future<void> submit(Map<String, Object?> payload);
}

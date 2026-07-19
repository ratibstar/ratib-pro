/// Presentation failure — transport/UI code only.
///
/// Not an HR domain model. No business rule fields.
library;

/// Opaque failure for UI messaging. Codes are adapter-defined strings.
final class AppFailure {
  const AppFailure({
    required this.code,
    this.message,
  });

  /// Stable machine code (e.g. `network`, `unauthorized`, `erp`).
  final String code;

  /// Optional human-readable message already localized by ERP or adapter.
  final String? message;
}

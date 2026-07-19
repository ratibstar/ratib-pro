/// Immutable ESS employee identity context — presentation session only.
///
/// Not an ERP table. Not an auth authority. Populated from MePort / ERP only.
library;

final class EmployeeContext {
  const EmployeeContext._({
    required this.employeeId,
    required this.record,
  });

  final String employeeId;
  final Map<String, Object?> record;

  String? get name => record['name']?.toString();
  String? get employeeCode => record['employee_code']?.toString();
  String? get email => record['email']?.toString();

  static EmployeeContext? _current;

  /// Sole in-memory ESS identity for this app session.
  static EmployeeContext? get current => _current;

  static bool get isResolved =>
      _current != null && _current!.employeeId.isNotEmpty;

  static void bind(EmployeeContext context) {
    _current = context;
  }

  static void clear() {
    _current = null;
  }

  factory EmployeeContext.fromErpRecord(Map<String, Object?> record) {
    final id = record['id']?.toString() ?? '';
    if (id.isEmpty) {
      throw StateError('ERP employee record missing id');
    }
    return EmployeeContext._(
      employeeId: id,
      record: Map<String, Object?>.unmodifiable(record),
    );
  }

  /// Mandatory accessor for future HR features — throws if unresolved.
  static EmployeeContext requireResolved() {
    final ctx = _current;
    if (ctx == null || ctx.employeeId.isEmpty) {
      throw StateError(
        'EmployeeContext is not resolved. ESS features must not run without MePort.',
      );
    }
    return ctx;
  }
}

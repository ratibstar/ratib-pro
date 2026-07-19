/// Attendance ESS port — maps UI to existing ERP attendance only.
///
/// Check in/out reuse ERP `check_in` / `check_out` fields.
/// No new attendance rules, GPS geofence logic, or queues.
/// Offline create must use existing ERP `attendance.create` queue only.
/// Check out updates are online-only unless ERP already supports update enqueue.
/// Phase 0.6: interface only.
library;

abstract interface class AttendancePort {
  /// Today's attendance snapshot for the current employee (ERP-shaped map).
  Future<Map<String, Object?>> today();

  /// Attendance history for the current employee (list of ERP-shaped maps).
  Future<List<Map<String, Object?>>> history();

  /// Check in — payload keys must match existing ERP attendance create/update.
  Future<void> checkIn(Map<String, Object?> payload);

  /// Check out — payload keys must match existing ERP attendance update.
  Future<void> checkOut(Map<String, Object?> payload);
}

/// Demo workforce identity badge data (UI preview only — no backend yet).
///
/// TODO: Replace with live payload from `/mobile/qr-generate.php` when badge
/// issuance is wired to the mobile app.
class WorkforceBadgeData {
  const WorkforceBadgeData({
    required this.workerName,
    required this.roleLabel,
    required this.companyName,
    required this.workerId,
    required this.statusLabel,
    this.photoUrl,
    this.qrPayloadHint = 'RATEBMOBQR:…',
  });

  final String workerName;
  final String roleLabel;
  final String companyName;
  final String workerId;
  final String statusLabel;
  final String? photoUrl;
  final String qrPayloadHint;

  static const demo = WorkforceBadgeData(
    workerName: 'Ahmed Al-Rashid',
    roleLabel: 'Field Worker',
    companyName: 'RATEB Workforce',
    workerId: 'WRK-10482',
    statusLabel: 'Active',
  );
}

/// Demo workforce identity badge data (UI preview only — no backend yet).
class WorkforceBadgeData {
  const WorkforceBadgeData({
    required this.workerName,
    required this.companyName,
    required this.workerId,
    required this.statusLabel,
    this.photoUrl,
    this.qrPayloadHint = 'RATEB WORKFORCE ID',
  });

  final String workerName;
  final String companyName;
  final String workerId;
  final String statusLabel;
  final String? photoUrl;
  final String qrPayloadHint;

  static const demo = WorkforceBadgeData(
    workerName: 'Ahmed Al-Rashid',
    companyName: 'RATEB Workforce',
    workerId: 'WRK-10482',
    statusLabel: 'Active',
  );
}

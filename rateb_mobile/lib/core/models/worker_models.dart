import 'auth_response.dart';
import 'user_role.dart';

class WorkerDashboardData {
  const WorkerDashboardData({
    required this.profile,
    required this.stats,
    this.worker,
  });

  final UserProfile profile;
  final WorkerDashboardStats stats;
  final WorkerSummary? worker;

  factory WorkerDashboardData.fromJson(Map<String, dynamic> json) {
    final profileJson = json['profile'] as Map<String, dynamic>? ?? {};
    final statsJson = json['stats'] as Map<String, dynamic>? ?? {};
    final role = UserRole.fromString(profileJson['role'] as String?) ??
        UserRole.worker;

    return WorkerDashboardData(
      profile: UserProfile.fromJson(profileJson, role),
      stats: WorkerDashboardStats.fromJson(statsJson),
      worker: json['worker'] != null
          ? WorkerSummary.fromJson(json['worker'] as Map<String, dynamic>)
          : null,
    );
  }
}

class WorkerDashboardStats {
  const WorkerDashboardStats({
    required this.pendingTasks,
    required this.dueToday,
    required this.hasWorkerRecord,
    required this.documentsPending,
  });

  final int pendingTasks;
  final int dueToday;
  final bool hasWorkerRecord;
  final bool documentsPending;

  factory WorkerDashboardStats.fromJson(Map<String, dynamic> json) {
    return WorkerDashboardStats(
      pendingTasks: (json['pending_tasks'] as num?)?.toInt() ?? 0,
      dueToday: (json['due_today'] as num?)?.toInt() ?? 0,
      hasWorkerRecord: json['has_worker_record'] == true,
      documentsPending: json['documents_pending'] == true,
    );
  }
}

class WorkerSummary {
  const WorkerSummary({
    required this.id,
    required this.name,
    required this.status,
    this.passportNumber,
  });

  final int id;
  final String name;
  final String status;
  final String? passportNumber;

  factory WorkerSummary.fromJson(Map<String, dynamic> json) {
    return WorkerSummary(
      id: (json['id'] as num?)?.toInt() ?? 0,
      name: json['name'] as String? ?? '',
      status: json['status'] as String? ?? '',
      passportNumber: json['passport_number'] as String?,
    );
  }
}

class WorkerTask {
  const WorkerTask({
    required this.id,
    required this.title,
    required this.subtitle,
    required this.dueLabel,
    required this.status,
    required this.category,
  });

  final String id;
  final String title;
  final String subtitle;
  final String dueLabel;
  final String status;
  final String category;

  factory WorkerTask.fromJson(Map<String, dynamic> json) {
    return WorkerTask(
      id: json['id'] as String? ?? '',
      title: json['title'] as String? ?? '',
      subtitle: json['subtitle'] as String? ?? '',
      dueLabel: json['due_label'] as String? ?? '',
      status: json['status'] as String? ?? '',
      category: json['category'] as String? ?? '',
    );
  }
}

class CompanyWorker {
  const CompanyWorker({
    required this.id,
    required this.name,
    required this.subtitle,
    required this.status,
    this.email,
    this.agentName,
  });

  final int id;
  final String name;
  final String subtitle;
  final String status;
  final String? email;
  final String? agentName;

  factory CompanyWorker.fromJson(Map<String, dynamic> json) {
    return CompanyWorker(
      id: (json['id'] as num?)?.toInt() ?? 0,
      name: json['name'] as String? ?? '',
      subtitle: json['subtitle'] as String? ?? '',
      status: json['status'] as String? ?? '',
      email: json['email'] as String?,
      agentName: json['agent_name'] as String?,
    );
  }
}

class CompanyWorkersData {
  const CompanyWorkersData({
    required this.workers,
    required this.total,
    required this.active,
    required this.pending,
  });

  final List<CompanyWorker> workers;
  final int total;
  final int active;
  final int pending;

  factory CompanyWorkersData.fromJson(Map<String, dynamic> json) {
    final list = json['workers'] as List<dynamic>? ?? [];
    final stats = json['stats'] as Map<String, dynamic>? ?? {};

    return CompanyWorkersData(
      workers: list
          .map((e) => CompanyWorker.fromJson(e as Map<String, dynamic>))
          .toList(),
      total: (stats['total'] as num?)?.toInt() ?? list.length,
      active: (stats['active'] as num?)?.toInt() ?? 0,
      pending: (stats['pending'] as num?)?.toInt() ?? 0,
    );
  }
}

class CompanyRequest {
  const CompanyRequest({
    required this.id,
    required this.title,
    required this.subtitle,
    required this.status,
    this.updatedLabel,
  });

  final int id;
  final String title;
  final String subtitle;
  final String status;
  final String? updatedLabel;

  factory CompanyRequest.fromJson(Map<String, dynamic> json) {
    return CompanyRequest(
      id: (json['id'] as num?)?.toInt() ?? 0,
      title: json['title'] as String? ?? '',
      subtitle: json['subtitle'] as String? ?? '',
      status: json['status'] as String? ?? '',
      updatedLabel: json['updated_label'] as String?,
    );
  }
}

class CompanyRequestsData {
  const CompanyRequestsData({
    required this.requests,
    required this.total,
    required this.open,
  });

  final List<CompanyRequest> requests;
  final int total;
  final int open;

  factory CompanyRequestsData.fromJson(Map<String, dynamic> json) {
    final list = json['requests'] as List<dynamic>? ?? [];
    final stats = json['stats'] as Map<String, dynamic>? ?? {};

    return CompanyRequestsData(
      requests: list
          .map((e) => CompanyRequest.fromJson(e as Map<String, dynamic>))
          .toList(),
      total: (stats['total'] as num?)?.toInt() ?? list.length,
      open: (stats['open'] as num?)?.toInt() ?? 0,
    );
  }
}

class CompanyDashboardData {
  const CompanyDashboardData({
    required this.activeWorkers,
    required this.pendingWorkers,
    required this.openRequests,
    required this.totalWorkers,
  });

  final int activeWorkers;
  final int pendingWorkers;
  final int openRequests;
  final int totalWorkers;

  factory CompanyDashboardData.fromJson(Map<String, dynamic> json) {
    final workerStats = json['worker_stats'] as Map<String, dynamic>? ?? {};
    final requestStats = json['request_stats'] as Map<String, dynamic>? ?? {};

    return CompanyDashboardData(
      activeWorkers: (workerStats['active'] as num?)?.toInt() ?? 0,
      pendingWorkers: (workerStats['pending'] as num?)?.toInt() ?? 0,
      openRequests: (requestStats['open'] as num?)?.toInt() ?? 0,
      totalWorkers: (workerStats['total'] as num?)?.toInt() ?? 0,
    );
  }
}

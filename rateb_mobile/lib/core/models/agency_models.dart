class PipelineStage {
  const PipelineStage({
    required this.name,
    required this.count,
    required this.statusKey,
  });

  final String name;
  final int count;
  final String statusKey;

  factory PipelineStage.fromJson(Map<String, dynamic> json) {
    return PipelineStage(
      name: json['name'] as String? ?? '',
      count: (json['count'] as num?)?.toInt() ?? 0,
      statusKey: json['status_key'] as String? ?? '',
    );
  }
}

class AgencyPipelineData {
  const AgencyPipelineData({
    required this.stages,
    required this.totalCandidates,
    required this.deployed,
    required this.cvs,
  });

  final List<PipelineStage> stages;
  final int totalCandidates;
  final int deployed;
  final int cvs;

  factory AgencyPipelineData.fromJson(Map<String, dynamic> json) {
    final list = json['stages'] as List<dynamic>? ?? [];
    final stats = json['stats'] as Map<String, dynamic>? ?? {};

    return AgencyPipelineData(
      stages: list
          .map((e) => PipelineStage.fromJson(e as Map<String, dynamic>))
          .toList(),
      totalCandidates: (stats['total_candidates'] as num?)?.toInt() ?? 0,
      deployed: (stats['deployed'] as num?)?.toInt() ?? 0,
      cvs: (stats['cvs'] as num?)?.toInt() ?? 0,
    );
  }
}

class AgencyAssignment {
  const AgencyAssignment({
    required this.clientName,
    required this.workersCount,
    required this.subtitle,
  });

  final String clientName;
  final int workersCount;
  final String subtitle;

  factory AgencyAssignment.fromJson(Map<String, dynamic> json) {
    return AgencyAssignment(
      clientName: json['client_name'] as String? ?? '',
      workersCount: (json['workers_count'] as num?)?.toInt() ?? 0,
      subtitle: json['subtitle'] as String? ?? '',
    );
  }
}

class AgencyAssignmentsData {
  const AgencyAssignmentsData({
    required this.assignments,
    required this.totalWorkers,
    required this.activeAssignments,
  });

  final List<AgencyAssignment> assignments;
  final int totalWorkers;
  final int activeAssignments;

  factory AgencyAssignmentsData.fromJson(Map<String, dynamic> json) {
    final list = json['assignments'] as List<dynamic>? ?? [];
    final stats = json['stats'] as Map<String, dynamic>? ?? {};

    return AgencyAssignmentsData(
      assignments: list
          .map((e) => AgencyAssignment.fromJson(e as Map<String, dynamic>))
          .toList(),
      totalWorkers: (stats['total_workers'] as num?)?.toInt() ?? 0,
      activeAssignments: (stats['active_assignments'] as num?)?.toInt() ?? 0,
    );
  }
}

class AgencyDashboardData {
  const AgencyDashboardData({
    required this.totalCandidates,
    required this.deployed,
    required this.activeAssignments,
    required this.cvs,
  });

  final int totalCandidates;
  final int deployed;
  final int activeAssignments;
  final int cvs;

  factory AgencyDashboardData.fromJson(Map<String, dynamic> json) {
    final pipelineStats = json['pipeline_stats'] as Map<String, dynamic>? ?? {};
    final assignmentStats =
        json['assignment_stats'] as Map<String, dynamic>? ?? {};

    return AgencyDashboardData(
      totalCandidates:
          (pipelineStats['total_candidates'] as num?)?.toInt() ?? 0,
      deployed: (pipelineStats['deployed'] as num?)?.toInt() ?? 0,
      activeAssignments:
          (assignmentStats['active_assignments'] as num?)?.toInt() ?? 0,
      cvs: (pipelineStats['cvs'] as num?)?.toInt() ?? 0,
    );
  }
}

import '../api/api_client.dart';
import '../api/api_endpoints.dart';
import '../models/agency_models.dart';
import '../models/auth_response.dart';
import '../models/company_models.dart';
import '../models/user_role.dart';
import '../models/worker_models.dart';

/// Central API access for authenticated mobile data endpoints.
class RatebApiService {
  RatebApiService._();

  static final RatebApiService instance = RatebApiService._();

  late final ApiClient client;

  void init({
    required ApiClient apiClient,
  }) {
    client = apiClient;
  }

  Future<UserProfile> getProfile(UserRole role) async {
    final payload = await client.get(ApiEndpoints.authProfile);
    final data = payload['data'] as Map<String, dynamic>? ?? payload;
    return UserProfile.fromJson(data, role);
  }

  Future<WorkerDashboardData> getWorkerDashboard() async {
    final payload = await client.get(ApiEndpoints.workerDashboard);
    final data = payload['data'] as Map<String, dynamic>? ?? payload;
    return WorkerDashboardData.fromJson(data);
  }

  Future<List<WorkerTask>> getWorkerTasks() async {
    final payload = await client.get(ApiEndpoints.workerTasks);
    final data = payload['data'] as Map<String, dynamic>? ?? payload;
    final list = data['tasks'] as List<dynamic>? ?? [];
    return list
        .map((e) => WorkerTask.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<CompanyWorkersData> getCompanyWorkers() async {
    final payload = await client.get(ApiEndpoints.companyWorkers);
    final data = payload['data'] as Map<String, dynamic>? ?? payload;
    return CompanyWorkersData.fromJson(data);
  }

  Future<CompanyRequestsData> getCompanyRequests() async {
    final payload = await client.get(ApiEndpoints.companyRequests);
    final data = payload['data'] as Map<String, dynamic>? ?? payload;
    return CompanyRequestsData.fromJson(data);
  }

  Future<CompanyDashboardData> getCompanyDashboard() async {
    final results = await Future.wait([
      getCompanyWorkers(),
      getCompanyRequests(),
    ]);
    final workers = results[0] as CompanyWorkersData;
    final requests = results[1] as CompanyRequestsData;

    return CompanyDashboardData.fromJson({
      'worker_stats': {
        'total': workers.total,
        'active': workers.active,
        'pending': workers.pending,
      },
      'request_stats': {
        'open': requests.open,
        'total': requests.total,
      },
    });
  }

  Future<AgencyPipelineData> getAgencyPipeline() async {
    final payload = await client.get(ApiEndpoints.agencyPipeline);
    final data = payload['data'] as Map<String, dynamic>? ?? payload;
    return AgencyPipelineData.fromJson(data);
  }

  Future<AgencyAssignmentsData> getAgencyAssignments() async {
    final payload = await client.get(ApiEndpoints.agencyAssignments);
    final data = payload['data'] as Map<String, dynamic>? ?? payload;
    return AgencyAssignmentsData.fromJson(data);
  }

  Future<AgencyDashboardData> getAgencyDashboard() async {
    final results = await Future.wait([
      getAgencyPipeline(),
      getAgencyAssignments(),
    ]);
    final pipeline = results[0] as AgencyPipelineData;
    final assignments = results[1] as AgencyAssignmentsData;

    return AgencyDashboardData.fromJson({
      'pipeline_stats': {
        'total_candidates': pipeline.totalCandidates,
        'deployed': pipeline.deployed,
        'cvs': pipeline.cvs,
      },
      'assignment_stats': {
        'active_assignments': assignments.activeAssignments,
      },
    });
  }
}

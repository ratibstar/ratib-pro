class ApiEndpoints {
  ApiEndpoints._();

  static const authLogin = '/mobile/login.php';
  static const authProfile = '/mobile/profile.php';
  static const authLogout = '/mobile/logout.php';

  // Role-based placeholders (wire to real endpoints as backend grows)
  static const workerTasks = '/mobile/worker/tasks.php';
  static const companyWorkers = '/mobile/company/workers.php';
  static const companyRequests = '/mobile/company/requests.php';
  static const agencyPipeline = '/mobile/agency/pipeline.php';
  static const agencyAssignments = '/mobile/agency/assignments.php';
}

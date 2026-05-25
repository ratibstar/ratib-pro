/// RATEB mobile portal roles returned by the backend after login.
enum UserRole {
  worker,
  company,
  agency;

  static UserRole? fromString(String? value) {
    switch (value?.toLowerCase().trim()) {
      case 'worker':
        return UserRole.worker;
      case 'company':
        return UserRole.company;
      case 'agency':
        return UserRole.agency;
      default:
        return null;
    }
  }

  String get apiValue => name;

  String get displayName {
    switch (this) {
      case UserRole.worker:
        return 'Worker';
      case UserRole.company:
        return 'Company';
      case UserRole.agency:
        return 'Agency';
    }
  }
}

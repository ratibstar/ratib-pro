/// Employee profile — ERP ESS profile read only.
///
/// Presentation mapping only. No HR validation in Flutter.
library;

abstract interface class ProfilePort {
  Future<Map<String, Object?>> mine();
}

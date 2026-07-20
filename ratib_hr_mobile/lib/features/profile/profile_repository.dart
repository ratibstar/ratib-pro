/// Profile repository — presentation orchestration over ProfilePort.
library;

import 'package:ratib_hr_mobile/core/contracts/profile_port.dart';

final class ProfileRepository {
  ProfileRepository({required ProfilePort profile}) : _profile = profile;

  final ProfilePort _profile;

  Future<Map<String, Object?>> loadMine() => _profile.mine();
}

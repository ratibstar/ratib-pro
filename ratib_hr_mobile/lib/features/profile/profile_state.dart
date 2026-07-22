/// Profile UI state — presentation only.
library;

import 'package:flutter/foundation.dart';
import 'package:ratib_hr_mobile/core/errors/ess_failure_ui.dart';
import 'package:ratib_hr_mobile/features/profile/profile_repository.dart';

enum ProfileLoadStatus { idle, loading, ready, error }

class ProfileState extends ChangeNotifier {
  ProfileState({required ProfileRepository repository})
      : _repository = repository;

  final ProfileRepository _repository;

  ProfileLoadStatus status = ProfileLoadStatus.idle;
  String? errorCode;
  String? errorMessage;
  Map<String, Object?> profile = const {};
  bool offlineDegraded = false;
  bool fromCache = false;

  Future<void> load() async {
    status = ProfileLoadStatus.loading;
    errorCode = null;
    errorMessage = null;
    offlineDegraded = false;
    fromCache = false;
    notifyListeners();
    try {
      final snap = await _repository.loadMine();
      profile = snap.profile;
      offlineDegraded = snap.offlineDegraded;
      fromCache = snap.fromCache;
      status = ProfileLoadStatus.ready;
    } catch (e) {
      final f = EssFailureUi.normalize(e);
      EssFailureUi.signalIfOffline(f);
      errorCode = f.code;
      errorMessage = f.message;
      status = ProfileLoadStatus.error;
    }
    notifyListeners();
  }
}

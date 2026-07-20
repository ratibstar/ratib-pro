/// Profile UI state — presentation only.
library;

import 'package:flutter/foundation.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
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

  Future<void> load() async {
    status = ProfileLoadStatus.loading;
    errorCode = null;
    errorMessage = null;
    notifyListeners();
    try {
      profile = await _repository.loadMine();
      status = ProfileLoadStatus.ready;
    } catch (e) {
      final f = e is AppFailure ? e : AppFailure(code: 'unknown', message: '$e');
      errorCode = f.code;
      errorMessage = f.message;
      status = ProfileLoadStatus.error;
    }
    notifyListeners();
  }
}

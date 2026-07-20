/// Port — load tenant mobile config from ERP (no UI).
library;

import 'package:ratib_hr_mobile/core/mobile_config/mobile_app_configuration.dart';

abstract interface class MobileConfigPort {
  /// Fetches live config for the authenticated tenant token.
  /// Throws [AppFailure] with `mobile_disabled` when ERP returns 403.
  Future<MobileAppConfiguration> fetchRemote();
}

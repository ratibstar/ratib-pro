/// Maps transport / ERP error envelopes to presentation-safe codes.
///
/// Must not interpret HR business outcomes (e.g. leave policy).
/// Phase 0.6: interface only.
library;

import 'package:ratib_hr_mobile/core/errors/app_failure.dart';

abstract interface class ErrorMapper {
  /// Converts an unknown thrown object into a presentation [AppFailure].
  AppFailure map(Object error, [StackTrace? stackTrace]);
}

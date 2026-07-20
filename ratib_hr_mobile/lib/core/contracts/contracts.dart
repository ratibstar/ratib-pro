/// Barrel export — architecture contracts only.
///
/// Features may import this library. Features must not import each other.
/// Features must not import ERP PHP/JS packages (none exist in this app).
library;

export 'package:ratib_hr_mobile/core/contracts/approval_port.dart';
export 'package:ratib_hr_mobile/core/contracts/attendance_port.dart';
export 'package:ratib_hr_mobile/core/contracts/auth_port.dart';
export 'package:ratib_hr_mobile/core/contracts/biometric_unlock.dart';
export 'package:ratib_hr_mobile/core/contracts/cache_store.dart';
export 'package:ratib_hr_mobile/core/contracts/documents_port.dart';
export 'package:ratib_hr_mobile/core/contracts/employee_request_port.dart';
export 'package:ratib_hr_mobile/core/contracts/erp_http_client.dart';
export 'package:ratib_hr_mobile/core/contracts/error_mapper.dart';
export 'package:ratib_hr_mobile/core/contracts/leave_port.dart';
export 'package:ratib_hr_mobile/core/contracts/me_port.dart';
export 'package:ratib_hr_mobile/core/contracts/mobile_config_port.dart';
export 'package:ratib_hr_mobile/core/contracts/notification_port.dart';
export 'package:ratib_hr_mobile/core/contracts/offline_queue_port.dart';
export 'package:ratib_hr_mobile/core/contracts/payslip_port.dart';
export 'package:ratib_hr_mobile/core/contracts/permission_request_port.dart';
export 'package:ratib_hr_mobile/core/contracts/secure_token_store.dart';
export 'package:ratib_hr_mobile/core/env/app_environment.dart';
export 'package:ratib_hr_mobile/core/env/app_flavor.dart';
export 'package:ratib_hr_mobile/core/errors/app_failure.dart';

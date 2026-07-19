/// Deprecated stub — use [SecureTokenStore] / [CacheStore] contracts.
///
/// Kept so Phase 0 imports do not break. No storage implementation.
library;

export 'package:ratib_hr_mobile/core/contracts/cache_store.dart';
export 'package:ratib_hr_mobile/core/contracts/secure_token_store.dart';

@Deprecated('Use SecureTokenStore / CacheStore contracts')
const bool kSecureStorageEnabled = false;

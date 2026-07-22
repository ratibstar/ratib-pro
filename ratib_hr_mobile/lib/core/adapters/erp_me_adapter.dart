/// MePort → existing ERP `GET /api/v1/hr/me` (user_id linkage only).
///
/// Persists identity **claims** only (employee record) for offline restore.
/// Never stores passwords, tokens, or auth secrets.
library;

import 'dart:convert';

import 'package:ratib_hr_mobile/core/contracts/cache_store.dart';
import 'package:ratib_hr_mobile/core/contracts/error_mapper.dart';
import 'package:ratib_hr_mobile/core/contracts/erp_http_client.dart';
import 'package:ratib_hr_mobile/core/contracts/me_port.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/identity/employee_context.dart';

final class ErpMeAdapter implements MePort {
  ErpMeAdapter({
    required ErpHttpClient http,
    required ErrorMapper errors,
    CacheStore? cache,
  })  : _http = http,
        _errors = errors,
        _cache = cache;

  static const mePath = '/api/v1/hr/me';
  static const claimsCacheKey = 'ess.identity.claims.v1';

  final ErpHttpClient _http;
  final ErrorMapper _errors;
  final CacheStore? _cache;

  Map<String, Object?>? _sessionCache;

  @override
  Future<Map<String, Object?>> currentEmployee() async {
    if (_sessionCache != null) {
      return Map<String, Object?>.from(_sessionCache!);
    }
    try {
      final body = await _http.get(mePath);
      final success = body['success'] == true;
      final employee = body['employee'];
      if (!success || employee is! Map) {
        final code = body['code']?.toString() ?? 'employee_unbound';
        throw AppFailure(
          code: code,
          message: body['message']?.toString(),
        );
      }
      final record = employee.map((k, v) => MapEntry(k.toString(), v));
      _sessionCache = record;
      EmployeeContext.bind(EmployeeContext.fromErpRecord(record));
      await _persistClaims(record);
      return Map<String, Object?>.from(record);
    } catch (e, st) {
      _sessionCache = null;
      EmployeeContext.clear();
      throw _errors.map(e, st);
    }
  }

  @override
  Future<String?> currentEmployeeId() async {
    if (EmployeeContext.isResolved) {
      return EmployeeContext.current!.employeeId;
    }
    final record = await currentEmployee();
    return record['id']?.toString();
  }

  /// Cold offline restore — bind last successful /me claims from disk.
  Future<bool> hydrateFromDisk() async {
    final record = await _readClaims();
    if (record == null) return false;
    try {
      EmployeeContext.bind(EmployeeContext.fromErpRecord(record));
      _sessionCache = record;
      return true;
    } catch (_) {
      return false;
    }
  }

  void clearCache({bool wipeDisk = false}) {
    _sessionCache = null;
    EmployeeContext.clear();
    if (wipeDisk) {
      final cache = _resolvedCache;
      if (cache != null) {
        // Fire-and-forget disk wipe on explicit logout.
        cache.remove(claimsCacheKey);
      }
    }
  }

  CacheStore? get _resolvedCache {
    if (_cache != null) return _cache;
    try {
      return AppLocator.cache;
    } catch (_) {
      return null;
    }
  }

  Future<void> _persistClaims(Map<String, Object?> record) async {
    final cache = _resolvedCache;
    if (cache == null) return;
    try {
      await cache.write(claimsCacheKey, jsonEncode(record));
    } catch (_) {}
  }

  Future<Map<String, Object?>?> _readClaims() async {
    final cache = _resolvedCache;
    if (cache == null) return null;
    try {
      final raw = await cache.read(claimsCacheKey);
      if (raw == null || raw.isEmpty) return null;
      final decoded = jsonDecode(raw);
      if (decoded is! Map) return null;
      return decoded.map((k, v) => MapEntry(k.toString(), v));
    } catch (_) {
      return null;
    }
  }
}

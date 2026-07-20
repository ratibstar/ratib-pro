/// Local offline queue — ESS allowed actions only.
library;

import 'dart:convert';

import 'package:ratib_hr_mobile/core/contracts/cache_store.dart';
import 'package:ratib_hr_mobile/core/contracts/offline_queue_port.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';

final class LocalOfflineQueueAdapter implements OfflineQueuePort {
  LocalOfflineQueueAdapter({required CacheStore cache}) : _cache = cache;

  static const cacheKey = 'offline.queue.v1';
  static const allowed = {'attendance.create', 'leave_request.draft'};
  static const forbidden = {
    'attendance.update',
    'attendance.delete',
    'payroll',
    'document.upload',
  };

  final CacheStore _cache;

  @override
  Future<bool> supportsExistingAction(String existingAction) async {
    return allowed.contains(existingAction);
  }

  @override
  Future<void> enqueue({
    required String existingAction,
    required Map<String, Object?> payload,
  }) async {
    if (forbidden.contains(existingAction) || !allowed.contains(existingAction)) {
      throw const AppFailure(
        code: 'offline_action_forbidden',
        message: 'Offline action not allowed',
      );
    }
    final safe = Map<String, Object?>.from(payload)
      ..remove('company_id')
      ..remove('role')
      ..remove('permissions');
    final items = await pendingItems();
    items.add({
      'action': existingAction,
      'payload': safe,
      'enqueued_at': DateTime.now().toUtc().toIso8601String(),
      'idempotency_key':
          'ess-${existingAction}-${DateTime.now().millisecondsSinceEpoch}',
    });
    await _persist(items);
  }

  @override
  Future<List<Map<String, Object?>>> pendingItems() async {
    final raw = await _cache.read(cacheKey);
    if (raw == null || raw.isEmpty) return [];
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! List) return [];
      return decoded
          .whereType<Map>()
          .map((e) => e.map((k, v) => MapEntry(k.toString(), v)))
          .toList();
    } catch (_) {
      return [];
    }
  }

  @override
  Future<int> pendingCount() async => (await pendingItems()).length;

  Future<void> clear() async => _cache.write(cacheKey, '[]');

  @override
  Future<void> replaceAll(List<Map<String, Object?>> items) async {
    final filtered = items.where((e) {
      final action = (e['action'] ?? '').toString();
      return allowed.contains(action);
    }).toList();
    await _persist(filtered);
  }

  Future<void> _persist(List<Map<String, Object?>> items) async {
    await _cache.write(cacheKey, jsonEncode(items));
  }
}

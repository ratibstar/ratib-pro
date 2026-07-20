/// Local offline queue — existing ERP action names only (`attendance.create`).
library;

import 'dart:convert';

import 'package:ratib_hr_mobile/core/contracts/cache_store.dart';
import 'package:ratib_hr_mobile/core/contracts/offline_queue_port.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';

final class LocalOfflineQueueAdapter implements OfflineQueuePort {
  LocalOfflineQueueAdapter({required CacheStore cache}) : _cache = cache;

  static const cacheKey = 'offline.queue.v1';
  static const allowed = {'attendance.create', 'leave_request.draft'};

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
    if (!allowed.contains(existingAction)) {
      throw const AppFailure(
        code: 'offline_action_forbidden',
        message: 'Unknown offline action',
      );
    }
    if (existingAction == 'attendance.update') {
      throw const AppFailure(
        code: 'offline_action_forbidden',
        message: 'attendance.update is not allowed',
      );
    }
    final items = await pendingItems();
    items.add({
      'action': existingAction,
      'payload': payload,
      'enqueued_at': DateTime.now().toUtc().toIso8601String(),
      'idempotency_key':
          'ess-${existingAction}-${DateTime.now().millisecondsSinceEpoch}',
    });
    await _persist(items);
  }

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

  Future<int> pendingCount() async => (await pendingItems()).length;

  Future<void> clear() async => _cache.write(cacheKey, '[]');

  Future<void> replaceAll(List<Map<String, Object?>> items) async {
    await _persist(items);
  }

  Future<void> _persist(List<Map<String, Object?>> items) async {
    await _cache.write(cacheKey, jsonEncode(items));
  }
}

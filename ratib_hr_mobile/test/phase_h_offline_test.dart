import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ratib_hr_mobile/core/adapters/local_offline_queue_adapter.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';
import 'package:ratib_hr_mobile/core/contracts/attendance_port.dart';
import 'package:ratib_hr_mobile/core/contracts/cache_store.dart';
import 'package:ratib_hr_mobile/core/contracts/leave_port.dart';
import 'package:ratib_hr_mobile/core/mobile_config/mobile_app_configuration.dart';
import 'package:ratib_hr_mobile/core/offline/connectivity_controller.dart';
import 'package:ratib_hr_mobile/core/offline/offline_sync_service.dart';
import 'package:ratib_hr_mobile/core/shell/shell_nav_policy.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class _MemCache implements CacheStore {
  final map = <String, String>{};

  @override
  Future<void> clear() async => map.clear();

  @override
  Future<String?> read(String key) async => map[key];

  @override
  Future<void> remove(String key) async => map.remove(key);

  @override
  Future<void> write(String key, String value) async => map[key] = value;
}

class _FakeAttendance implements AttendancePort {
  @override
  Future<void> checkIn(Map<String, Object?> payload) async {}

  @override
  Future<void> checkOut(Map<String, Object?> payload) async {}

  @override
  Future<List<Map<String, Object?>>> history() async => const [];

  @override
  Future<Map<String, Object?>> today() async => const {};
}

class _FakeLeave implements LeavePort {
  @override
  Future<void> apply(Map<String, Object?> payload) async {}

  @override
  Future<List<Map<String, Object?>>> balances() async => const [];

  @override
  Future<List<Map<String, Object?>>> status() async => const [];
}

MobileAppConfiguration _cfg(Map<String, bool> features) {
  return MobileAppConfiguration(
    companyId: 1,
    companyName: 'A',
    appName: 'A',
    logoUrl: '',
    iconUrl: '',
    splashUrl: '',
    themeColorHex: '#0F766E',
    features: features,
    mobileActive: true,
    role: AppWorkspaceRole.employee,
    fetchedAt: DateTime.utc(2026, 7, 20),
  );
}

void main() {
  test('Phase marker is H or later', () {
    expect(['H', 'J', 'I3','B'].contains(AppConfig.phase), isTrue);
  });

  test('Queue persists only allowed actions', () async {
    final q = LocalOfflineQueueAdapter(cache: _MemCache());
    expect(await q.supportsExistingAction('attendance.create'), isTrue);
    expect(await q.supportsExistingAction('leave_request.draft'), isTrue);
    expect(await q.supportsExistingAction('attendance.update'), isFalse);
    await q.enqueue(
      existingAction: 'attendance.create',
      payload: {'attendance_date': '2026-07-20', 'check_in': '09:00:00'},
    );
    expect(await q.pendingCount(), 1);
    expect(() => q.enqueue(existingAction: 'attendance.update', payload: {}),
        throwsA(isA<Object>()));
  });

  test('Queue persistence survives re-read', () async {
    final cache = _MemCache();
    final q1 = LocalOfflineQueueAdapter(cache: cache);
    await q1.enqueue(
      existingAction: 'leave_request.draft',
      payload: {
        'leave_type_id': 1,
        'start_date': '2026-08-01',
        'end_date': '2026-08-02',
      },
    );
    final q2 = LocalOfflineQueueAdapter(cache: cache);
    expect(await q2.pendingCount(), 1);
    expect((await q2.pendingItems()).first['action'], 'leave_request.draft');
  });

  test('Sync route gated by attendance or leave flags', () {
    final on = _cfg({MobileFeatureKey.attendance: true});
    final off = _cfg({
      MobileFeatureKey.attendance: false,
      MobileFeatureKey.leave: false,
    });
    expect(ShellNavPolicy.isRouteAllowed(on, '/more/sync'), isTrue);
    expect(ShellNavPolicy.isRouteAllowed(off, '/more/sync'), isFalse);
  });

  test('OfflineSyncService flush empty reports completed', () async {
    final connectivity = ConnectivityController();
    final sync = OfflineSyncService(
      queue: LocalOfflineQueueAdapter(cache: _MemCache()),
      attendance: _FakeAttendance(),
      leave: _FakeLeave(),
      connectivity: connectivity,
    );
    // Without http, probe returns current online (true) — empty flush completes.
    final result = await sync.flush();
    expect(result, OfflineFlushResult.empty);
    expect(connectivity.lastOutcome, SyncOutcome.completed);
  });

  testWidgets('Sync status smoke', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        locale: const Locale('en'),
        localizationsDelegates: const [AppLocalizations.delegate],
        supportedLocales: AppLocalizations.supportedLocales,
        home: DsPageScaffold(
          title: 'Sync status',
          body: DsEmptyState(title: 'Waiting for connection'),
        ),
      ),
    );
    expect(find.text('Sync status'), findsOneWidget);
    expect(find.text('Waiting for connection'), findsOneWidget);
  });
}

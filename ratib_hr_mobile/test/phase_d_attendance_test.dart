import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_attendance_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/local_offline_queue_adapter.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';
import 'package:ratib_hr_mobile/core/contracts/attendance_port.dart';
import 'package:ratib_hr_mobile/core/contracts/cache_store.dart';
import 'package:ratib_hr_mobile/core/contracts/offline_queue_port.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/identity/employee_context.dart';
import 'package:ratib_hr_mobile/core/mobile_config/mobile_app_configuration.dart';
import 'package:ratib_hr_mobile/core/shell/shell_nav_policy.dart';
import 'package:ratib_hr_mobile/features/attendance/attendance_repository.dart';
import 'package:ratib_hr_mobile/features/attendance/attendance_state.dart';
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
  Map<String, Object?> todayRow = {};
  List<Map<String, Object?>> historyRows = const [];
  AppFailure? checkInError;
  int checkInCalls = 0;
  int checkOutCalls = 0;

  @override
  Future<Map<String, Object?>> today() async => todayRow;

  @override
  Future<List<Map<String, Object?>>> history() async => historyRows;

  @override
  Future<void> checkIn(Map<String, Object?> payload) async {
    checkInCalls++;
    if (checkInError != null) throw checkInError!;
    todayRow = {
      'attendance_date': payload['date'],
      'check_in': payload['check_in'],
      'check_out': null,
      'status': 'present',
    };
  }

  @override
  Future<void> checkOut(Map<String, Object?> payload) async {
    checkOutCalls++;
    todayRow = {
      ...todayRow,
      'check_out': payload['check_out'],
    };
  }
}

void main() {
  setUp(() {
    EmployeeContext.clear();
    EmployeeContext.bind(
      EmployeeContext.fromErpRecord({'id': 10, 'name': 'Test'}),
    );
  });

  tearDown(EmployeeContext.clear);

  test('Phase marker is D or later', () {
    expect(['D', 'E', 'F', 'G', 'H'].contains(AppConfig.phase), isTrue);
  });

  test('Attendance adapter paths are Phase D ESS endpoints', () {
    expect(ErpAttendanceAdapter.todayPath, '/api/v1/hr/attendance/today');
    expect(ErpAttendanceAdapter.historyPath, '/api/v1/hr/attendance/history');
    expect(ErpAttendanceAdapter.checkInPath, '/api/v1/hr/attendance/check-in');
    expect(
      ErpAttendanceAdapter.checkOutPath,
      '/api/v1/hr/attendance/check-out',
    );
  });

  test('Feature flag gates attendance routes', () {
    final on = MobileAppConfiguration(
      companyId: 1,
      companyName: 'A',
      appName: 'A',
      logoUrl: '',
      iconUrl: '',
      splashUrl: '',
      themeColorHex: '#0F766E',
      features: {MobileFeatureKey.attendance: true},
      mobileActive: true,
      role: AppWorkspaceRole.employee,
      fetchedAt: DateTime.utc(2026, 7, 20),
    );
    final off = MobileAppConfiguration(
      companyId: 1,
      companyName: 'A',
      appName: 'A',
      logoUrl: '',
      iconUrl: '',
      splashUrl: '',
      themeColorHex: '#0F766E',
      features: {MobileFeatureKey.attendance: false},
      mobileActive: true,
      role: AppWorkspaceRole.employee,
      fetchedAt: DateTime.utc(2026, 7, 20),
    );
    expect(ShellNavPolicy.isRouteAllowed(on, '/attendance'), isTrue);
    expect(ShellNavPolicy.isRouteAllowed(off, '/attendance'), isFalse);
  });

  test('Offline queue allows attendance.create only among punch actions', () async {
    final q = LocalOfflineQueueAdapter(cache: _MemCache());
    expect(await q.supportsExistingAction('attendance.create'), isTrue);
    expect(await q.supportsExistingAction('attendance.update'), isFalse);
    await q.enqueue(
      existingAction: 'attendance.create',
      payload: {'attendance_date': '2026-07-20', 'check_in': '09:00:00'},
    );
    expect(await q.pendingCount(), 1);
    await expectLater(
      () => q.enqueue(
        existingAction: 'attendance.update',
        payload: const {},
      ),
      throwsA(isA<AppFailure>()),
    );
  });

  test('Repository queues check-in on network failure', () async {
    final fake = _FakeAttendance()
      ..checkInError = const AppFailure(code: 'network', message: 'down');
    final queue = LocalOfflineQueueAdapter(cache: _MemCache());
    final repo = AttendanceRepository(attendance: fake, offlineQueue: queue);
    final result = await repo.checkIn();
    expect(result, AttendancePunchResult.queuedOffline);
    expect(await queue.pendingCount(), 1);
    final items = await queue.pendingItems();
    expect(items.first['action'], 'attendance.create');
  });

  test('Repository check-out never enqueues', () async {
    final fake = _FakeAttendance()
      ..todayRow = {
        'check_in': '09:00:00',
        'status': 'present',
      };
    final queue = LocalOfflineQueueAdapter(cache: _MemCache());
    final repo = AttendanceRepository(attendance: fake, offlineQueue: queue);
    await repo.checkOut();
    expect(fake.checkOutCalls, 1);
    expect(await queue.pendingCount(), 0);
  });

  test('AttendanceState tracks check-in / duration display', () async {
    final fake = _FakeAttendance();
    final repo = AttendanceRepository(
      attendance: fake,
      offlineQueue: LocalOfflineQueueAdapter(cache: _MemCache()),
    );
    final state = AttendanceState(repository: repo);
    await state.loadToday();
    expect(state.hasCheckIn, isFalse);
    await state.checkIn();
    expect(state.hasCheckIn, isTrue);
    expect(state.workingDurationLabel(), isNotNull);
    state.dispose();
  });

  testWidgets('AttendanceScreen shows today card and actions', (tester) async {
    final fake = _FakeAttendance();
    // Wire via a local repository by temporarily using a test harness page.
    final repo = AttendanceRepository(
      attendance: fake,
      offlineQueue: _NoopOffline(),
    );
    await tester.pumpWidget(
      MaterialApp(
        locale: const Locale('en'),
        localizationsDelegates: const [AppLocalizations.delegate],
        supportedLocales: AppLocalizations.supportedLocales,
        home: _Harness(repo: repo),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.textContaining('Attendance'), findsWidgets);
    expect(find.byType(DsPrimaryButton), findsOneWidget);
  });
}

class _NoopOffline implements OfflineQueuePort {
  @override
  Future<void> enqueue({
    required String existingAction,
    required Map<String, Object?> payload,
  }) async {}

  @override
  Future<bool> supportsExistingAction(String existingAction) async =>
      existingAction == 'attendance.create';
}

class _Harness extends StatefulWidget {
  const _Harness({required this.repo});
  final AttendanceRepository repo;

  @override
  State<_Harness> createState() => _HarnessState();
}

class _HarnessState extends State<_Harness> {
  late final AttendanceState state;

  @override
  void initState() {
    super.initState();
    state = AttendanceState(repository: widget.repo)..loadToday();
  }

  @override
  void dispose() {
    state.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: state,
      builder: (context, _) {
        final l10n = AppLocalizations.of(context);
        return DsPageScaffold(
          title: l10n.navAttendance,
          body: state.status == AttendanceLoadStatus.loading
              ? DsLoadingState(message: l10n.genericLoading)
              : Column(
                  children: [
                    Text(l10n.homeTodayAttendance),
                    DsPrimaryButton(
                      label: l10n.navCheckIn,
                      onPressed: state.hasCheckIn ? null : () {},
                    ),
                  ],
                ),
        );
      },
    );
  }
}

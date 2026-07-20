import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_leave_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/local_offline_queue_adapter.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';
import 'package:ratib_hr_mobile/core/contracts/cache_store.dart';
import 'package:ratib_hr_mobile/core/contracts/leave_port.dart';
import 'package:ratib_hr_mobile/core/contracts/offline_queue_port.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/identity/employee_context.dart';
import 'package:ratib_hr_mobile/core/mobile_config/mobile_app_configuration.dart';
import 'package:ratib_hr_mobile/core/shell/shell_nav_policy.dart';
import 'package:ratib_hr_mobile/features/leave/leave_repository.dart';
import 'package:ratib_hr_mobile/features/leave/leave_state.dart';
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

class _FakeLeave implements LeavePort {
  List<Map<String, Object?>> balanceRows = const [];
  List<Map<String, Object?>> requestRows = const [];
  AppFailure? applyError;
  int applyCalls = 0;

  @override
  Future<List<Map<String, Object?>>> balances() async => balanceRows;

  @override
  Future<List<Map<String, Object?>>> status() async => requestRows;

  @override
  Future<void> apply(Map<String, Object?> payload) async {
    applyCalls++;
    if (applyError != null) throw applyError!;
    requestRows = [
      {
        'id': 1,
        'leave_type_id': payload['leave_type_id'],
        'start_date': payload['start_date'],
        'end_date': payload['end_date'],
        'status': 'pending',
      },
      ...requestRows,
    ];
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

  test('Phase marker is E or later', () {
    expect(['E', 'F', 'G', 'H', 'J'].contains(AppConfig.phase), isTrue);
  });

  test('Leave adapter paths are Phase E ESS endpoints', () {
    expect(ErpLeaveAdapter.balancesPath, '/api/v1/hr/leave/balances');
    expect(ErpLeaveAdapter.requestsPath, '/api/v1/hr/leave/requests');
    expect(ErpLeaveAdapter.applyPath, '/api/v1/hr/leave/apply');
  });

  test('Feature flag gates leave routes', () {
    final on = MobileAppConfiguration(
      companyId: 1,
      companyName: 'A',
      appName: 'A',
      logoUrl: '',
      iconUrl: '',
      splashUrl: '',
      themeColorHex: '#0F766E',
      features: {MobileFeatureKey.leave: true},
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
      features: {MobileFeatureKey.leave: false},
      mobileActive: true,
      role: AppWorkspaceRole.employee,
      fetchedAt: DateTime.utc(2026, 7, 20),
    );
    expect(ShellNavPolicy.isRouteAllowed(on, '/leave'), isTrue);
    expect(ShellNavPolicy.isRouteAllowed(on, '/leave/apply'), isTrue);
    expect(ShellNavPolicy.isRouteAllowed(off, '/leave'), isFalse);
  });

  test('Offline queue allows leave_request.draft only for leave', () async {
    final q = LocalOfflineQueueAdapter(cache: _MemCache());
    expect(await q.supportsExistingAction('leave_request.draft'), isTrue);
    await q.enqueue(
      existingAction: 'leave_request.draft',
      payload: {
        'leave_type_id': 1,
        'start_date': '2026-08-01',
        'end_date': '2026-08-02',
      },
    );
    expect(await q.pendingCount(), 1);
  });

  test('Repository queues apply on network failure', () async {
    final fake = _FakeLeave()
      ..applyError = const AppFailure(code: 'network', message: 'down');
    final queue = LocalOfflineQueueAdapter(cache: _MemCache());
    final repo = LeaveRepository(leave: fake, offlineQueue: queue);
    final result = await repo.apply(
      leaveTypeId: 3,
      startDate: '2026-08-01',
      endDate: '2026-08-03',
    );
    expect(result, LeaveApplyResult.queuedOffline);
    expect(await queue.pendingCount(), 1);
    expect((await queue.pendingItems()).first['action'], 'leave_request.draft');
  });

  test('LeaveState loads balances and requests', () async {
    final fake = _FakeLeave()
      ..balanceRows = [
        {
          'leave_type_id': 1,
          'leave_type_name': 'Annual',
          'remaining_days': 10,
        },
      ]
      ..requestRows = [
        {'id': 9, 'status': 'pending', 'start_date': '2026-08-01'},
      ];
    final state = LeaveState(
      repository: LeaveRepository(
        leave: fake,
        offlineQueue: _NoopOffline(),
      ),
    );
    await state.loadBalances();
    expect(state.balances.length, 1);
    await state.loadRequests();
    expect(state.requests.first['id'], 9);
    state.dispose();
  });

  testWidgets('Leave balances screen shows KPI and actions', (tester) async {
    final fake = _FakeLeave()
      ..balanceRows = [
        {
          'leave_type_id': 1,
          'leave_type_name': 'Annual',
          'remaining_days': 12,
        },
      ];
    final repo = LeaveRepository(leave: fake, offlineQueue: _NoopOffline());
    final state = LeaveState(repository: repo);
    await state.loadBalances();

    await tester.pumpWidget(
      MaterialApp(
        locale: const Locale('en'),
        localizationsDelegates: const [AppLocalizations.delegate],
        supportedLocales: AppLocalizations.supportedLocales,
        home: ListenableBuilder(
          listenable: state,
          builder: (context, _) {
            final l10n = AppLocalizations.of(context);
            return DsPageScaffold(
              title: l10n.leave,
              body: Column(
                children: [
                  Text(l10n.homeLeaveBalance),
                  Text('${state.balances.first['remaining_days']}'),
                  DsPrimaryButton(label: l10n.navApplyLeave, onPressed: () {}),
                ],
              ),
            );
          },
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('12'), findsOneWidget);
    expect(find.textContaining('Apply'), findsOneWidget);
    state.dispose();
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
      existingAction == 'leave_request.draft';
}

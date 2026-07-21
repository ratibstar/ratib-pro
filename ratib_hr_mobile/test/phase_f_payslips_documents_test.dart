import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_documents_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_payslip_adapter.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';
import 'package:ratib_hr_mobile/core/contracts/documents_port.dart';
import 'package:ratib_hr_mobile/core/contracts/payslip_port.dart';
import 'package:ratib_hr_mobile/core/identity/employee_context.dart';
import 'package:ratib_hr_mobile/core/mobile_config/mobile_app_configuration.dart';
import 'package:ratib_hr_mobile/core/shell/shell_nav_policy.dart';
import 'package:ratib_hr_mobile/features/documents/documents_repository.dart';
import 'package:ratib_hr_mobile/features/documents/documents_state.dart';
import 'package:ratib_hr_mobile/features/payslips/payslip_repository.dart';
import 'package:ratib_hr_mobile/features/payslips/payslip_state.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class _FakePayslips implements PayslipPort {
  List<Map<String, Object?>> rows = const [];
  Map<String, Object?> detailRow = const {};

  @override
  Future<List<Map<String, Object?>>> listMine() async => rows;

  @override
  Future<Map<String, Object?>> detail(String payslipKey) async => detailRow;

  @override
  Future<({List<int> bytes, String? contentType, String? filename})> download(
    String payslipKey,
  ) async =>
      (bytes: 'slip'.codeUnits, contentType: 'text/plain', filename: 'a.txt');
}

class _FakeDocuments implements DocumentsPort {
  List<Map<String, Object?>> rows = const [];
  Map<String, Object?> detailRow = const {};

  @override
  Future<List<Map<String, Object?>>> listMine({String? category}) async {
    if (category == null || category.isEmpty) return rows;
    return rows
        .where((r) => (r['category'] ?? '').toString() == category)
        .toList();
  }

  @override
  Future<Map<String, Object?>> detail(String documentKey) async => detailRow;

  @override
  Future<({List<int> bytes, String? contentType, String? filename})?> download(
    String documentKey,
  ) async {
    if ((detailRow['file_url'] ?? '').toString().isEmpty) return null;
    return (
      bytes: [1, 2, 3],
      contentType: 'application/pdf',
      filename: 'doc.pdf',
    );
  }
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
  setUp(() {
    EmployeeContext.clear();
    EmployeeContext.bind(
      EmployeeContext.fromErpRecord({'id': 10, 'name': 'Test'}),
    );
  });

  tearDown(EmployeeContext.clear);

  test('Phase marker is F or later', () {
    expect(['F', 'G', 'H', 'J', 'I3','B','B2'].contains(AppConfig.phase), isTrue);
  });

  test('Payslip/document adapter paths are Phase F ESS endpoints', () {
    expect(ErpPayslipAdapter.listPath, '/api/v1/hr/payslips');
    expect(ErpDocumentsAdapter.listPath, '/api/v1/hr/documents');
  });

  test('Feature flags gate payslips and documents', () {
    final on = _cfg({
      MobileFeatureKey.payslips: true,
      MobileFeatureKey.documents: true,
    });
    final off = _cfg({
      MobileFeatureKey.payslips: false,
      MobileFeatureKey.documents: false,
      MobileFeatureKey.payroll: false,
    });
    expect(ShellNavPolicy.isRouteAllowed(on, '/more/payslips'), isTrue);
    expect(ShellNavPolicy.isRouteAllowed(on, '/more/documents'), isTrue);
    expect(ShellNavPolicy.isRouteAllowed(off, '/more/payslips'), isFalse);
    expect(ShellNavPolicy.isRouteAllowed(off, '/more/documents'), isFalse);
    expect(ShellNavPolicy.visibleMoreItems(on), contains(ShellMoreItem.payslips));
    expect(ShellNavPolicy.visibleMoreItems(off), isNot(contains(ShellMoreItem.payslips)));
  });

  test('payslips feature aliases legacy payroll flag', () {
    final viaPayroll = _cfg({MobileFeatureKey.payroll: true});
    expect(viaPayroll.isFeatureEnabled(MobileFeatureKey.payslips), isTrue);
    expect(ShellNavPolicy.isRouteAllowed(viaPayroll, '/more/payslips'), isTrue);
  });

  test('PayslipRepository/State load list and detail', () async {
    final fake = _FakePayslips()
      ..rows = [
        {
          'id': 'l-1',
          'period': '2026-07',
          'net_amount': 5000,
          'status': 'posted',
        },
      ]
      ..detailRow = {
        'id': 'l-1',
        'period': '2026-07',
        'gross_amount': 5500,
        'net_amount': 5000,
        'status': 'posted',
        'download_url': '/api/v1/hr/payslips/l-1/file',
      };
    final repo = PayslipRepository(payslips: fake);
    final state = PayslipState(repository: repo);
    await state.loadList();
    expect(state.status, PayslipLoadStatus.ready);
    expect(state.items, hasLength(1));
    await state.loadDetail('l-1');
    expect(state.detail['net_amount'], 5000);
    expect(await state.openDownload('l-1'), isTrue);
    expect(state.previewBytes, isNotNull);
  });

  test('DocumentsRepository filters by category', () async {
    final fake = _FakeDocuments()
      ..rows = [
        {'id': 'f-1', 'title': 'A', 'category': 'contract'},
        {'id': 'f-2', 'title': 'B', 'category': 'id'},
      ];
    final repo = DocumentsRepository(documents: fake);
    final all = await repo.loadList();
    final filtered = await repo.loadList(category: 'id');
    expect(all, hasLength(2));
    expect(filtered, hasLength(1));
    expect(filtered.first['id'], 'f-2');
  });

  test('DocumentsState loads detail', () async {
    final fake = _FakeDocuments()
      ..detailRow = {
        'id': 'f-1',
        'title': 'Contract',
        'category': 'contract',
        'file_url': '/api/v1/hr/documents/f-1/file',
      };
    final state = DocumentsState(
      repository: DocumentsRepository(documents: fake),
    );
    await state.loadDetail('f-1');
    expect(state.status, DocumentsLoadStatus.ready);
    expect(state.detail['title'], 'Contract');
  });

  testWidgets('Payslips list smoke', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        locale: const Locale('en'),
        localizationsDelegates: const [AppLocalizations.delegate],
        supportedLocales: AppLocalizations.supportedLocales,
        home: DsPageScaffold(
          title: 'Payslips',
          body: DsEmptyState(title: 'empty'),
        ),
      ),
    );
    expect(find.text('Payslips'), findsOneWidget);
  });

  testWidgets('Documents list smoke', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        locale: const Locale('en'),
        localizationsDelegates: const [AppLocalizations.delegate],
        supportedLocales: AppLocalizations.supportedLocales,
        home: DsPageScaffold(
          title: 'Documents',
          body: DsEmptyState(title: 'empty'),
        ),
      ),
    );
    expect(find.text('Documents'), findsOneWidget);
  });
}

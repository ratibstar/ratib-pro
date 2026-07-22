/// Payslips list — ERP list only.
library;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/ess_failure_ui.dart';
import 'package:ratib_hr_mobile/core/routing/app_routes.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/features/payslips/payslip_state.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class PayslipsListScreen extends StatefulWidget {
  const PayslipsListScreen({super.key});

  @override
  State<PayslipsListScreen> createState() => _PayslipsListScreenState();
}

class _PayslipsListScreenState extends State<PayslipsListScreen> {
  late final PayslipState _state;

  @override
  void initState() {
    super.initState();
    _state = PayslipState(repository: AppLocator.payslipRepository)
      ..addListener(_onChanged);
    _state.loadList();
  }

  void _onChanged() {
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    _state
      ..removeListener(_onChanged)
      ..dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return DsPageScaffold(
      title: l10n.navPayslips,
      body: _state.status == PayslipLoadStatus.loading
          ? DsLoadingState(message: l10n.genericLoading)
          : (_state.status == PayslipLoadStatus.error && !_state.offlineDegraded)
              ? DsErrorState(
                  title: l10n.genericLoadFailed,
                  message: EssFailureUi.fromStored(
                    l10n,
                    code: _state.errorCode,
                    message: _state.errorMessage,
                  ),
                  actionLabel: l10n.homeRetry,
                  onAction: _state.loadList,
                )
              : _state.items.isEmpty
                  ? Column(
                      children: [
                        if (_state.offlineDegraded)
                          Padding(
                            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                            child: DsGlassTile(
                              child: Text(l10n.offlineCachedHint),
                            ),
                          ),
                        Expanded(
                          child: DsEmptyState(title: l10n.payslipsEmpty),
                        ),
                      ],
                    )
                  : RefreshIndicator(
                      onRefresh: _state.loadList,
                      child: ListView.builder(
                        padding: const EdgeInsets.only(top: 8, bottom: 32),
                        itemCount: _state.items.length +
                            (_state.offlineDegraded ? 1 : 0),
                        itemBuilder: (context, i) {
                          if (_state.offlineDegraded && i == 0) {
                            return Padding(
                              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                              child: DsGlassTile(
                                child: Text(l10n.offlineCachedHint),
                              ),
                            );
                          }
                          final row = _state
                              .items[_state.offlineDegraded ? i - 1 : i];
                          final id = (row['id'] ?? '').toString();
                          final period = (row['period'] ?? '').toString();
                          final net = (row['net_amount'] ?? '').toString();
                          final status = (row['status'] ?? '').toString();
                          return DsListItem(
                            title: period.isEmpty ? id : period,
                            subtitle: net.isEmpty
                                ? null
                                : '${l10n.payslipNet}: $net',
                            leading: const DsIconBadge(
                              icon: Icons.payments_outlined,
                              color: AppColors.auroraCyan,
                            ),
                            trailing: status.isEmpty
                                ? const SizedBox.shrink()
                                : DsStatusBadge(label: status),
                            onTap: () => context.go(
                              '${AppRoutes.payslipDetail}?id=${Uri.encodeComponent(id)}',
                            ),
                          );
                        },
                      ),
                    ),
    );
  }
}

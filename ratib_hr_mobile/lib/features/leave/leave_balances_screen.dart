/// Leave hub — balances + navigation to apply / my requests.
library;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/ess_failure_ui.dart';
import 'package:ratib_hr_mobile/core/routing/app_routes.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/features/leave/leave_state.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class LeaveBalancesScreen extends StatefulWidget {
  const LeaveBalancesScreen({super.key});

  @override
  State<LeaveBalancesScreen> createState() => _LeaveBalancesScreenState();
}

class _LeaveBalancesScreenState extends State<LeaveBalancesScreen> {
  late final LeaveState _state;

  @override
  void initState() {
    super.initState();
    _state = LeaveState(repository: AppLocator.leaveRepository)
      ..addListener(_onChanged);
    _state.loadBalances();
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
      title: l10n.leave,
      body: _state.status == LeaveLoadStatus.loading
          ? DsLoadingState(message: l10n.genericLoading)
          : _state.status == LeaveLoadStatus.error
              ? DsErrorState(
                  title: l10n.genericLoadFailed,
                  message: EssFailureUi.fromStored(
                    l10n,
                    code: _state.errorCode,
                    message: _state.errorMessage,
                  ),
                  actionLabel: l10n.homeRetry,
                  onAction: _state.loadBalances,
                )
              : RefreshIndicator(
                  onRefresh: _state.loadBalances,
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.only(bottom: 32),
                    children: [
                      if (_state.offlineDegraded)
                        Padding(
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                          child: DsGlassTile(
                            child: Text(
                              _state.fromCache
                                  ? l10n.offlineLeaveMode
                                  : l10n.offlineNeedsConnection,
                            ),
                          ),
                        ),
                      if (_state.pendingOfflineCount > 0)
                        Padding(
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                          child: DsGlassTile(
                            child: Text(
                              l10n.leaveOfflineBanner(_state.pendingOfflineCount),
                            ),
                          ),
                        ),
                      DsSectionHeader(title: l10n.homeLeaveBalance),
                      if (_state.balances.isEmpty)
                        DsCard(
                          child: Text(
                            _state.offlineDegraded
                                ? l10n.offlineNeedsConnection
                                : l10n.homeNoLeaveBalances,
                          ),
                        )
                      else
                        for (final row in _state.balances)
                          DsKpiCard(
                            label: (row['leave_type_name'] ??
                                    row['leave_type_code'] ??
                                    l10n.tabLeave)
                                .toString(),
                            value: (row['remaining_days'] ??
                                    row['entitled_days'] ??
                                    '—')
                                .toString(),
                            icon: Icons.beach_access_rounded,
                            accent: AppColors.auroraCyan,
                          ),
                      const SizedBox(height: 8),
                      DsListItem(
                        title: l10n.navApplyLeave,
                        leading: const DsIconBadge(
                          icon: Icons.add_circle_outline,
                          color: AppColors.auroraTeal,
                        ),
                        onTap: () => context.go(AppRoutes.leaveApply),
                      ),
                      DsListItem(
                        title: l10n.leaveMyRequests,
                        leading: const DsIconBadge(
                          icon: Icons.list_alt_rounded,
                          color: AppColors.auroraAmber,
                        ),
                        onTap: () => context.go(AppRoutes.leaveStatus),
                      ),
                    ],
                  ),
                ),
    );
  }
}

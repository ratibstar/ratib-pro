/// My leave requests list.
library;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/routing/app_routes.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/features/leave/leave_state.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class LeaveRequestsScreen extends StatefulWidget {
  const LeaveRequestsScreen({super.key});

  @override
  State<LeaveRequestsScreen> createState() => _LeaveRequestsScreenState();
}

class _LeaveRequestsScreenState extends State<LeaveRequestsScreen> {
  late final LeaveState _state;

  @override
  void initState() {
    super.initState();
    _state = LeaveState(repository: AppLocator.leaveRepository)
      ..addListener(_onChanged);
    _state.loadRequests();
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
      title: l10n.leaveMyRequests,
      actions: [
        IconButton(
          onPressed: () => context.go(AppRoutes.leaveApply),
          icon: const Icon(Icons.add_rounded),
        ),
      ],
      body: _state.status == LeaveLoadStatus.loading
          ? DsLoadingState(message: l10n.genericLoading)
          : _state.status == LeaveLoadStatus.error
              ? DsErrorState(
                  title: l10n.genericLoadFailed,
                  message: _state.errorMessage ?? _state.errorCode,
                  actionLabel: l10n.homeRetry,
                  onAction: _state.loadRequests,
                )
              : _state.requests.isEmpty
                  ? DsEmptyState(
                      title: l10n.leaveRequestsEmpty,
                      actionLabel: l10n.navApplyLeave,
                      onAction: () => context.go(AppRoutes.leaveApply),
                    )
                  : RefreshIndicator(
                      onRefresh: _state.loadRequests,
                      child: ListView.builder(
                        padding: const EdgeInsets.only(top: 8, bottom: 32),
                        itemCount: _state.requests.length +
                            (_state.pendingOfflineCount > 0 ? 1 : 0),
                        itemBuilder: (context, i) {
                          if (_state.pendingOfflineCount > 0 && i == 0) {
                            return Padding(
                              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                              child: DsGlassTile(
                                child: Text(
                                  l10n.leaveOfflineBanner(
                                    _state.pendingOfflineCount,
                                  ),
                                ),
                              ),
                            );
                          }
                          final idx =
                              _state.pendingOfflineCount > 0 ? i - 1 : i;
                          final row = _state.requests[idx];
                          final id = (row['id'] ?? '').toString();
                          final title = (row['leave_type_name'] ??
                                  row['leave_type_code'] ??
                                  l10n.tabLeave)
                              .toString();
                          final range =
                              '${row['start_date'] ?? ''} → ${row['end_date'] ?? ''}';
                          final status = (row['status'] ?? '').toString();
                          return DsListItem(
                            title: title,
                            subtitle: [
                              range,
                              if ((row['days'] ?? '').toString().isNotEmpty)
                                '${row['days']} ${l10n.leaveDays}',
                            ].join(' · '),
                            leading: const DsIconBadge(
                              icon: Icons.event_note_outlined,
                              color: AppColors.auroraCyan,
                            ),
                            trailing: status.isEmpty
                                ? const SizedBox.shrink()
                                : DsStatusBadge(label: status),
                            onTap: () => context.go(
                              '${AppRoutes.leaveDetail}?id=$id',
                            ),
                          );
                        },
                      ),
                    ),
    );
  }
}

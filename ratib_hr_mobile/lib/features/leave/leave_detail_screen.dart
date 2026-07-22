/// Leave request detail — ERP detail only.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/ess_failure_ui.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/features/leave/leave_state.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class LeaveDetailScreen extends StatefulWidget {
  const LeaveDetailScreen({super.key, required this.requestId});

  final String requestId;

  @override
  State<LeaveDetailScreen> createState() => _LeaveDetailScreenState();
}

class _LeaveDetailScreenState extends State<LeaveDetailScreen> {
  late final LeaveState _state;

  @override
  void initState() {
    super.initState();
    _state = LeaveState(repository: AppLocator.leaveRepository)
      ..addListener(_onChanged);
    _state.loadDetail(widget.requestId);
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
    final row = _state.detail;
    return DsPageScaffold(
      title: l10n.leaveDetailTitle,
      body: _state.status == LeaveLoadStatus.loading
          ? DsLoadingState(message: l10n.genericLoading)
          : (_state.status == LeaveLoadStatus.error && !_state.offlineDegraded)
              ? DsErrorState(
                  title: l10n.genericLoadFailed,
                  message: EssFailureUi.fromStored(
                    l10n,
                    code: _state.errorCode,
                    message: _state.errorMessage,
                  ),
                  actionLabel: l10n.homeRetry,
                  onAction: () => _state.loadDetail(widget.requestId),
                )
              : ListView(
                  padding: const EdgeInsets.only(bottom: 32),
                  children: [
                    if (_state.offlineDegraded)
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                        child: DsGlassTile(child: Text(l10n.offlineCachedHint)),
                      ),
                    DsSectionHeader(title: l10n.requestStatus),
                    DsCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          if ((row['status'] ?? '').toString().isNotEmpty)
                            DsStatusBadge(label: row['status'].toString()),
                          const SizedBox(height: AppSpacing.sm),
                          Text(
                            '${l10n.leaveType}: ${row['leave_type_name'] ?? row['leave_type_code'] ?? '-'}',
                          ),
                          Text(
                            '${l10n.leaveStartDate}: ${row['start_date'] ?? '-'}',
                          ),
                          Text(
                            '${l10n.leaveEndDate}: ${row['end_date'] ?? '-'}',
                          ),
                          Text('${l10n.leaveDays}: ${row['days'] ?? '-'}'),
                          if ((row['reason'] ?? '').toString().isNotEmpty) ...[
                            const SizedBox(height: AppSpacing.sm),
                            Text(row['reason'].toString()),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),
    );
  }
}

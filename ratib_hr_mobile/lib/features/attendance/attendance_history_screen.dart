/// Attendance history list — ERP history endpoint only.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/ess_failure_ui.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/features/attendance/attendance_state.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class AttendanceHistoryScreen extends StatefulWidget {
  const AttendanceHistoryScreen({super.key});

  @override
  State<AttendanceHistoryScreen> createState() =>
      _AttendanceHistoryScreenState();
}

class _AttendanceHistoryScreenState extends State<AttendanceHistoryScreen> {
  late final AttendanceState _state;

  @override
  void initState() {
    super.initState();
    _state = AttendanceState(
      repository: AppLocator.attendanceRepository,
    )..addListener(_onChanged);
    _state.loadHistory();
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
      title: l10n.navAttendanceHistory,
      body: _state.status == AttendanceLoadStatus.loading
          ? DsLoadingState(message: l10n.genericLoading)
          : (_state.status == AttendanceLoadStatus.error &&
                  !_state.offlineDegraded)
              ? DsErrorState(
                  title: l10n.genericLoadFailed,
                  message: EssFailureUi.fromStored(
                    l10n,
                    code: _state.errorCode,
                    message: _state.errorMessage,
                  ),
                  actionLabel: l10n.homeRetry,
                  onAction: _state.loadHistory,
                )
              : _state.history.isEmpty
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
                          child: DsEmptyState(
                            title: l10n.attendanceHistoryEmpty,
                          ),
                        ),
                      ],
                    )
                  : RefreshIndicator(
                      onRefresh: _state.loadHistory,
                      child: ListView.builder(
                        padding: const EdgeInsets.only(top: 8, bottom: 32),
                        itemCount: _state.history.length +
                            (_state.pendingOfflineCount > 0 ||
                                    _state.offlineDegraded
                                ? 1
                                : 0),
                        itemBuilder: (context, i) {
                          final showBanner = _state.pendingOfflineCount > 0 ||
                              _state.offlineDegraded;
                          if (showBanner && i == 0) {
                            return Padding(
                              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                              child: DsGlassTile(
                                child: Text(
                                  _state.pendingOfflineCount > 0
                                      ? l10n.attendanceOfflineBanner(
                                          _state.pendingOfflineCount,
                                        )
                                      : l10n.offlineCachedHint,
                                ),
                              ),
                            );
                          }
                          final idx = showBanner ? i - 1 : i;
                          final row = _state.history[idx];
                          final date =
                              (row['attendance_date'] ?? '').toString();
                          final inn = (row['check_in'] ?? '—').toString();
                          final out = (row['check_out'] ?? '—').toString();
                          final status = (row['status'] ?? '').toString();
                          return DsListItem(
                            title: date.isEmpty ? l10n.navAttendance : date,
                            subtitle: '${l10n.navCheckIn}: $inn · ${l10n.navCheckOut}: $out',
                            leading: const DsIconBadge(
                              icon: Icons.event_available_outlined,
                              color: AppColors.auroraTeal,
                            ),
                            trailing: status.isEmpty
                                ? const SizedBox.shrink()
                                : DsStatusBadge(label: status),
                          );
                        },
                      ),
                    ),
    );
  }
}

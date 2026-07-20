/// Attendance hub — today card, check-in/out, history entry.
library;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/routing/app_routes.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/features/attendance/attendance_repository.dart';
import 'package:ratib_hr_mobile/features/attendance/attendance_state.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class AttendanceScreen extends StatefulWidget {
  const AttendanceScreen({super.key});

  @override
  State<AttendanceScreen> createState() => _AttendanceScreenState();
}

class _AttendanceScreenState extends State<AttendanceScreen> {
  late final AttendanceState _state;

  @override
  void initState() {
    super.initState();
    _state = AttendanceState(
      repository: AppLocator.attendanceRepository,
    )..addListener(_onChanged);
    _state.loadToday();
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

  Future<void> _checkIn() async {
    final l10n = AppLocalizations.of(context);
    try {
      final result = await _state.checkIn();
      if (!mounted) return;
      if (result == AttendancePunchResult.queuedOffline) {
        DsSnackbar.show(
          context,
          message: l10n.attendanceQueuedOffline,
          kind: DsSnackbarKind.success,
        );
      } else {
        DsSnackbar.show(
          context,
          message: l10n.attendanceCheckInSuccess,
          kind: DsSnackbarKind.success,
        );
      }
    } catch (e) {
      if (!mounted) return;
      final f = e is AppFailure ? e : AppFailure(code: 'unknown');
      DsSnackbar.show(
        context,
        message: _errorMessage(l10n, f),
        kind: DsSnackbarKind.error,
      );
    }
  }

  Future<void> _checkOut() async {
    final l10n = AppLocalizations.of(context);
    try {
      await _state.checkOut();
      if (!mounted) return;
      DsSnackbar.show(
        context,
        message: l10n.attendanceCheckOutSuccess,
        kind: DsSnackbarKind.success,
      );
    } catch (e) {
      if (!mounted) return;
      final f = e is AppFailure ? e : AppFailure(code: 'unknown');
      DsSnackbar.show(
        context,
        message: _errorMessage(l10n, f),
        kind: DsSnackbarKind.error,
      );
    }
  }

  String _errorMessage(AppLocalizations l10n, AppFailure f) {
    switch (f.code) {
      case 'already_checked_in':
        return l10n.attendanceAlreadyCheckedIn;
      case 'invalid_state':
        return l10n.attendanceInvalidState;
      case 'network':
      case 'timeout':
        return l10n.loginNetworkError;
      default:
        return f.message?.isNotEmpty == true
            ? f.message!
            : l10n.genericLoadFailed;
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return DsPageScaffold(
      title: l10n.navAttendance,
      actions: [
        IconButton(
          tooltip: l10n.navAttendanceHistory,
          onPressed: () => context.go(AppRoutes.attendanceHistory),
          icon: const Icon(Icons.history_rounded),
        ),
      ],
      body: _state.status == AttendanceLoadStatus.loading && !_state.punching
          ? DsLoadingState(message: l10n.genericLoading)
          : _state.status == AttendanceLoadStatus.error &&
                  _state.today.isEmpty &&
                  !_state.punching
              ? DsErrorState(
                  title: l10n.genericLoadFailed,
                  message: _state.errorMessage ?? _state.errorCode,
                  actionLabel: l10n.homeRetry,
                  onAction: _state.loadToday,
                )
              : RefreshIndicator(
                  onRefresh: _state.loadToday,
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.only(bottom: 32),
                    children: [
                      if (_state.pendingOfflineCount > 0)
                        Padding(
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                          child: DsGlassTile(
                            padding: const EdgeInsets.all(14),
                            child: Row(
                              children: [
                                const DsIconBadge(
                                  icon: Icons.cloud_off_outlined,
                                  color: AppColors.auroraAmber,
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Text(
                                    l10n.attendanceOfflineBanner(
                                      _state.pendingOfflineCount,
                                    ),
                                    style:
                                        Theme.of(context).textTheme.bodyMedium,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      DsSectionHeader(title: l10n.homeTodayAttendance),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        child: DsGlassTile(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Row(
                                children: [
                                  const DsIconBadge(
                                    icon: Icons.fingerprint_rounded,
                                    color: AppColors.auroraTeal,
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: Text(
                                      l10n.attendanceStatusLabel(
                                        _state.statusLabel,
                                      ),
                                      style: Theme.of(context)
                                          .textTheme
                                          .titleMedium
                                          ?.copyWith(
                                            fontWeight: FontWeight.w800,
                                          ),
                                    ),
                                  ),
                                  if ((_state.today['status'] ?? '')
                                      .toString()
                                      .isNotEmpty)
                                    DsStatusBadge(
                                      label:
                                          _state.today['status'].toString(),
                                    ),
                                ],
                              ),
                              const SizedBox(height: 16),
                              _TimeRow(
                                label: l10n.navCheckIn,
                                value: _state.hasCheckIn
                                    ? _state.today['check_in'].toString()
                                    : '—',
                              ),
                              const SizedBox(height: 8),
                              _TimeRow(
                                label: l10n.navCheckOut,
                                value: _state.hasCheckOut
                                    ? _state.today['check_out'].toString()
                                    : '—',
                              ),
                              if (_state.workingDurationLabel() != null) ...[
                                const SizedBox(height: 8),
                                _TimeRow(
                                  label: l10n.attendanceWorkingDuration,
                                  value: _state.workingDurationLabel()!,
                                ),
                              ],
                              if (!_state.hasCheckIn) ...[
                                const SizedBox(height: 12),
                                Text(
                                  l10n.homeNoAttendanceToday,
                                  style: Theme.of(context)
                                      .textTheme
                                      .bodyMedium
                                      ?.copyWith(
                                        color: Theme.of(context)
                                            .colorScheme
                                            .onSurfaceVariant,
                                      ),
                                ),
                              ],
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 20),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        child: Column(
                          children: [
                            DsPrimaryButton(
                              label: l10n.navCheckIn,
                              icon: Icons.login_rounded,
                              onPressed: (_state.punching || _state.hasCheckIn)
                                  ? null
                                  : _checkIn,
                            ),
                            const SizedBox(height: 12),
                            DsSecondaryButton(
                              label: l10n.navCheckOut,
                              icon: Icons.logout_rounded,
                              onPressed: (_state.punching ||
                                      !_state.hasCheckIn ||
                                      _state.hasCheckOut)
                                  ? null
                                  : _checkOut,
                            ),
                          ],
                        ),
                      ),
                      DsListItem(
                        title: l10n.navAttendanceHistory,
                        subtitle: l10n.attendanceHistoryHint,
                        leading: const DsIconBadge(
                          icon: Icons.history_rounded,
                          color: AppColors.auroraCyan,
                        ),
                        onTap: () => context.go(AppRoutes.attendanceHistory),
                      ),
                    ],
                  ),
                ),
    );
  }
}

class _TimeRow extends StatelessWidget {
  const _TimeRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            label,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
          ),
        ),
        Text(
          value,
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w700,
              ),
        ),
      ],
    );
  }
}

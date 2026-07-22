/// Apply leave — dates + type; ERP computes days.
library;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/routing/app_routes.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/features/leave/leave_repository.dart';
import 'package:ratib_hr_mobile/features/leave/leave_state.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class LeaveApplyScreen extends StatefulWidget {
  const LeaveApplyScreen({super.key});

  @override
  State<LeaveApplyScreen> createState() => _LeaveApplyScreenState();
}

class _LeaveApplyScreenState extends State<LeaveApplyScreen> {
  late final LeaveState _state;
  final _reason = TextEditingController();
  DateTime? _start;
  DateTime? _end;
  int? _leaveTypeId;

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
    _reason.dispose();
    _state
      ..removeListener(_onChanged)
      ..dispose();
    super.dispose();
  }

  String _iso(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  Future<void> _pickStart() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _start ?? now,
      firstDate: DateTime(now.year - 1),
      lastDate: DateTime(now.year + 2),
    );
    if (picked != null) setState(() => _start = picked);
  }

  Future<void> _pickEnd() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _end ?? _start ?? now,
      firstDate: _start ?? DateTime(now.year - 1),
      lastDate: DateTime(now.year + 2),
    );
    if (picked != null) setState(() => _end = picked);
  }

  Future<void> _submit() async {
    final l10n = AppLocalizations.of(context);
    if (_leaveTypeId == null || _start == null || _end == null) {
      DsSnackbar.show(
        context,
        message: l10n.leaveFormRequired,
        kind: DsSnackbarKind.error,
      );
      return;
    }
    try {
      final result = await _state.apply(
        leaveTypeId: _leaveTypeId!,
        startDate: _iso(_start!),
        endDate: _iso(_end!),
        reason: _reason.text,
      );
      if (!mounted) return;
      DsSnackbar.show(
        context,
        message: result == LeaveApplyResult.queuedOffline
            ? l10n.leaveQueuedOffline
            : l10n.leaveApplySuccess,
        kind: DsSnackbarKind.success,
      );
      context.go(AppRoutes.leaveStatus);
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
      case 'duplicate_request':
        return l10n.leaveDuplicate;
      case 'validation_error':
        return f.message?.isNotEmpty == true
            ? f.message!
            : l10n.leaveFormRequired;
      case 'network':
      case 'timeout':
        return l10n.offlineNeedsConnection;
      default:
        return f.message?.isNotEmpty == true
            ? f.message!
            : l10n.genericLoadFailed;
    }
  }

  int? _asInt(Object? value) {
    if (value is int) return value;
    return int.tryParse('$value');
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final types = _state.balances;
    return DsPageScaffold(
      title: l10n.navApplyLeave,
      body: _state.status == LeaveLoadStatus.loading && types.isEmpty
          ? DsLoadingState(message: l10n.genericLoading)
          : ListView(
              padding: const EdgeInsets.only(bottom: 32),
              children: [
                DsSectionHeader(title: l10n.leaveType),
                DsCard(
                  child: DropdownButtonFormField<int>(
                    value: _leaveTypeId,
                    decoration: InputDecoration(labelText: l10n.leaveType),
                    items: [
                      for (final t in types)
                        if (_asInt(t['leave_type_id']) != null)
                          DropdownMenuItem<int>(
                            value: _asInt(t['leave_type_id']),
                            child: Text(
                              (t['leave_type_name'] ??
                                      t['leave_type_code'] ??
                                      '')
                                  .toString(),
                            ),
                          ),
                    ],
                    onChanged: (v) => setState(() => _leaveTypeId = v),
                  ),
                ),
                DsSectionHeader(title: l10n.leaveDates),
                DsListItem(
                  title: l10n.leaveStartDate,
                  subtitle: _start == null ? '—' : _iso(_start!),
                  leading: const DsIconBadge(
                    icon: Icons.event,
                    color: AppColors.auroraTeal,
                  ),
                  onTap: _pickStart,
                ),
                DsListItem(
                  title: l10n.leaveEndDate,
                  subtitle: _end == null ? '—' : _iso(_end!),
                  leading: const DsIconBadge(
                    icon: Icons.event_available,
                    color: AppColors.auroraCyan,
                  ),
                  onTap: _pickEnd,
                ),
                DsSectionHeader(title: l10n.leaveReason),
                DsCard(
                  child: TextField(
                    controller: _reason,
                    maxLines: 3,
                    decoration: InputDecoration(
                      hintText: l10n.leaveReasonHint,
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.all(AppSpacing.md),
                  child: DsPrimaryButton(
                    label: l10n.leaveSubmit,
                    icon: Icons.send_rounded,
                    onPressed: _state.submitting ? null : _submit,
                  ),
                ),
              ],
            ),
    );
  }
}

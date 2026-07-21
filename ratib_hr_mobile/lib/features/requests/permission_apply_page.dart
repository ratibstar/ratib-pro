/// Submit a short-exit permission request via ERP.
library;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/routing/app_routes.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class PermissionApplyPage extends StatefulWidget {
  const PermissionApplyPage({super.key});

  @override
  State<PermissionApplyPage> createState() => _PermissionApplyPageState();
}

class _PermissionApplyPageState extends State<PermissionApplyPage> {
  final _reason = TextEditingController();
  DateTime? _date;
  TimeOfDay? _from;
  TimeOfDay? _to;
  bool _busy = false;

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  String _isoDate(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  String _hhmm(TimeOfDay t) =>
      '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _date ?? now,
      firstDate: DateTime(now.year - 1),
      lastDate: DateTime(now.year + 1),
    );
    if (picked != null) setState(() => _date = picked);
  }

  Future<void> _pickFrom() async {
    final picked = await showTimePicker(
      context: context,
      initialTime: _from ?? const TimeOfDay(hour: 9, minute: 0),
    );
    if (picked != null) setState(() => _from = picked);
  }

  Future<void> _pickTo() async {
    final picked = await showTimePicker(
      context: context,
      initialTime: _to ?? _from ?? const TimeOfDay(hour: 11, minute: 0),
    );
    if (picked != null) setState(() => _to = picked);
  }

  Future<void> _submit() async {
    final l10n = AppLocalizations.of(context);
    if (_date == null || _from == null || _to == null) {
      DsSnackbar.show(
        context,
        message: l10n.permissionFormRequired,
        kind: DsSnackbarKind.error,
      );
      return;
    }
    setState(() => _busy = true);
    try {
      await AppLocator.permissionRequests.submit({
        'permission_date': _isoDate(_date!),
        'time_from': _hhmm(_from!),
        'time_to': _hhmm(_to!),
        if (_reason.text.trim().isNotEmpty) 'reason': _reason.text.trim(),
      });
      if (!mounted) return;
      DsSnackbar.show(
        context,
        message: l10n.permissionApplySuccess,
        kind: DsSnackbarKind.success,
      );
      context.go(AppRoutes.permissionRequests);
    } catch (e) {
      if (!mounted) return;
      final f = e is AppFailure ? e : AppLocator.errors.map(e);
      DsSnackbar.show(
        context,
        message: _errorMessage(l10n, f),
        kind: DsSnackbarKind.error,
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  String _errorMessage(AppLocalizations l10n, AppFailure f) {
    switch (f.code) {
      case 'duplicate_request':
        return l10n.permissionDuplicate;
      case 'validation_error':
        return f.message?.isNotEmpty == true
            ? f.message!
            : l10n.permissionFormRequired;
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
      title: l10n.permissionApply,
      body: ListView(
        padding: const EdgeInsets.only(bottom: 32),
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
            child: Text(
              l10n.permissionApplyHint,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ),
          DsSectionHeader(title: l10n.permissionDate),
          DsListItem(
            title: l10n.permissionDate,
            subtitle: _date == null ? '—' : _isoDate(_date!),
            leading: const DsIconBadge(
              icon: Icons.event,
              color: AppColors.auroraTeal,
            ),
            onTap: _busy ? null : _pickDate,
          ),
          DsSectionHeader(title: l10n.permissionTime),
          DsListItem(
            title: l10n.permissionTimeFrom,
            subtitle: _from == null ? '—' : _hhmm(_from!),
            leading: const DsIconBadge(
              icon: Icons.schedule,
              color: AppColors.auroraTeal,
            ),
            onTap: _busy ? null : _pickFrom,
          ),
          DsListItem(
            title: l10n.permissionTimeTo,
            subtitle: _to == null ? '—' : _hhmm(_to!),
            leading: const DsIconBadge(
              icon: Icons.schedule_outlined,
              color: AppColors.auroraTeal,
            ),
            onTap: _busy ? null : _pickTo,
          ),
          DsSectionHeader(title: l10n.permissionReason),
          DsCard(
            child: TextField(
              controller: _reason,
              enabled: !_busy,
              maxLines: 3,
              decoration: InputDecoration(
                labelText: l10n.permissionReasonOptional,
              ),
            ),
          ),
          const SizedBox(height: 16),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: FilledButton(
              onPressed: _busy ? null : _submit,
              child: Text(
                _busy ? l10n.genericLoading : l10n.permissionSubmit,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Permission requests — apply form + my requests list on one screen.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class PermissionRequestsPage extends StatefulWidget {
  const PermissionRequestsPage({super.key});

  @override
  State<PermissionRequestsPage> createState() => _PermissionRequestsPageState();
}

class _PermissionRequestsPageState extends State<PermissionRequestsPage> {
  final _reason = TextEditingController();
  DateTime? _date;
  TimeOfDay? _from;
  TimeOfDay? _to;
  bool _busy = false;
  bool _loading = true;
  String? _error;
  List<Map<String, Object?>> _items = const [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  String _isoDate(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  String _hhmm(TimeOfDay t) =>
      '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final rows = await AppLocator.permissionRequests.listMine();
      if (!mounted) return;
      setState(() {
        _items = rows;
        _loading = false;
      });
    } catch (e) {
      final f = e is AppFailure ? e : AppLocator.errors.map(e);
      if (!mounted) return;
      setState(() {
        _error = f.message ?? f.code;
        _loading = false;
      });
    }
  }

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
      _reason.clear();
      setState(() {
        _date = null;
        _from = null;
        _to = null;
      });
      DsSnackbar.show(
        context,
        message: l10n.permissionApplySuccess,
        kind: DsSnackbarKind.success,
      );
      await _load();
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
      title: l10n.navPermissionRequests,
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.only(bottom: 40),
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
              child: Material(
                color: const Color(0xFFF59E0B),
                borderRadius: BorderRadius.circular(16),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        'راتب جديد · v${AppConfig.versionLabel}',
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          color: Color(0xFF111827),
                          fontWeight: FontWeight.w900,
                          fontSize: 18,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        l10n.permissionApplyHint,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          color: Color(0xFF111827),
                          fontWeight: FontWeight.w600,
                          fontSize: 14,
                        ),
                      ),
                    ],
                  ),
                ),
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
            const SizedBox(height: 12),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: FilledButton(
                style: FilledButton.styleFrom(
                  backgroundColor: const Color(0xFFF59E0B),
                  foregroundColor: const Color(0xFF111827),
                  minimumSize: const Size.fromHeight(52),
                ),
                onPressed: _busy ? null : _submit,
                child: Text(
                  _busy ? l10n.genericLoading : l10n.permissionSubmit,
                  style: const TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ),
            const SizedBox(height: 24),
            DsSectionHeader(title: l10n.permissionMyRequests),
            if (_loading)
              Padding(
                padding: const EdgeInsets.all(24),
                child: DsLoadingState(message: l10n.genericLoading),
              )
            else if (_error != null)
              DsErrorState(
                title: l10n.genericLoadFailed,
                message: _error,
                actionLabel: l10n.homeRetry,
                onAction: _load,
              )
            else if (_items.isEmpty)
              Padding(
                padding: const EdgeInsets.all(16),
                child: Text(l10n.permissionRequestsEmpty),
              )
            else
              for (final row in _items)
                DsListItem(
                  title: (row['permission_date'] ?? l10n.navPermissionRequests)
                      .toString(),
                  subtitle: [
                    if ((row['time_from'] ?? '').toString().isNotEmpty ||
                        (row['time_to'] ?? '').toString().isNotEmpty)
                      '${row['time_from'] ?? ''} – ${row['time_to'] ?? ''}',
                    if ((row['status'] ?? '').toString().isNotEmpty)
                      row['status'].toString(),
                    if ((row['reason'] ?? '').toString().isNotEmpty)
                      row['reason'].toString(),
                  ].where((e) => e.trim().isNotEmpty).join(' · '),
                  leading: const DsIconBadge(
                    icon: Icons.history,
                    color: AppColors.auroraCyan,
                  ),
                ),
          ],
        ),
      ),
    );
  }
}

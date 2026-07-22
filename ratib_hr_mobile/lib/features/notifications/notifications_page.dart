/// Notification center — categories, read/unread, mark all.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/errors/ess_failure_ui.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

abstract final class _NotifCategory {
  static const all = '';
  static const general = 'general';
  static const attendance = 'attendance';
  static const leave = 'leave';
  static const payroll = 'payroll';
  static const system = 'system';
  static const customer = 'customer';

  static const values = [
    all,
    general,
    attendance,
    leave,
    payroll,
    system,
    customer,
  ];
}

class NotificationsPage extends StatefulWidget {
  const NotificationsPage({super.key});

  @override
  State<NotificationsPage> createState() => _NotificationsPageState();
}

class _NotificationsPageState extends State<NotificationsPage> {
  bool _loading = true;
  String? _error;
  String _filter = _NotifCategory.all;
  List<Map<String, Object?>> _items = const [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final rows = await AppLocator.notifications.listFiltered(_filter);
      if (!mounted) return;
      setState(() {
        _items = rows;
        _loading = false;
      });
    } catch (e) {
      final f = e is AppFailure ? e : AppLocator.errors.map(e);
      if (!mounted) return;
      setState(() {
        _error = EssFailureUi.message(AppLocalizations.of(context), f);
        EssFailureUi.signalIfOffline(f);
        _loading = false;
      });
    }
  }

  Future<void> _markAll() async {
    try {
      await AppLocator.notifications.markAllRead();
      await _load();
    } catch (e) {
      if (!mounted) return;
      final f = e is AppFailure ? e : AppLocator.errors.map(e);
      DsSnackbar.show(
        context,
        message: EssFailureUi.message(AppLocalizations.of(context), f),
        kind: DsSnackbarKind.error,
      );
    }
  }

  Future<void> _markOne(Map<String, Object?> row) async {
    final id = (row['id'] ?? '').toString();
    if (id.isEmpty) return;
    try {
      await AppLocator.notifications.markRead(id);
      await _load();
    } catch (e) {
      if (!mounted) return;
      final f = e is AppFailure ? e : AppLocator.errors.map(e);
      DsSnackbar.show(
        context,
        message: EssFailureUi.message(AppLocalizations.of(context), f),
        kind: DsSnackbarKind.error,
      );
    }
  }

  String _label(AppLocalizations l10n, String key) {
    switch (key) {
      case _NotifCategory.general:
        return l10n.notifCatGeneral;
      case _NotifCategory.attendance:
        return l10n.notifCatAttendance;
      case _NotifCategory.leave:
        return l10n.notifCatLeave;
      case _NotifCategory.payroll:
        return l10n.notifCatPayroll;
      case _NotifCategory.system:
        return l10n.notifCatSystem;
      case _NotifCategory.customer:
        return l10n.notifCatCustomer;
      default:
        return l10n.notifCatAll;
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return DsPageScaffold(
      title: l10n.navNotifications,
      actions: [
        TextButton(
          onPressed: _items.isEmpty ? null : _markAll,
          child: Text(l10n.notifMarkAllRead),
        ),
      ],
      body: Column(
        children: [
          SizedBox(
            height: 52,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.sm),
              children: [
                for (final c in _NotifCategory.values)
                  Padding(
                    padding: const EdgeInsets.symmetric(
                      horizontal: AppSpacing.xs,
                    ),
                    child: FilterChip(
                      label: Text(_label(l10n, c)),
                      selected: _filter == c,
                      onSelected: (_) {
                        setState(() => _filter = c);
                        _load();
                      },
                    ),
                  ),
              ],
            ),
          ),
          Expanded(
            child: _loading
                ? DsLoadingState(message: l10n.genericLoading)
                : _error != null
                    ? DsErrorState(
                        title: l10n.genericLoadFailed,
                        message: _error,
                        actionLabel: l10n.homeRetry,
                        onAction: _load,
                      )
                    : _items.isEmpty
                        ? DsEmptyState(title: l10n.homeNoNotifications)
                        : RefreshIndicator(
                            onRefresh: _load,
                            child: ListView.builder(
                              padding: const EdgeInsets.only(bottom: 24),
                              itemCount: _items.length,
                              itemBuilder: (context, i) {
                                final row = _items[i];
                                final unread = row['is_read'] != true &&
                                    row['is_read'] != 1 &&
                                    row['is_read'] != '1';
                                return DsNotificationTile(
                                  title: (row['title'] ??
                                          row['subject'] ??
                                          l10n.navNotifications)
                                      .toString(),
                                  body: (row['body'] ?? row['message'] ?? '')
                                      .toString(),
                                  timeLabel:
                                      (row['created_at'] ?? '').toString(),
                                  unread: unread,
                                  onTap: () => _markOne(row),
                                );
                              },
                            ),
                          ),
          ),
        ],
      ),
    );
  }
}

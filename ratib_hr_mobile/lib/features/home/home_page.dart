/// Enterprise ESS dashboard — presentation over DashboardPort.
library;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/mobile_config/mobile_app_configuration.dart';
import 'package:ratib_hr_mobile/core/routing/app_routes.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class HomePage extends StatefulWidget {
  const HomePage({super.key});

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  bool _loading = true;
  String? _error;
  Map<String, Object?> _data = {};

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
      final body = await AppLocator.dashboard.summary();
      if (!mounted) return;
      setState(() {
        _data = body;
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

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final cfg = AppLocator.mobileConfiguration.current;
    final employee = _data['employee'];
    final name = employee is Map
        ? (employee['name'] ?? cfg?.displayName ?? '').toString()
        : (cfg?.displayName ?? '');
    final payroll = _data['payroll_summary'];
    final payrollAvailable =
        payroll is Map && payroll['available'] == true;
    final notif = _data['notifications_summary'];
    final unread = notif is Map ? (notif['unread'] ?? 0) : 0;
    final unreadCount = unread is int
        ? unread
        : int.tryParse(unread.toString()) ?? 0;

    return Scaffold(
      appBar: DsAppBar(
        title: cfg?.displayName.isNotEmpty == true
            ? '${cfg!.displayName} · 0.1.2'
            : '${l10n.navHome} · 0.1.2',
        actions: [
          if (cfg?.isFeatureEnabled(MobileFeatureKey.notifications) == true)
            IconButton(
              icon: Badge(
                isLabelVisible: unreadCount > 0,
                label: Text('$unreadCount'),
                child: const Icon(Icons.notifications_outlined),
              ),
              onPressed: () => context.go(AppRoutes.notifications),
            ),
        ],
      ),
      body: _loading
          ? DsLoadingState(message: l10n.homeLoading)
          : _error != null
              ? DsErrorState(
                  title: l10n.homeLoadFailed,
                  message: _error,
                  actionLabel: l10n.homeRetry,
                  onAction: _load,
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.only(bottom: AppSpacing.xxl),
                    children: [
                      Padding(
                        padding: const EdgeInsets.fromLTRB(
                          AppSpacing.md,
                          AppSpacing.lg,
                          AppSpacing.md,
                          AppSpacing.sm,
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Text(
                              name,
                              style: Theme.of(context).textTheme.headlineSmall,
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'الإصدار 0.1.2',
                              style: Theme.of(context)
                                  .textTheme
                                  .labelMedium
                                  ?.copyWith(
                                    color: Theme.of(context)
                                        .colorScheme
                                        .onSurfaceVariant,
                                  ),
                            ),
                          ],
                        ),
                      ),
                      DsSectionHeader(title: l10n.homeQuickActions),
                      _QuickActions(l10n: l10n, cfg: cfg),
                      DsSectionHeader(title: l10n.homeTodayAttendance),
                      _AttendanceBlock(
                        raw: _data['attendance_today'],
                        l10n: l10n,
                      ),
                      DsSectionHeader(title: l10n.homeLeaveBalance),
                      _LeaveBlock(raw: _data['leave_balances'], l10n: l10n),
                      DsSectionHeader(title: l10n.homePendingRequests),
                      _PendingBlock(
                        requests: _data['pending_requests'],
                        leaves: _data['pending_leaves'],
                        l10n: l10n,
                      ),
                      if (unreadCount > 0) ...[
                        DsSectionHeader(title: l10n.homeRecentNotifications),
                        _NotifSummary(
                          raw: _data['notifications_summary'],
                          l10n: l10n,
                        ),
                      ],
                      if (payrollAvailable) ...[
                        DsSectionHeader(title: l10n.homePayrollSummary),
                        _PayrollBlock(
                          raw: _data['payroll_summary'],
                          l10n: l10n,
                        ),
                      ],
                    ],
                  ),
                ),
    );
  }
}

class _AttendanceBlock extends StatelessWidget {
  const _AttendanceBlock({required this.raw, required this.l10n});
  final Object? raw;
  final AppLocalizations l10n;

  @override
  Widget build(BuildContext context) {
    final data = raw;
    if (data is! Map || data.isEmpty) {
      return DsCard(child: Text(l10n.homeNoAttendanceToday));
    }
    final m = data.map((k, v) => MapEntry(k.toString(), v));
    return DsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if ((m['status'] ?? '').toString().isNotEmpty)
            DsStatusBadge(label: m['status'].toString()),
          if ((m['check_in'] ?? m['check_in_at'] ?? '')
              .toString()
              .isNotEmpty) ...[
            const SizedBox(height: AppSpacing.sm),
            Text(
              '${l10n.navCheckIn}: ${m['check_in'] ?? m['check_in_at']}',
            ),
          ],
          if ((m['check_out'] ?? m['check_out_at'] ?? '')
              .toString()
              .isNotEmpty)
            Text(
              '${l10n.navCheckOut}: ${m['check_out'] ?? m['check_out_at']}',
            ),
        ],
      ),
    );
  }
}

class _LeaveBlock extends StatelessWidget {
  const _LeaveBlock({required this.raw, required this.l10n});
  final Object? raw;
  final AppLocalizations l10n;

  @override
  Widget build(BuildContext context) {
    final data = raw;
    if (data is! List || data.isEmpty) {
      return DsCard(child: Text(l10n.homeNoLeaveBalances));
    }
    final rows = _primaryLeaveRows(data).take(3).toList();
    final theme = Theme.of(context).textTheme;
    return DsCard(
      child: Column(
        children: [
          for (var i = 0; i < rows.length; i++) ...[
            if (i > 0) const Divider(height: AppSpacing.lg),
            Row(
              children: [
                Expanded(
                  child: Text(
                    (rows[i]['leave_type_name'] ??
                            rows[i]['name'] ??
                            l10n.tabLeave)
                        .toString(),
                    style: theme.bodyLarge,
                  ),
                ),
                Text(
                  (rows[i]['remaining_days'] ??
                          rows[i]['balance'] ??
                          rows[i]['entitled_days'] ??
                          '-')
                      .toString(),
                  style: theme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  List<Map> _primaryLeaveRows(List data) {
    const preferred = [
      'annual',
      'sick',
      'emergency',
      'unpaid',
      'hajj',
      'marriage',
      'bereavement',
    ];
    const skip = {'maternity', 'paternity', 'iddah'};
    final maps = data.whereType<Map>().toList();
    final ranked = <Map>[];
    for (final code in preferred) {
      for (final row in maps) {
        final c = (row['leave_type_code'] ?? row['code'] ?? '')
            .toString()
            .toLowerCase()
            .trim();
        if (c == code) {
          ranked.add(row);
          break;
        }
      }
    }
    if (ranked.isNotEmpty) return ranked;
    return maps.where((row) {
      final c = (row['leave_type_code'] ?? row['code'] ?? '')
          .toString()
          .toLowerCase()
          .trim();
      return !skip.contains(c);
    }).toList();
  }
}

class _PendingBlock extends StatelessWidget {
  const _PendingBlock({
    required this.requests,
    required this.leaves,
    required this.l10n,
  });
  final Object? requests;
  final Object? leaves;
  final AppLocalizations l10n;

  @override
  Widget build(BuildContext context) {
    final reqList = requests;
    final leaveList = leaves;
    final reqCount = reqList is List ? reqList.length : 0;
    final leaveCount = leaveList is List ? leaveList.length : 0;
    final total = reqCount + leaveCount;
    final text = total == 0
        ? l10n.homeNoPendingRequests
        : '${l10n.homePendingRequests}: $total';
    return DsCard(
      onTap: () => context.go(AppRoutes.requests),
      child: Row(
        children: [
          Expanded(child: Text(text)),
          if (total > 0)
            DsStatusBadge(label: '$total'),
        ],
      ),
    );
  }
}

class _NotifSummary extends StatelessWidget {
  const _NotifSummary({required this.raw, required this.l10n});
  final Object? raw;
  final AppLocalizations l10n;

  @override
  Widget build(BuildContext context) {
    final data = raw;
    if (data is! Map) {
      return DsCard(child: Text(l10n.homeNoNotifications));
    }
    final unread = data['unread'] ?? 0;
    return DsCard(
      onTap: () => context.go(AppRoutes.notifications),
      child: Text('${l10n.homeUnreadNotifications}: $unread'),
    );
  }
}

class _PayrollBlock extends StatelessWidget {
  const _PayrollBlock({required this.raw, required this.l10n});
  final Object? raw;
  final AppLocalizations l10n;

  @override
  Widget build(BuildContext context) {
    final data = raw;
    if (data is! Map) {
      return const SizedBox.shrink();
    }
    final message =
        (data['message'] ?? l10n.homePayrollPlaceholder).toString();
    return DsCard(child: Text(message));
  }
}

class _QuickActions extends StatelessWidget {
  const _QuickActions({required this.l10n, required this.cfg});
  final AppLocalizations l10n;
  final MobileAppConfiguration? cfg;

  @override
  Widget build(BuildContext context) {
    final actions = <Widget>[];
    if (cfg?.isFeatureEnabled(MobileFeatureKey.attendance) == true) {
      actions.add(
        FilledButton.tonalIcon(
          onPressed: () => context.go(AppRoutes.attendanceCheckIn),
          icon: const Icon(AppIcons.checkIn),
          label: Text(l10n.navCheckIn),
        ),
      );
    }
    if (cfg?.isFeatureEnabled(MobileFeatureKey.leave) == true) {
      actions.add(
        FilledButton.tonalIcon(
          onPressed: () => context.go(AppRoutes.leaveApply),
          icon: const Icon(AppIcons.leave),
          label: Text(l10n.navApplyLeave),
        ),
      );
    }
    if (cfg?.isFeatureEnabled(MobileFeatureKey.inquiries) == true) {
      actions.add(
        FilledButton.tonalIcon(
          onPressed: () => context.go(AppRoutes.inquiries),
          icon: const Icon(Icons.support_agent_outlined),
          label: Text(l10n.navInquiries),
        ),
      );
    }
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
      child: Wrap(
        spacing: AppSpacing.sm,
        runSpacing: AppSpacing.sm,
        children: actions,
      ),
    );
  }
}

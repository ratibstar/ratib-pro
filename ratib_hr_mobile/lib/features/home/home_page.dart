/// Enterprise ESS dashboard — modern presentation over DashboardPort.
library;

import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/errors/ess_failure_ui.dart';
import 'package:ratib_hr_mobile/core/identity/employee_context.dart';
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
  bool _offlineDegraded = false;

  static const _dashboardCacheKey = 'ess.dashboard.summary.v1';

  bool get _hasContent => _data.isNotEmpty;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final keepContent = _hasContent;
    setState(() {
      // Pull-to-refresh must not blank the page — RefreshIndicator shows its own spinner.
      if (!keepContent) {
        _loading = true;
      }
      _error = null;
    });
    try {
      final body = await AppLocator.dashboard.summary();
      await _writeDashboardCache(body);
      if (!mounted) return;
      setState(() {
        _data = body;
        _offlineDegraded = false;
        _loading = false;
        _error = null;
      });
    } catch (e) {
      final f = e is AppFailure ? e : AppLocator.errors.map(e);
      EssFailureUi.signalIfOffline(f);
      final cached = await _readDashboardCache();
      if (!mounted) return;

      if (cached != null) {
        setState(() {
          _data = cached;
          _offlineDegraded = true;
          _loading = false;
          _error = null;
        });
        _toastOffline();
        return;
      }

      if (EssFailureUi.isConnectivity(f) && EmployeeContext.isResolved) {
        // Keep existing dashboard cards if we already rendered them.
        if (keepContent) {
          setState(() {
            _offlineDegraded = true;
            _loading = false;
            _error = null;
          });
          _toastOffline();
          return;
        }
        final ctx = EmployeeContext.current!;
        setState(() {
          _data = {
            'employee': {
              'name': ctx.name ?? '',
              'employee_code': ctx.employeeCode ?? '',
              'id': ctx.employeeId,
            },
          };
          _offlineDegraded = true;
          _loading = false;
          _error = null;
        });
        return;
      }

      // Never replace a working screen with a hard error on refresh failure.
      if (keepContent) {
        setState(() {
          _offlineDegraded = EssFailureUi.isConnectivity(f);
          _loading = false;
          _error = null;
        });
        _toastOffline(message: EssFailureUi.message(AppLocalizations.of(context), f));
        return;
      }

      setState(() {
        _error = EssFailureUi.message(AppLocalizations.of(context), f);
        _loading = false;
      });
    }
  }

  void _toastOffline({String? message}) {
    if (!mounted) return;
    final l10n = AppLocalizations.of(context);
    DsSnackbar.show(
      context,
      message: message ?? l10n.offlineNeedsConnection,
      kind: DsSnackbarKind.error,
    );
  }

  Future<void> _writeDashboardCache(Map<String, Object?> body) async {
    try {
      await AppLocator.cache.write(_dashboardCacheKey, jsonEncode(body));
    } catch (_) {}
  }

  Future<Map<String, Object?>?> _readDashboardCache() async {
    try {
      final raw = await AppLocator.cache.read(_dashboardCacheKey);
      if (raw == null || raw.isEmpty) return null;
      final decoded = jsonDecode(raw);
      if (decoded is! Map) return null;
      return decoded.map((k, v) => MapEntry(k.toString(), v));
    } catch (_) {
      return null;
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
    final job = employee is Map
        ? (employee['job_title'] ?? employee['employee_code'] ?? '')
            .toString()
            .trim()
        : '';
    final payroll = _data['payroll_summary'];
    final payrollAvailable = payroll is Map && payroll['available'] == true;
    final notif = _data['notifications_summary'];
    final unread = notif is Map ? (notif['unread'] ?? 0) : 0;
    final unreadCount =
        unread is int ? unread : int.tryParse(unread.toString()) ?? 0;
    final title = (cfg?.displayName.isNotEmpty == true)
        ? cfg!.displayName
        : l10n.navHome;

    return DsPageBackdrop(
      child: Scaffold(
        backgroundColor: Colors.transparent,
        extendBodyBehindAppBar: true,
        appBar: DsAppBar(
          title: title,
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
                    edgeOffset: kToolbarHeight + 12,
                    child: ListView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: EdgeInsets.only(
                        top:
                            MediaQuery.paddingOf(context).top + kToolbarHeight,
                        bottom: AppSpacing.xxl,
                      ),
                      children: [
                        if (_offlineDegraded)
                          Padding(
                            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                            child: DsGlassTile(
                              child: Text(l10n.offlineCachedHint),
                            ),
                          ),
                        _HeroGreeting(name: name, subtitle: job, l10n: l10n),
                        const SizedBox(height: AppSpacing.md),
                        _QuickActions(l10n: l10n, cfg: cfg),
                        const SizedBox(height: AppSpacing.lg),
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
      ),
    );
  }
}

class _HeroGreeting extends StatelessWidget {
  const _HeroGreeting({
    required this.name,
    required this.subtitle,
    required this.l10n,
  });

  final String name;
  final String subtitle;
  final AppLocalizations l10n;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.fromLTRB(20, 22, 20, 22),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(24),
          gradient: LinearGradient(
            begin: Alignment.topRight,
            end: Alignment.bottomLeft,
            colors: isDark
                ? const [
                    Color(0xFF0F3D3A),
                    Color(0xFF0B1F33),
                    Color(0xFF122B45),
                  ]
                : [
                    scheme.secondary.withValues(alpha: 0.22),
                    AppColors.navy.withValues(alpha: 0.08),
                    scheme.surface,
                  ],
          ),
          border: Border.all(
            color: AppColors.auroraTeal.withValues(alpha: isDark ? 0.35 : 0.25),
          ),
          boxShadow: [
            BoxShadow(
              color: AppColors.auroraTeal.withValues(alpha: isDark ? 0.18 : 0.1),
              blurRadius: 28,
              offset: const Offset(0, 10),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              l10n.homeGreeting,
              style: Theme.of(context).textTheme.labelLarge?.copyWith(
                    color: AppColors.auroraCyan,
                    letterSpacing: 0.4,
                  ),
            ),
            const SizedBox(height: 6),
            Text(
              name.isEmpty ? '—' : name,
              style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                    height: 1.15,
                  ),
            ),
            if (subtitle.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text(
                subtitle,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: scheme.onSurfaceVariant,
                    ),
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
    final scheme = Theme.of(context).colorScheme;
    if (data is! Map || data.isEmpty) {
      return Padding(
        padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
        child: DsGlassTile(
          child: Row(
            children: [
              const DsIconBadge(
                icon: Icons.fingerprint_rounded,
                color: AppColors.auroraAmber,
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Text(
                  l10n.homeNoAttendanceToday,
                  style: Theme.of(context).textTheme.bodyLarge,
                ),
              ),
              FilledButton.tonal(
                onPressed: () => context.go(AppRoutes.attendanceCheckIn),
                child: Text(l10n.navCheckIn),
              ),
            ],
          ),
        ),
      );
    }
    final m = data.map((k, v) => MapEntry(k.toString(), v));
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
      child: DsGlassTile(
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
                style: Theme.of(context).textTheme.titleMedium,
              ),
            ],
            if ((m['check_out'] ?? m['check_out_at'] ?? '')
                .toString()
                .isNotEmpty)
              Text(
                '${l10n.navCheckOut}: ${m['check_out'] ?? m['check_out_at']}',
                style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                      color: scheme.onSurfaceVariant,
                    ),
              ),
          ],
        ),
      ),
    );
  }
}

class _LeaveBlock extends StatelessWidget {
  const _LeaveBlock({required this.raw, required this.l10n});
  final Object? raw;
  final AppLocalizations l10n;

  static const _accents = [
    AppColors.auroraTeal,
    AppColors.auroraCyan,
    AppColors.auroraAmber,
  ];

  @override
  Widget build(BuildContext context) {
    final data = raw;
    if (data is! List || data.isEmpty) {
      return Padding(
        padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
        child: DsGlassTile(child: Text(l10n.homeNoLeaveBalances)),
      );
    }
    final rows = _primaryLeaveRows(data).take(3).toList();
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
      child: Row(
        children: [
          for (var i = 0; i < rows.length; i++) ...[
            if (i > 0) const SizedBox(width: 10),
            Expanded(
              child: _LeaveMetricTile(
                label: (rows[i]['leave_type_name'] ??
                        rows[i]['name'] ??
                        l10n.tabLeave)
                    .toString(),
                value: (rows[i]['remaining_days'] ??
                        rows[i]['balance'] ??
                        rows[i]['entitled_days'] ??
                        '-')
                    .toString(),
                accent: _accents[i % _accents.length],
              ),
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

class _LeaveMetricTile extends StatelessWidget {
  const _LeaveMetricTile({
    required this.label,
    required this.value,
    required this.accent,
  });

  final String label;
  final String value;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 14, 12, 14),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(18),
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [
            accent.withValues(alpha: 0.22),
            accent.withValues(alpha: 0.06),
          ],
        ),
        border: Border.all(color: accent.withValues(alpha: 0.4)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            value,
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                  color: accent,
                ),
          ),
          const SizedBox(height: 6),
          Text(
            label,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.labelMedium,
          ),
        ],
      ),
    );
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
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () => context.go(AppRoutes.requests),
          borderRadius: BorderRadius.circular(18),
          child: DsGlassTile(
            child: Row(
              children: [
                const DsIconBadge(
                  icon: Icons.assignment_outlined,
                  color: AppColors.auroraRose,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    text,
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                ),
                if (total > 0) DsStatusBadge(label: '$total'),
                const Icon(Icons.chevron_left_rounded),
              ],
            ),
          ),
        ),
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
      return Padding(
        padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
        child: DsGlassTile(child: Text(l10n.homeNoNotifications)),
      );
    }
    final unread = data['unread'] ?? 0;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () => context.go(AppRoutes.notifications),
          borderRadius: BorderRadius.circular(18),
          child: DsGlassTile(
            child: Text('${l10n.homeUnreadNotifications}: $unread'),
          ),
        ),
      ),
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
    if (data is! Map) return const SizedBox.shrink();
    final message =
        (data['message'] ?? l10n.homePayrollPlaceholder).toString();
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
      child: DsGlassTile(child: Text(message)),
    );
  }
}

class _QuickActions extends StatelessWidget {
  const _QuickActions({required this.l10n, required this.cfg});
  final AppLocalizations l10n;
  final MobileAppConfiguration? cfg;

  @override
  Widget build(BuildContext context) {
    final tiles = <Widget>[];
    if (cfg?.isFeatureEnabled(MobileFeatureKey.attendance) == true) {
      tiles.add(
        _ActionTile(
          label: l10n.navCheckIn,
          icon: AppIcons.checkIn,
          colors: const [Color(0xFF0D9488), Color(0xFF0F766E)],
          onTap: () => context.go(AppRoutes.attendanceCheckIn),
        ),
      );
    }
    if (cfg?.isFeatureEnabled(MobileFeatureKey.leave) == true) {
      tiles.add(
        _ActionTile(
          label: l10n.navApplyLeave,
          icon: AppIcons.leave,
          colors: const [Color(0xFF0284C7), Color(0xFF0369A1)],
          onTap: () => context.go(AppRoutes.leaveApply),
        ),
      );
    }
    if (cfg?.isFeatureEnabled(MobileFeatureKey.inquiries) == true) {
      tiles.add(
        _ActionTile(
          label: l10n.navInquiries,
          icon: Icons.support_agent_rounded,
          colors: const [Color(0xFFD97706), Color(0xFFB45309)],
          onTap: () => context.go(AppRoutes.inquiries),
        ),
      );
    }
    if (tiles.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            l10n.homeQuickActions,
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              for (var i = 0; i < tiles.length; i++) ...[
                if (i > 0) const SizedBox(width: 10),
                Expanded(child: tiles[i]),
              ],
            ],
          ),
        ],
      ),
    );
  }
}

class _ActionTile extends StatelessWidget {
  const _ActionTile({
    required this.label,
    required this.icon,
    required this.colors,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final List<Color> colors;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20),
        child: Ink(
          height: 108,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(20),
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: colors,
            ),
            boxShadow: [
              BoxShadow(
                color: colors.last.withValues(alpha: 0.35),
                blurRadius: 16,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(icon, color: Colors.white, size: 26),
                const Spacer(),
                Text(
                  label,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.labelLarge?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w700,
                      ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

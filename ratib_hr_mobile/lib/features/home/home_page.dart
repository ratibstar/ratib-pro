/// Phase 3 Reduced MVP Home — presentation only.
library;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/routing/app_routes.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/features/home/home_dtos.dart';
import 'package:ratib_hr_mobile/features/home/home_view_model.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class HomePage extends StatefulWidget {
  const HomePage({super.key});

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  late final HomeViewModel _vm;

  @override
  void initState() {
    super.initState();
    _vm = HomeViewModel()..addListener(_onVm);
    _vm.load();
  }

  void _onVm() {
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    _vm.removeListener(_onVm);
    _vm.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Scaffold(
      appBar: DsAppBar(title: l10n.navHome),
      body: switch (_vm.state) {
        HomeLoadState.idle || HomeLoadState.loading => DsLoadingState(
            message: l10n.homeLoading,
          ),
        HomeLoadState.error => DsErrorState(
            title: l10n.homeLoadFailed,
            message: _vm.errorMessage,
            actionLabel: l10n.homeRetry,
            onAction: _vm.load,
          ),
        HomeLoadState.ready => RefreshIndicator(
            onRefresh: _vm.load,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.only(bottom: AppSpacing.xxl),
              children: [
                _EmployeeHeader(name: _vm.employeeName),
                DsSectionHeader(title: l10n.homeTodayAttendance),
                _AttendanceCard(dto: _vm.attendance, l10n: l10n),
                DsSectionHeader(title: l10n.homeLeaveBalance),
                _LeaveBalances(balances: _vm.leaveBalances, l10n: l10n),
                DsSectionHeader(
                  title: l10n.homeRecentNotifications,
                  actionLabel: l10n.navNotifications,
                  onAction: () => context.go(AppRoutes.notifications),
                ),
                _Notifications(items: _vm.notifications, l10n: l10n),
                DsSectionHeader(title: l10n.homeQuickActions),
                _QuickActions(l10n: l10n),
              ],
            ),
          ),
      },
    );
  }
}

class _EmployeeHeader extends StatelessWidget {
  const _EmployeeHeader({required this.name});

  final String name;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.md,
        AppSpacing.lg,
        AppSpacing.md,
        AppSpacing.sm,
      ),
      child: Text(
        name,
        style: Theme.of(context).textTheme.headlineSmall,
      ),
    );
  }
}

class _AttendanceCard extends StatelessWidget {
  const _AttendanceCard({required this.dto, required this.l10n});

  final HomeAttendanceDto dto;
  final AppLocalizations l10n;

  @override
  Widget build(BuildContext context) {
    if (!dto.hasRecord) {
      return DsCard(
        child: Text(
          l10n.homeNoAttendanceToday,
          style: Theme.of(context).textTheme.bodyMedium,
        ),
      );
    }

    return DsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (dto.status != null && dto.status!.isNotEmpty)
            DsStatusBadge(label: dto.status!),
          if (dto.checkIn != null && dto.checkIn!.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.sm),
            Text('${l10n.navCheckIn}: ${dto.checkIn}'),
          ],
          if (dto.checkOut != null && dto.checkOut!.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.xs),
            Text('${l10n.navCheckOut}: ${dto.checkOut}'),
          ],
        ],
      ),
    );
  }
}

class _LeaveBalances extends StatelessWidget {
  const _LeaveBalances({required this.balances, required this.l10n});

  final List<HomeLeaveBalanceDto> balances;
  final AppLocalizations l10n;

  @override
  Widget build(BuildContext context) {
    if (balances.isEmpty) {
      return DsCard(
        child: Text(
          l10n.homeNoLeaveBalances,
          style: Theme.of(context).textTheme.bodyMedium,
        ),
      );
    }

    return Column(
      children: [
        for (final row in balances)
          DsCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  row.typeName,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: AppSpacing.xs),
                Text('${l10n.homeEntitled}: ${row.entitledDays}'),
                Text('${l10n.homeUsed}: ${row.usedDays}'),
              ],
            ),
          ),
      ],
    );
  }
}

class _Notifications extends StatelessWidget {
  const _Notifications({required this.items, required this.l10n});

  final List<HomeNotificationDto> items;
  final AppLocalizations l10n;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) {
      return DsCard(
        child: Text(
          l10n.homeNoNotifications,
          style: Theme.of(context).textTheme.bodyMedium,
        ),
      );
    }

    return Column(
      children: [
        for (final n in items)
          DsNotificationTile(
            title: n.title,
            body: n.message,
            timeLabel: n.createdAt,
            unread: n.unread,
          ),
      ],
    );
  }
}

class _QuickActions extends StatelessWidget {
  const _QuickActions({required this.l10n});

  final AppLocalizations l10n;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
      child: Wrap(
        spacing: AppSpacing.sm,
        runSpacing: AppSpacing.sm,
        children: [
          FilledButton.tonalIcon(
            onPressed: () => context.go(AppRoutes.attendanceCheckIn),
            icon: const Icon(AppIcons.checkIn),
            label: Text(l10n.navCheckIn),
          ),
          FilledButton.tonalIcon(
            onPressed: () => context.go(AppRoutes.leaveApply),
            icon: const Icon(AppIcons.leave),
            label: Text(l10n.navApplyLeave),
          ),
          FilledButton.tonalIcon(
            onPressed: () => context.go(AppRoutes.notifications),
            icon: const Icon(AppIcons.notifications),
            label: Text(l10n.navNotifications),
          ),
        ],
      ),
    );
  }
}

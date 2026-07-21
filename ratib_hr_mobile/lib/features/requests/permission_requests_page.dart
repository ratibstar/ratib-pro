/// Permission (short-exit) requests list — ERP PermissionRequestPort only.
library;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/routing/app_routes.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class PermissionRequestsPage extends StatefulWidget {
  const PermissionRequestsPage({super.key});

  @override
  State<PermissionRequestsPage> createState() => _PermissionRequestsPageState();
}

class _PermissionRequestsPageState extends State<PermissionRequestsPage> {
  bool _loading = true;
  String? _error;
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

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return DsPageScaffold(
      title: l10n.navPermissionRequests,
      actions: [
        IconButton(
          onPressed: () => context.go(AppRoutes.permissionApply),
          icon: const Icon(Icons.add_rounded),
        ),
      ],
      body: _loading
          ? DsLoadingState(message: l10n.genericLoading)
          : _error != null
              ? DsErrorState(
                  title: l10n.genericLoadFailed,
                  message: _error,
                  actionLabel: l10n.homeRetry,
                  onAction: _load,
                )
              : _items.isEmpty
                  ? DsEmptyState(
                      title: l10n.permissionRequestsEmpty,
                      actionLabel: l10n.permissionApply,
                      onAction: () => context.go(AppRoutes.permissionApply),
                    )
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.builder(
                        padding: const EdgeInsets.only(top: 8, bottom: 24),
                        itemCount: _items.length,
                        itemBuilder: (context, i) {
                          final row = _items[i];
                          final date =
                              (row['permission_date'] ?? '').toString();
                          final from = (row['time_from'] ?? '').toString();
                          final to = (row['time_to'] ?? '').toString();
                          final status = (row['status'] ?? '').toString();
                          final reason = (row['reason'] ?? '').toString();
                          final window = [
                            if (from.isNotEmpty) from,
                            if (to.isNotEmpty) to,
                          ].join(' – ');
                          return DsListItem(
                            title: date.isNotEmpty
                                ? date
                                : l10n.navPermissionRequests,
                            subtitle: [
                              if (window.isNotEmpty) window,
                              if (status.isNotEmpty) status,
                              if (reason.isNotEmpty) reason,
                            ].join(' · '),
                            leading: const DsIconBadge(
                              icon: Icons.schedule_outlined,
                              color: AppColors.auroraTeal,
                            ),
                          );
                        },
                      ),
                    ),
    );
  }
}

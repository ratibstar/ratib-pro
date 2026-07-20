/// Employee requests list — ERP EmployeeRequestPort only.
library;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/routing/app_routes.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class EmployeeRequestsPage extends StatefulWidget {
  const EmployeeRequestsPage({super.key});

  @override
  State<EmployeeRequestsPage> createState() => _EmployeeRequestsPageState();
}

class _EmployeeRequestsPageState extends State<EmployeeRequestsPage> {
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
      final rows = await AppLocator.employeeRequests.listMine();
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
      title: l10n.navEmployeeRequests,
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
                  ? DsEmptyState(title: l10n.requestsEmpty)
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.builder(
                        padding: const EdgeInsets.only(top: 8, bottom: 24),
                        itemCount: _items.length,
                        itemBuilder: (context, i) {
                          final row = _items[i];
                          final id = (row['id'] ?? '').toString();
                          final title = (row['request_no'] ??
                                  row['request_type'] ??
                                  l10n.navEmployeeRequests)
                              .toString();
                          final status = (row['status'] ?? '').toString();
                          final date = (row['request_date'] ??
                                  row['created_at'] ??
                                  '')
                              .toString();
                          return DsListItem(
                            title: title,
                            subtitle: [
                              if (status.isNotEmpty) status,
                              if (date.isNotEmpty) date,
                            ].join(' · '),
                            leading: const DsIconBadge(
                              icon: Icons.assignment_outlined,
                              color: AppColors.auroraRose,
                            ),
                            onTap: () => context.go(
                              '${AppRoutes.requestDetail}?id=$id',
                            ),
                          );
                        },
                      ),
                    ),
    );
  }
}

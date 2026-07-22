/// Employee requests list — ERP EmployeeRequestPort only.
library;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/ess_failure_ui.dart';
import 'package:ratib_hr_mobile/core/offline/ess_read_cache.dart';
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
  bool _offlineDegraded = false;
  List<Map<String, Object?>> _items = const [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final keep = _items.isNotEmpty;
    setState(() {
      if (!keep) _loading = true;
    });
    try {
      final snap = await EssReadCache.fetchList(
        key: EssReadCache.employeeRequests,
        fetch: () => AppLocator.employeeRequests.listMine(),
      );
      if (!mounted) return;
      setState(() {
        _items = snap.items;
        _offlineDegraded = snap.offlineDegraded;
        _loading = false;
      });
    } catch (e) {
      final f = EssFailureUi.normalize(e);
      EssFailureUi.signalIfOffline(f);
      if (!mounted) return;
      setState(() {
        _offlineDegraded = EssFailureUi.isConnectivity(f);
        _loading = false;
        if (!keep) _items = const [];
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
          : _items.isEmpty
              ? Column(
                  children: [
                    if (_offlineDegraded)
                      Padding(
                        padding: const EdgeInsets.all(16),
                        child: DsGlassTile(
                          child: Text(l10n.offlineCachedHint),
                        ),
                      ),
                    Expanded(child: DsEmptyState(title: l10n.requestsEmpty)),
                  ],
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView.builder(
                    padding: const EdgeInsets.only(top: 8, bottom: 24),
                    itemCount: _items.length + (_offlineDegraded ? 1 : 0),
                    itemBuilder: (context, i) {
                      if (_offlineDegraded && i == 0) {
                        return Padding(
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                          child: DsGlassTile(
                            child: Text(l10n.offlineCachedHint),
                          ),
                        );
                      }
                      final row = _items[_offlineDegraded ? i - 1 : i];
                      final id = (row['id'] ?? '').toString();
                      final title = (row['request_no'] ??
                              row['request_type'] ??
                              l10n.navEmployeeRequests)
                          .toString();
                      final status = (row['status'] ?? '').toString();
                      final date =
                          (row['request_date'] ?? row['created_at'] ?? '')
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

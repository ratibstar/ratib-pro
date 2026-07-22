/// Workforce ratings — read-only RatingsPort.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/ess_failure_ui.dart';
import 'package:ratib_hr_mobile/core/offline/ess_read_cache.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class RatingsPage extends StatefulWidget {
  const RatingsPage({super.key});

  @override
  State<RatingsPage> createState() => _RatingsPageState();
}

class _RatingsPageState extends State<RatingsPage> {
  bool _loading = true;
  bool _offlineDegraded = false;
  Map<String, Object?> _data = {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final keep = _data.isNotEmpty;
    setState(() {
      if (!keep) _loading = true;
    });
    try {
      final snap = await EssReadCache.fetchMap(
        key: EssReadCache.ratings,
        fetch: () => AppLocator.ratings.summary(),
      );
      if (!mounted) return;
      setState(() {
        _data = snap.data;
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
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final score = _data['performance_score'];
    final monthly = _data['monthly_evaluation'];
    final kpis = _data['kpi_summary'];
    final reviews = _data['reviews'];

    return DsPageScaffold(
      title: l10n.navRatings,
      body: _loading
          ? DsLoadingState(message: l10n.genericLoading)
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.only(bottom: AppSpacing.xxl),
                children: [
                  if (_offlineDegraded)
                    Padding(
                      padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                      child: DsGlassTile(child: Text(l10n.offlineCachedHint)),
                    ),
                  DsSectionHeader(title: l10n.ratingsScore),
                  DsKpiCard(
                    label: l10n.ratingsScore,
                    value: score == null ? '—' : score.toString(),
                    icon: Icons.stars_outlined,
                  ),
                  DsSectionHeader(title: l10n.ratingsMonthly),
                  if (monthly == null)
                    DsCard(child: Text(l10n.ratingsNoMonthly))
                  else
                    DsCard(child: Text('$monthly')),
                  DsSectionHeader(title: l10n.ratingsKpi),
                  if (kpis == null)
                    DsCard(child: Text(l10n.ratingsNoKpi))
                  else
                    DsCard(child: Text('$kpis')),
                  DsSectionHeader(title: l10n.ratingsReviews),
                  if (reviews is! List || reviews.isEmpty)
                    DsEmptyState(title: l10n.ratingsEmpty)
                  else
                    for (final r in reviews.whereType<Map>())
                      DsListItem(
                        title: (r['title'] ?? r['comment'] ?? '—').toString(),
                        subtitle: (r['date'] ?? r['created_at'] ?? '').toString(),
                      ),
                ],
              ),
            ),
    );
  }
}

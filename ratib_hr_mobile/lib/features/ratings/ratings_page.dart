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

  String _reviewTitle(Map r) {
    final title = (r['title'] ?? r['review_title'] ?? r['period'] ?? '').toString().trim();
    if (title.isNotEmpty) return title;
    final score = r['overall_score'] ?? r['score'];
    if (score != null && '$score'.trim().isNotEmpty) {
      return score.toString();
    }
    return (r['comment'] ?? r['notes'] ?? '—').toString();
  }

  String _reviewSubtitle(Map r) {
    return [
      (r['status'] ?? '').toString(),
      (r['date'] ?? r['created_at'] ?? r['review_date'] ?? '').toString(),
      if (r['overall_score'] != null) r['overall_score'].toString(),
    ].where((e) => e.trim().isNotEmpty).join(' · ');
  }

  String _monthlyLabel(Object? monthly) {
    if (monthly is Map) {
      final score = monthly['overall_score'] ?? monthly['score'];
      final period = monthly['period'] ?? monthly['title'] ?? monthly['review_date'];
      final parts = [
        if (period != null && '$period'.trim().isNotEmpty) period.toString(),
        if (score != null && '$score'.trim().isNotEmpty) score.toString(),
      ];
      if (parts.isNotEmpty) return parts.join(' · ');
    }
    return '$monthly';
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
                    DsCard(child: Text(_monthlyLabel(monthly))),
                  DsSectionHeader(title: l10n.ratingsKpi),
                  if (kpis == null || (kpis is List && kpis.isEmpty))
                    DsCard(child: Text(l10n.ratingsNoKpi))
                  else
                    DsCard(child: Text('$kpis')),
                  DsSectionHeader(title: l10n.ratingsReviews),
                  if (reviews is! List || reviews.isEmpty)
                    DsEmptyState(
                      title: l10n.ratingsEmpty,
                      message: l10n.ratingsEmptyHint,
                    )
                  else
                    for (final r in reviews.whereType<Map>())
                      DsListItem(
                        title: _reviewTitle(r),
                        subtitle: _reviewSubtitle(r),
                        leading: const DsIconBadge(
                          icon: Icons.star_outline_rounded,
                          color: AppColors.auroraAmber,
                        ),
                        trailing: const SizedBox.shrink(),
                      ),
                ],
              ),
            ),
    );
  }
}

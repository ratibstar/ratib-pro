/// Workforce ratings — read-only RatingsPort.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
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
      final body = await AppLocator.ratings.summary();
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
    final score = _data['performance_score'];
    final monthly = _data['monthly_evaluation'];
    final kpis = _data['kpi_summary'];
    final reviews = _data['reviews'];

    return DsPageScaffold(
      title: l10n.navRatings,
      body: _loading
          ? DsLoadingState(message: l10n.genericLoading)
          : _error != null
              ? DsErrorState(
                  title: l10n.genericLoadFailed,
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
                      DsSectionHeader(title: l10n.ratingsScore),
                      DsKpiCard(
                        label: l10n.ratingsScore,
                        value: score == null ? '—' : score.toString(),
                        icon: Icons.stars_outlined,
                        accent: AppColors.auroraAmber,
                      ),
                      DsSectionHeader(title: l10n.ratingsMonthly),
                      DsCard(
                        child: Text(
                          monthly is Map
                              ? (monthly['title'] ??
                                      monthly['period'] ??
                                      monthly['overall_score'] ??
                                      l10n.ratingsNoMonthly)
                                  .toString()
                              : l10n.ratingsNoMonthly,
                        ),
                      ),
                      DsSectionHeader(title: l10n.ratingsKpi),
                      if (kpis is! List || kpis.isEmpty)
                        DsCard(child: Text(l10n.ratingsNoKpi))
                      else
                        for (final k in kpis.whereType<Map>().take(8))
                          DsKpiCard(
                            label: (k['name'] ?? k['label'] ?? l10n.ratingsKpi)
                                .toString(),
                            value: (k['value'] ?? k['score'] ?? '—').toString(),
                            icon: Icons.insights_outlined,
                            accent: AppColors.auroraTeal,
                          ),
                      DsSectionHeader(title: l10n.ratingsReviews),
                      if (reviews is! List || reviews.isEmpty)
                        DsCard(child: Text(l10n.ratingsEmpty))
                      else
                        for (final r in reviews.whereType<Map>().take(20))
                          DsListItem(
                            title: (r['title'] ??
                                    r['period'] ??
                                    r['review_period'] ??
                                    l10n.navRatings)
                                .toString(),
                            subtitle:
                                (r['overall_score'] ?? r['status'] ?? '')
                                    .toString(),
                            leading: const DsIconBadge(
                              icon: Icons.rate_review_outlined,
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

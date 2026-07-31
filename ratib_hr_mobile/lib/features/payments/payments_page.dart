/// Payment methods architecture — no processing.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/ess_failure_ui.dart';
import 'package:ratib_hr_mobile/core/offline/ess_read_cache.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class PaymentsPage extends StatefulWidget {
  const PaymentsPage({super.key});

  @override
  State<PaymentsPage> createState() => _PaymentsPageState();
}

class _PaymentsPageState extends State<PaymentsPage> {
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
        key: EssReadCache.payments,
        fetch: () => AppLocator.payments.list(),
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

  List<Map<String, Object?>> _gateways() {
    final raw = _data['gateways'];
    if (raw is! List) return const [];
    return raw
        .whereType<Map>()
        .map((e) => e.map((k, v) => MapEntry(k.toString(), v as Object?)))
        .toList();
  }

  List<Map<String, Object?>> _bankAccounts() {
    final raw = _data['bank_accounts'];
    if (raw is! List) return const [];
    return raw
        .whereType<Map>()
        .map((e) => e.map((k, v) => MapEntry(k.toString(), v as Object?)))
        .toList();
  }

  String _gatewayLabel(AppLocalizations l10n, Map<String, Object?> g) {
    final ar = (g['label_ar'] ?? '').toString().trim();
    final en = (g['label_en'] ?? '').toString().trim();
    if (l10n.isArabic) {
      return ar.isNotEmpty ? ar : (en.isNotEmpty ? en : (g['code'] ?? '').toString());
    }
    return en.isNotEmpty ? en : (ar.isNotEmpty ? ar : (g['code'] ?? '').toString());
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final available = _data['available'] == true;
    final gateways = _gateways();
    final banks = _bankAccounts();
    final salary = (_data['salary_payment'] ?? '').toString().trim();
    final wallet = _data['wallet'];

    return DsPageScaffold(
      title: l10n.navPayments,
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
                  DsSectionHeader(title: l10n.paymentsSalary),
                  DsCard(
                    child: Row(
                      children: [
                        DsIconBadge(
                          icon: Icons.payments_outlined,
                          color: available
                              ? AppColors.auroraTeal
                              : AppColors.badgeNeutral,
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Text(
                            available
                                ? (salary.isNotEmpty
                                    ? salary
                                    : l10n.paymentsReady)
                                : l10n.paymentsUnavailable,
                          ),
                        ),
                      ],
                    ),
                  ),
                  DsSectionHeader(title: l10n.paymentsBanks),
                  if (banks.isEmpty)
                    DsCard(
                      child: Row(
                        children: [
                          const DsIconBadge(
                            icon: Icons.account_balance_outlined,
                            color: AppColors.auroraCyan,
                          ),
                          const SizedBox(width: 14),
                          Expanded(child: Text(l10n.paymentsBanksPlaceholder)),
                        ],
                      ),
                    )
                  else
                    for (final b in banks)
                      DsListItem(
                        title: (b['bank_name'] ??
                                b['name'] ??
                                l10n.paymentsBanks)
                            .toString(),
                        subtitle: [
                          (b['account_mask'] ?? b['iban_mask'] ?? '').toString(),
                          (b['currency'] ?? '').toString(),
                        ].where((e) => e.isNotEmpty).join(' · '),
                        leading: const DsIconBadge(
                          icon: Icons.account_balance_outlined,
                          color: AppColors.auroraCyan,
                        ),
                        trailing: const SizedBox.shrink(),
                      ),
                  DsSectionHeader(title: l10n.paymentsWallet),
                  DsCard(
                    child: Row(
                      children: [
                        const DsIconBadge(
                          icon: Icons.account_balance_wallet_outlined,
                          color: AppColors.auroraAmber,
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Text(
                            wallet == null
                                ? l10n.paymentsWalletPlaceholder
                                : wallet.toString(),
                          ),
                        ),
                      ],
                    ),
                  ),
                  DsSectionHeader(title: l10n.paymentsGateways),
                  if (!available || gateways.isEmpty)
                    DsCard(
                      child: Row(
                        children: [
                          const DsIconBadge(
                            icon: Icons.credit_card_outlined,
                            color: AppColors.auroraRose,
                          ),
                          const SizedBox(width: 14),
                          Expanded(
                            child: Text(
                              available
                                  ? l10n.paymentsGatewaysPlaceholder
                                  : l10n.paymentsUnavailable,
                            ),
                          ),
                        ],
                      ),
                    )
                  else
                    for (final g in gateways)
                      DsListItem(
                        title: _gatewayLabel(l10n, g),
                        subtitle: l10n.paymentsGatewayInfoOnly,
                        leading: const DsIconBadge(
                          icon: Icons.credit_card_outlined,
                          color: AppColors.auroraRose,
                        ),
                        trailing: const SizedBox.shrink(),
                      ),
                ],
              ),
            ),
    );
  }
}

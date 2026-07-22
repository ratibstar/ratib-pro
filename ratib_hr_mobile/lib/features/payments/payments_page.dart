/// Payment methods architecture — no processing.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/errors/ess_failure_ui.dart';
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
      final body = await AppLocator.payments.list();
      if (!mounted) return;
      setState(() {
        _data = body;
        _loading = false;
      });
    } catch (e) {
      final f = e is AppFailure ? e : AppLocator.errors.map(e);
      if (!mounted) return;
      setState(() {
        _error = EssFailureUi.message(AppLocalizations.of(context), f);
        EssFailureUi.signalIfOffline(f);
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final available = _data['available'] == true;

    return DsPageScaffold(
      title: l10n.navPayments,
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
                                    ? (_data['salary_payment']?.toString() ??
                                        l10n.paymentsReady)
                                    : l10n.paymentsUnavailable,
                              ),
                            ),
                          ],
                        ),
                      ),
                      DsSectionHeader(title: l10n.paymentsBanks),
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
                              child: Text(l10n.paymentsWalletPlaceholder),
                            ),
                          ],
                        ),
                      ),
                      DsSectionHeader(title: l10n.paymentsGateways),
                      DsCard(
                        child: Row(
                          children: [
                            const DsIconBadge(
                              icon: Icons.credit_card_outlined,
                              color: AppColors.auroraRose,
                            ),
                            const SizedBox(width: 14),
                            Expanded(
                              child: Text(l10n.paymentsGatewaysPlaceholder),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
    );
  }
}

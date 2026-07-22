/// Payslip detail + open/download preview (online only).
library;

import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/ess_failure_ui.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/features/payslips/payslip_state.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class PayslipDetailScreen extends StatefulWidget {
  const PayslipDetailScreen({super.key, required this.payslipId});

  final String payslipId;

  @override
  State<PayslipDetailScreen> createState() => _PayslipDetailScreenState();
}

class _PayslipDetailScreenState extends State<PayslipDetailScreen> {
  late final PayslipState _state;

  @override
  void initState() {
    super.initState();
    _state = PayslipState(repository: AppLocator.payslipRepository)
      ..addListener(_onChanged);
    _state.loadDetail(widget.payslipId);
  }

  void _onChanged() {
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    _state
      ..removeListener(_onChanged)
      ..dispose();
    super.dispose();
  }

  Future<void> _open() async {
    final ok = await _state.openDownload(widget.payslipId);
    if (!mounted || !ok) return;
    final bytes = _state.previewBytes;
    if (bytes == null) return;
    final text = utf8.decode(bytes, allowMalformed: true);
    await showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(AppLocalizations.of(ctx).payslipDownload),
        content: SingleChildScrollView(child: Text(text)),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: Text(AppLocalizations.of(ctx).genericClose),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final row = _state.detail;
    return DsPageScaffold(
      title: l10n.payslipDetailTitle,
      body: _state.status == PayslipLoadStatus.loading
          ? DsLoadingState(message: l10n.genericLoading)
          : _state.status == PayslipLoadStatus.error
              ? DsErrorState(
                  title: l10n.genericLoadFailed,
                  message: EssFailureUi.fromStored(
                    l10n,
                    code: _state.errorCode,
                    message: _state.errorMessage,
                  ),
                  actionLabel: l10n.homeRetry,
                  onAction: () => _state.loadDetail(widget.payslipId),
                )
              : ListView(
                  padding: const EdgeInsets.only(bottom: 32),
                  children: [
                    DsSectionHeader(title: l10n.payslipDetailTitle),
                    DsCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          if ((row['status'] ?? '').toString().isNotEmpty)
                            DsStatusBadge(label: row['status'].toString()),
                          const SizedBox(height: AppSpacing.sm),
                          Text('${l10n.payslipPeriod}: ${row['period'] ?? '-'}'),
                          Text('${l10n.payslipGross}: ${row['gross_amount'] ?? '-'}'),
                          Text('${l10n.payslipNet}: ${row['net_amount'] ?? '-'}'),
                          Text(
                            '${l10n.payslipMonthYear}: ${row['month'] ?? '-'} / ${row['year'] ?? '-'}',
                          ),
                          const SizedBox(height: AppSpacing.md),
                          FilledButton.icon(
                            onPressed: _state.downloading ? null : _open,
                            icon: _state.downloading
                                ? const SizedBox(
                                    width: 16,
                                    height: 16,
                                    child: CircularProgressIndicator(strokeWidth: 2),
                                  )
                                : const Icon(Icons.download_outlined),
                            label: Text(l10n.payslipDownload),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
    );
  }
}

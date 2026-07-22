/// Complaints & inquiries — InquiryPort only (ERP creates requests).
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/errors/ess_failure_ui.dart';
import 'package:ratib_hr_mobile/core/offline/ess_read_cache.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class InquiriesPage extends StatefulWidget {
  const InquiriesPage({super.key});

  @override
  State<InquiriesPage> createState() => _InquiriesPageState();
}

class _InquiriesPageState extends State<InquiriesPage> {
  bool _loading = true;
  String? _error;
  bool _offlineDegraded = false;
  List<Map<String, Object?>> _items = const [];
  final _message = TextEditingController();
  String _submitType = 'inquiry';

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _message.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final keep = _items.isNotEmpty;
    setState(() {
      if (!keep) _loading = true;
      _error = null;
    });
    try {
      final snap = await EssReadCache.fetchList(
        key: EssReadCache.inquiries,
        fetch: () => AppLocator.inquiries.listMine(),
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
        if (!keep) {
          _items = const [];
        }
      });
    }
  }

  Future<void> _submit() async {
    final text = _message.text.trim();
    if (text.isEmpty) {
      DsSnackbar.show(
        context,
        message: AppLocalizations.of(context).inquiryMessageRequired,
        kind: DsSnackbarKind.error,
      );
      return;
    }
    try {
      await AppLocator.inquiries.submit({
        'request_type': _submitType,
        'notes': text,
      });
      _message.clear();
      if (!mounted) return;
      DsSnackbar.show(
        context,
        message: AppLocalizations.of(context).inquirySubmitted,
        kind: DsSnackbarKind.success,
      );
      await _load();
    } catch (e) {
      if (!mounted) return;
      final f = e is AppFailure ? e : AppLocator.errors.map(e);
      DsSnackbar.show(
        context,
        message: EssFailureUi.message(AppLocalizations.of(context), f),
        kind: DsSnackbarKind.error,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return DsPageScaffold(
      title: l10n.navInquiries,
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
                      DsSectionHeader(title: l10n.inquirySubmit),
                      DsCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            SegmentedButton<String>(
                              segments: [
                                ButtonSegment(
                                  value: 'inquiry',
                                  label: Text(l10n.inquiryTypeInquiry),
                                ),
                                ButtonSegment(
                                  value: 'complaint',
                                  label: Text(l10n.inquiryTypeComplaint),
                                ),
                              ],
                              selected: {_submitType},
                              onSelectionChanged: (s) {
                                setState(() => _submitType = s.first);
                              },
                            ),
                            const SizedBox(height: AppSpacing.md),
                            TextField(
                              controller: _message,
                              maxLines: 4,
                              decoration: InputDecoration(
                                hintText: l10n.inquiryMessageHint,
                              ),
                            ),
                            const SizedBox(height: AppSpacing.md),
                            DsPrimaryButton(
                              label: l10n.inquirySubmit,
                              onPressed: _submit,
                              icon: Icons.send_rounded,
                            ),
                          ],
                        ),
                      ),
                      DsSectionHeader(title: l10n.inquiryHistory),
                      if (_items.isEmpty)
                        DsCard(child: Text(l10n.inquiryEmpty))
                      else
                        for (final row in _items)
                          DsListItem(
                            title: (row['request_type'] ?? l10n.navInquiries)
                                .toString(),
                            subtitle: [
                              (row['status'] ?? '').toString(),
                              (row['notes'] ?? '').toString(),
                            ].where((e) => e.isNotEmpty).join(' · '),
                            leading: const DsIconBadge(
                              icon: Icons.support_agent_outlined,
                              color: Color(0xFF0284C7),
                            ),
                            trailing: const SizedBox.shrink(),
                          ),
                    ],
                  ),
                ),
    );
  }
}

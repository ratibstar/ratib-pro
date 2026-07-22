/// Request detail — ERP EmployeeRequestPort.detail with offline list fallback.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/errors/ess_failure_ui.dart';
import 'package:ratib_hr_mobile/core/offline/ess_read_cache.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class RequestDetailPage extends StatefulWidget {
  const RequestDetailPage({super.key, required this.requestId});

  final String requestId;

  @override
  State<RequestDetailPage> createState() => _RequestDetailPageState();
}

class _RequestDetailPageState extends State<RequestDetailPage> {
  bool _loading = true;
  bool _offlineDegraded = false;
  Map<String, Object?> _data = {};
  List<Map<String, Object?>> _history = const [];

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
      final row = await AppLocator.employeeRequests.detail(widget.requestId);
      if (!mounted) return;
      _applyRow(row, offline: false);
    } catch (e) {
      final f = e is AppFailure ? e : AppLocator.errors.map(e);
      EssFailureUi.signalIfOffline(f);
      if (!mounted) return;
      if (EssFailureUi.isConnectivity(f) || keep) {
        final cached = await EssReadCache.readList(EssReadCache.employeeRequests);
        final hit = cached == null
            ? null
            : EssReadCache.findById(cached, widget.requestId);
        if (hit != null) {
          _applyRow(hit, offline: true);
          return;
        }
        if (keep) {
          setState(() {
            _offlineDegraded = true;
            _loading = false;
          });
          return;
        }
        setState(() {
          _data = {
            'id': widget.requestId,
            'status': '—',
          };
          _history = const [];
          _offlineDegraded = true;
          _loading = false;
        });
        return;
      }
      setState(() {
        _loading = false;
        _offlineDegraded = false;
        _data = const {};
      });
    }
  }

  void _applyRow(Map<String, Object?> row, {required bool offline}) {
    final hist = row['history'];
    setState(() {
      _data = row;
      _history = hist is List
          ? hist
              .whereType<Map>()
              .map((e) => e.map((k, v) => MapEntry(k.toString(), v)))
              .toList(growable: false)
          : const [];
      _offlineDegraded = offline;
      _loading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return DsPageScaffold(
      title: l10n.requestDetailTitle,
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
                  DsSectionHeader(title: l10n.requestStatus),
                  DsCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if ((_data['status'] ?? '').toString().isNotEmpty)
                          DsStatusBadge(label: _data['status'].toString()),
                        const SizedBox(height: AppSpacing.sm),
                        Text(
                          '${l10n.requestType}: ${_data['request_type'] ?? '-'}',
                        ),
                        Text(
                          '${l10n.requestNumber}: ${_data['request_no'] ?? _data['id'] ?? '-'}',
                        ),
                        if ((_data['request_date'] ?? '')
                            .toString()
                            .isNotEmpty)
                          Text(
                            '${l10n.requestDate}: ${_data['request_date']}',
                          ),
                        if ((_data['notes'] ?? '').toString().isNotEmpty) ...[
                          const SizedBox(height: AppSpacing.sm),
                          Text(_data['notes'].toString()),
                        ],
                      ],
                    ),
                  ),
                  DsSectionHeader(title: l10n.requestHistory),
                  if (_history.isEmpty)
                    DsCard(
                      child: Text(
                        _offlineDegraded
                            ? l10n.offlineNeedsConnection
                            : l10n.requestHistoryEmpty,
                      ),
                    )
                  else
                    for (final h in _history)
                      DsCard(
                        child: Text(
                          (h['message'] ?? h['note'] ?? h.toString())
                              .toString(),
                        ),
                      ),
                ],
              ),
            ),
    );
  }
}

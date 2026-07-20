/// Sync status — pending queue + connectivity (presentation only).
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/offline/connectivity_controller.dart';
import 'package:ratib_hr_mobile/core/offline/offline_sync_service.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class SyncStatusScreen extends StatefulWidget {
  const SyncStatusScreen({super.key});

  @override
  State<SyncStatusScreen> createState() => _SyncStatusScreenState();
}

class _SyncStatusScreenState extends State<SyncStatusScreen> {
  List<Map<String, Object?>> _items = const [];
  int _pending = 0;
  bool _busy = false;
  String? _resultLabel;

  @override
  void initState() {
    super.initState();
    AppLocator.connectivity.addListener(_onChanged);
    _refresh();
  }

  void _onChanged() {
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    AppLocator.connectivity.removeListener(_onChanged);
    super.dispose();
  }

  Future<void> _refresh() async {
    final sync = AppLocator.offlineSync;
    final items = await sync.pendingItems();
    final count = await sync.pendingCount();
    if (!mounted) return;
    setState(() {
      _items = items;
      _pending = count;
    });
  }

  Future<void> _syncNow() async {
    setState(() {
      _busy = true;
      _resultLabel = null;
    });
    final l10n = AppLocalizations.of(context);
    final result = await AppLocator.offlineSync.flush();
    if (!mounted) return;
    String label;
    switch (result) {
      case OfflineFlushResult.offline:
        label = l10n.syncWaitingConnection;
      case OfflineFlushResult.synced:
      case OfflineFlushResult.empty:
        label = l10n.syncCompleted;
      case OfflineFlushResult.failed:
      case OfflineFlushResult.partial:
        label = l10n.syncFailedContactAdmin;
    }
    setState(() {
      _busy = false;
      _resultLabel = label;
    });
    await _refresh();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final conn = AppLocator.connectivity;
    return DsPageScaffold(
      title: l10n.navSyncStatus,
      body: RefreshIndicator(
        onRefresh: () async {
          await AppLocator.connectivity.probe();
          await _refresh();
        },
        child: ListView(
          padding: const EdgeInsets.only(bottom: 32),
          children: [
            if (!conn.online)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                child: DsGlassTile(
                  child: Text(l10n.syncWaitingConnection),
                ),
              ),
            if (_pending > 0)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                child: DsGlassTile(
                  child: Text(l10n.syncPendingCount(_pending)),
                ),
              ),
            if (_resultLabel != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                child: DsGlassTile(child: Text(_resultLabel!)),
              ),
            DsSectionHeader(title: l10n.navSyncStatus),
            DsCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    conn.online ? l10n.syncOnline : l10n.syncWaitingConnection,
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  Text(
                    '${l10n.syncLastOutcome}: ${_outcomeLabel(conn, l10n)}',
                  ),
                  const SizedBox(height: AppSpacing.md),
                  FilledButton.icon(
                    onPressed: _busy ? null : _syncNow,
                    icon: _busy
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.sync),
                    label: Text(l10n.syncNow),
                  ),
                ],
              ),
            ),
            DsSectionHeader(title: l10n.syncPendingActions),
            if (_items.isEmpty)
              Padding(
                padding: const EdgeInsets.all(16),
                child: DsEmptyState(title: l10n.syncQueueEmpty),
              )
            else
              ..._items.map((item) {
                final action = (item['action'] ?? '').toString();
                final at = (item['enqueued_at'] ?? '').toString();
                final err = (item['last_error'] ?? '').toString();
                return DsListItem(
                  title: action,
                  subtitle: [
                    if (at.isNotEmpty) at,
                    if (err.isNotEmpty) err,
                  ].join(' · '),
                  leading: const DsIconBadge(
                    icon: Icons.cloud_queue_outlined,
                    color: AppColors.auroraAmber,
                  ),
                );
              }),
          ],
        ),
      ),
    );
  }

  String _outcomeLabel(ConnectivityController conn, AppLocalizations l10n) {
    switch (conn.lastOutcome) {
      case SyncOutcome.idle:
        return '—';
      case SyncOutcome.waiting:
        return l10n.syncWaitingConnection;
      case SyncOutcome.completed:
        return l10n.syncCompleted;
      case SyncOutcome.failed:
        return l10n.syncFailedContactAdmin;
    }
  }
}

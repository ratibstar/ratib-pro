/// Shell offline / pending banner — presentation only.
library;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/routing/app_routes.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class OfflineBannerHost extends StatefulWidget {
  const OfflineBannerHost({super.key, required this.child});

  final Widget child;

  @override
  State<OfflineBannerHost> createState() => _OfflineBannerHostState();
}

class _OfflineBannerHostState extends State<OfflineBannerHost> {
  int _pending = 0;

  @override
  void initState() {
    super.initState();
    AppLocator.connectivity.addListener(_onChanged);
    _loadPending();
  }

  void _onChanged() {
    _loadPending();
  }

  Future<void> _loadPending() async {
    try {
      final n = await AppLocator.offlineSync.pendingCount();
      if (mounted) setState(() => _pending = n);
    } catch (_) {}
  }

  @override
  void dispose() {
    AppLocator.connectivity.removeListener(_onChanged);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final offline = !AppLocator.connectivity.online;
    final show = offline || _pending > 0;
    return Column(
      children: [
        if (show)
          Material(
            color: Colors.transparent,
            child: InkWell(
              onTap: () => context.go(AppRoutes.syncStatus),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
                child: DsGlassTile(
                  child: Row(
                    children: [
                      Icon(
                        offline ? Icons.cloud_off : Icons.cloud_queue,
                        size: 18,
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          offline
                              ? l10n.syncWaitingConnection
                              : l10n.syncPendingCount(_pending),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        Expanded(child: widget.child),
      ],
    );
  }
}

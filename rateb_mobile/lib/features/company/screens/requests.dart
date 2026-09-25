import 'package:flutter/material.dart';

import '../../../core/models/company_models.dart';
import '../../../core/services/resilient_loader.dart';
import '../../../core/services/screen_cache.dart';
import '../../../core/services/rateb_api_service.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/widgets/data_state_view.dart';
import '../../../shared/widgets/skeleton_loader.dart';

class Requests extends StatefulWidget {
  const Requests({super.key});

  @override
  State<Requests> createState() => _RequestsState();
}

class _RequestsState extends State<Requests> {
  ScreenLoadResult<CompanyRequestsData>? _result;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool manualRetry = false}) async {
    if (manualRetry || _result == null) {
      setState(() {
        _result = (_result ?? const ScreenLoadResult<CompanyRequestsData>())
            .copyWith(isLoading: true, clearError: true);
      });
    }

    final next = await ResilientLoader.execute(
      cacheKey: CacheKeys.companyRequests,
      fetch: RatebApiService.instance.getCompanyRequests,
      manualRetry: manualRetry,
    );
    if (!mounted) return;
    setState(() => _result = next);
  }

  @override
  Widget build(BuildContext context) {
    final result = _result;
    final requests = result?.data?.requests ?? const <CompanyRequest>[];
    final l10n = AppLocalizations.of(context);

    return DataStateView(
      isLoading: result?.isLoading ?? true,
      isFromCache: result?.isFromCache ?? false,
      errorMessage: result?.showError == true ? result!.error : null,
      staleMessage: result?.showStaleData == true ? result!.error : null,
      onRetry: () => _load(manualRetry: true),
      isEmpty: requests.isEmpty && result?.isLoading != true,
      emptyTitle: EmptyStateCopy.companyRequestsTitle,
      emptyMessage: EmptyStateCopy.companyRequestsMessage,
      emptyIcon: Icons.inbox_outlined,
      skeletonType: SkeletonType.list,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  l10n.requestsTitle,
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                ),
              ),
              FilledButton.tonalIcon(
                onPressed: () {},
                icon: const Icon(Icons.add),
                label: Text(l10n.newRequest),
              ),
            ],
          ),
          const SizedBox(height: 12),
          ...requests.map(
            (request) => Card(
              margin: const EdgeInsets.only(bottom: 10),
              child: ListTile(
                leading: const Icon(Icons.description_outlined),
                title: Text(l10n.server(request.title)),
                subtitle: Text(
                  l10n.server(
                    request.updatedLabel != null
                        ? '${request.subtitle} · ${request.updatedLabel}'
                        : request.subtitle,
                  ),
                ),
                trailing: const Icon(Icons.chevron_right),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

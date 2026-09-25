import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/models/auth_response.dart';
import '../../../core/models/user_role.dart';
import '../../../core/services/resilient_loader.dart';
import '../../../core/services/screen_cache.dart';
import '../../../core/services/rateb_api_service.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/widgets/data_state_view.dart';
import '../../../shared/widgets/skeleton_loader.dart';
import '../../auth/providers/auth_provider.dart';

class WorkerProfile extends StatefulWidget {
  const WorkerProfile({super.key});

  @override
  State<WorkerProfile> createState() => _WorkerProfileState();
}

class _WorkerProfileState extends State<WorkerProfile> {
  ScreenLoadResult<UserProfile>? _result;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool manualRetry = false}) async {
    final role = context.read<AuthProvider>().role;
    if (role == null) return;

    if (manualRetry || _result == null) {
      setState(() {
        _result = (_result ?? const ScreenLoadResult<UserProfile>())
            .copyWith(isLoading: true, clearError: true);
      });
    }

    final next = await ResilientLoader.execute(
      cacheKey: CacheKeys.workerProfile,
      fetch: () => RatebApiService.instance.getProfile(role),
      manualRetry: manualRetry,
    );
    if (!mounted) return;
    setState(() => _result = next);
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final theme = Theme.of(context);
    final result = _result;
    final profile = result?.data;
    final l10n = AppLocalizations.of(context);
    final roleLabel = profile != null ? l10n.roleName(profile.role) : null;

    return DataStateView(
      isLoading: result?.isLoading ?? true,
      isFromCache: result?.isFromCache ?? false,
      errorMessage: result?.showError == true ? result!.error : null,
      staleMessage: result?.showStaleData == true ? result!.error : null,
      onRetry: () => _load(manualRetry: true),
      isEmpty: result?.hasData == false && result?.isLoading == false,
      emptyTitle: EmptyStateCopy.workerProfileTitle,
      emptyMessage: EmptyStateCopy.workerProfileMessage,
      emptyIcon: Icons.person_outline,
      skeletonType: SkeletonType.profile,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      CircleAvatar(
                        radius: 28,
                        backgroundColor:
                            theme.colorScheme.primary.withValues(alpha: 0.12),
                        child: Icon(
                          Icons.person,
                          color: theme.colorScheme.primary,
                          size: 28,
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              profile?.username ??
                                  auth.username ??
                                  l10n.roleName(UserRole.worker),
                              style: theme.textTheme.titleLarge?.copyWith(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            Text(
                              roleLabel ?? l10n.workerAccount,
                              style: theme.textTheme.bodyMedium?.copyWith(
                                color: theme.colorScheme.onSurface
                                    .withValues(alpha: 0.65),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const Divider(height: 32),
                  _ProfileRow(
                    label: l10n.profileRole,
                    value: roleLabel ?? l10n.roleName(UserRole.worker),
                  ),
                  _ProfileRow(
                    label: l10n.profileStatus,
                    value: profile?.status != null
                        ? l10n.server(profile!.status!)
                        : l10n.statusActive,
                  ),
                  if (profile?.email != null && profile!.email!.isNotEmpty)
                    _ProfileRow(
                      label: l10n.profileEmail,
                      value: profile.email!,
                    ),
                  if (profile?.phone != null && profile!.phone!.isNotEmpty)
                    _ProfileRow(
                      label: l10n.profilePhone,
                      value: profile.phone!,
                    ),
                  if (profile?.countryName != null &&
                      profile!.countryName!.isNotEmpty)
                    _ProfileRow(
                      label: l10n.profileCountry,
                      value: profile.countryName!,
                    ),
                  _ProfileRow(
                    label: l10n.profilePortal,
                    value: l10n.mobileWorkforce,
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ProfileRow extends StatelessWidget {
  const _ProfileRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: Theme.of(context)
                        .colorScheme
                        .onSurface
                        .withValues(alpha: 0.65),
                  ),
            ),
          ),
          Text(
            value,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  fontWeight: FontWeight.w600,
                ),
          ),
        ],
      ),
    );
  }
}

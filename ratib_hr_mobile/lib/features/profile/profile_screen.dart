/// Employee profile screen — ERP profile DTO only.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/features/profile/profile_state.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  late final ProfileState _state;

  @override
  void initState() {
    super.initState();
    _state = ProfileState(repository: AppLocator.profileRepository)
      ..addListener(_onChanged);
    _state.load();
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

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final p = _state.profile;
    final name = (p['full_name'] ?? '').toString();
    final photo = (p['photo_url'] ?? '').toString();

    return DsPageScaffold(
      title: l10n.navProfile,
      body: _state.status == ProfileLoadStatus.loading
          ? DsLoadingState(message: l10n.genericLoading)
          : _state.status == ProfileLoadStatus.error
              ? DsErrorState(
                  title: l10n.genericLoadFailed,
                  message: _state.errorMessage ?? _state.errorCode,
                  actionLabel: l10n.homeRetry,
                  onAction: _state.load,
                )
              : RefreshIndicator(
                  onRefresh: _state.load,
                  child: ListView(
                    padding: const EdgeInsets.only(bottom: 32),
                    children: [
                      const SizedBox(height: AppSpacing.md),
                      Center(
                        child: _Avatar(
                          name: name,
                          photoUrl: photo.isEmpty ? null : photo,
                        ),
                      ),
                      const SizedBox(height: AppSpacing.sm),
                      Center(
                        child: Text(
                          name.isEmpty ? l10n.navProfile : name,
                          style: Theme.of(context).textTheme.titleLarge,
                          textAlign: TextAlign.center,
                        ),
                      ),
                      if ((p['employee_no'] ?? '').toString().isNotEmpty)
                        Center(
                          child: Text(
                            '${l10n.profileEmployeeNo}: ${p['employee_no']}',
                            style: Theme.of(context).textTheme.bodyMedium,
                          ),
                        ),
                      if ((p['status'] ?? '').toString().isNotEmpty) ...[
                        const SizedBox(height: AppSpacing.sm),
                        Center(
                          child: DsStatusBadge(label: p['status'].toString()),
                        ),
                      ],
                      DsSectionHeader(title: l10n.profileBasicInfo),
                      DsCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _line(l10n.profileFullName, p['full_name']),
                            _line(l10n.profileEmployeeNo, p['employee_no']),
                            _line(l10n.profileJoinDate, p['join_date']),
                          ],
                        ),
                      ),
                      DsSectionHeader(title: l10n.profileJobInfo),
                      DsCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _line(l10n.profileJobTitle, p['job_title']),
                            _line(l10n.profileDepartment, p['department']),
                            _line(l10n.profileBranch, p['branch']),
                            _line(l10n.profileManager, p['manager']),
                          ],
                        ),
                      ),
                      DsSectionHeader(title: l10n.profileContact),
                      DsCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _line(l10n.profileEmail, p['email']),
                            _line(l10n.profilePhone, p['phone']),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
    );
  }

  Widget _line(String label, Object? value) {
    final v = (value ?? '').toString().trim();
    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.sm),
      child: Text('$label: ${v.isEmpty ? '—' : v}'),
    );
  }
}

class _Avatar extends StatelessWidget {
  const _Avatar({required this.name, this.photoUrl});

  final String name;
  final String? photoUrl;

  @override
  Widget build(BuildContext context) {
    final url = photoUrl;
    if (url != null && url.isNotEmpty) {
      return CircleAvatar(
        radius: 40,
        backgroundColor: Theme.of(context).colorScheme.secondaryContainer,
        backgroundImage: NetworkImage(url),
        onBackgroundImageError: (_, __) {},
        child: null,
      );
    }
    return DsAvatar(initials: name.isEmpty ? null : name, size: 80);
  }
}

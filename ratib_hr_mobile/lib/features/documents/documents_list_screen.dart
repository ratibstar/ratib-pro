/// Documents list with optional category filter.
library;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/ess_failure_ui.dart';
import 'package:ratib_hr_mobile/core/routing/app_routes.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/features/documents/documents_state.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class DocumentsListScreen extends StatefulWidget {
  const DocumentsListScreen({super.key});

  @override
  State<DocumentsListScreen> createState() => _DocumentsListScreenState();
}

class _DocumentsListScreenState extends State<DocumentsListScreen> {
  late final DocumentsState _state;
  String? _selectedCategory;

  @override
  void initState() {
    super.initState();
    _state = DocumentsState(repository: AppLocator.documentsRepository)
      ..addListener(_onChanged);
    _state.loadList();
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

  List<String> get _categories {
    final set = <String>{};
    for (final row in _state.items) {
      final c = (row['category'] ?? '').toString().trim();
      if (c.isNotEmpty) set.add(c);
    }
    final list = set.toList()..sort();
    return list;
  }

  List<Map<String, Object?>> get _visible {
    final selected = _selectedCategory;
    if (selected == null || selected.isEmpty) return _state.items;
    return _state.items
        .where((r) => (r['category'] ?? '').toString() == selected)
        .toList(growable: false);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final categories = _categories;
    final visible = _visible;
    return DsPageScaffold(
      title: l10n.navDocuments,
      body: Column(
        children: [
          if (categories.isNotEmpty)
            SizedBox(
              height: 48,
              child: ListView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 12),
                children: [
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: FilterChip(
                      label: Text(l10n.documentsFilterAll),
                      selected: _selectedCategory == null,
                      onSelected: (_) => setState(() => _selectedCategory = null),
                    ),
                  ),
                  ...categories.map(
                    (c) => Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: FilterChip(
                        label: Text(c),
                        selected: _selectedCategory == c,
                        onSelected: (_) =>
                            setState(() => _selectedCategory = c),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          Expanded(
            child: _state.status == DocumentsLoadStatus.loading
                ? DsLoadingState(message: l10n.genericLoading)
                : _state.status == DocumentsLoadStatus.error
                    ? DsErrorState(
                        title: l10n.genericLoadFailed,
                        message: EssFailureUi.fromStored(
                    l10n,
                    code: _state.errorCode,
                    message: _state.errorMessage,
                  ),
                        actionLabel: l10n.homeRetry,
                        onAction: _state.loadList,
                      )
                    : visible.isEmpty
                        ? DsEmptyState(title: l10n.documentsEmpty)
                        : RefreshIndicator(
                            onRefresh: _state.loadList,
                            child: ListView.builder(
                              padding:
                                  const EdgeInsets.only(top: 8, bottom: 32),
                              itemCount: visible.length,
                              itemBuilder: (context, i) {
                                final row = visible[i];
                                final id = (row['id'] ?? '').toString();
                                final title =
                                    (row['title'] ?? l10n.navDocuments)
                                        .toString();
                                final category =
                                    (row['category'] ?? '').toString();
                                return DsListItem(
                                  title: title,
                                  subtitle: category.isEmpty ? null : category,
                                  leading: const DsIconBadge(
                                    icon: Icons.folder_open_outlined,
                                    color: AppColors.auroraCyan,
                                  ),
                                  onTap: () => context.go(
                                    '${AppRoutes.documentDetail}?id=${Uri.encodeComponent(id)}',
                                  ),
                                );
                              },
                            ),
                          ),
          ),
        ],
      ),
    );
  }
}

/// Document detail + in-app preview/open (online only).
library;

import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/ess_failure_ui.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/features/documents/documents_state.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class DocumentDetailScreen extends StatefulWidget {
  const DocumentDetailScreen({super.key, required this.documentId});

  final String documentId;

  @override
  State<DocumentDetailScreen> createState() => _DocumentDetailScreenState();
}

class _DocumentDetailScreenState extends State<DocumentDetailScreen> {
  late final DocumentsState _state;

  @override
  void initState() {
    super.initState();
    _state = DocumentsState(repository: AppLocator.documentsRepository)
      ..addListener(_onChanged);
    _state.loadDetail(widget.documentId);
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
    final ok = await _state.openFile(widget.documentId);
    if (!mounted) return;
    if (!ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AppLocalizations.of(context).documentsNoFile)),
      );
      return;
    }
    final bytes = _state.previewBytes;
    if (bytes == null) return;
    final mime = (_state.previewContentType ?? '').toLowerCase();
    await showDialog<void>(
      context: context,
      builder: (ctx) {
        final l10n = AppLocalizations.of(ctx);
        Widget body;
        if (mime.startsWith('image/')) {
          body = Image.memory(Uint8List.fromList(bytes));
        } else if (mime.startsWith('text/') || mime.contains('json')) {
          body = SingleChildScrollView(
            child: Text(utf8.decode(bytes, allowMalformed: true)),
          );
        } else {
          body = Text(
            l10n.documentsFileLoaded(
              _state.previewFilename ?? widget.documentId,
              bytes.length,
            ),
          );
        }
        return AlertDialog(
          title: Text(l10n.documentsOpen),
          content: SizedBox(width: double.maxFinite, child: body),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(ctx).pop(),
              child: Text(l10n.genericClose),
            ),
          ],
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final row = _state.detail;
    final hasFile = (row['file_url'] ?? '').toString().isNotEmpty;
    return DsPageScaffold(
      title: l10n.documentDetailTitle,
      body: _state.status == DocumentsLoadStatus.loading
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
                  onAction: () => _state.loadDetail(widget.documentId),
                )
              : ListView(
                  padding: const EdgeInsets.only(bottom: 32),
                  children: [
                    DsSectionHeader(title: l10n.documentDetailTitle),
                    DsCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            (row['title'] ?? '-').toString(),
                            style: Theme.of(context).textTheme.titleMedium,
                          ),
                          const SizedBox(height: AppSpacing.sm),
                          Text('${l10n.documentsCategory}: ${row['category'] ?? '-'}'),
                          Text(
                            '${l10n.documentsFileName}: ${row['file_name'] ?? '-'}',
                          ),
                          Text(
                            '${l10n.documentsUploadedAt}: ${row['uploaded_at'] ?? '-'}',
                          ),
                          if (hasFile) ...[
                            const SizedBox(height: AppSpacing.md),
                            FilledButton.icon(
                              onPressed: _state.opening ? null : _open,
                              icon: _state.opening
                                  ? const SizedBox(
                                      width: 16,
                                      height: 16,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                      ),
                                    )
                                  : const Icon(Icons.open_in_new),
                              label: Text(l10n.documentsOpen),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),
    );
  }
}

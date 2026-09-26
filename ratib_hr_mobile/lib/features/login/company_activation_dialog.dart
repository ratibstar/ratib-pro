/// Company code entry — links the shared HR build to the company's ERP server.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/activation/company_activation.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';

/// Returns true when the linked company changed (activated or unlinked).
Future<bool> showCompanyActivationDialog(BuildContext context) async {
  final changed = await showDialog<bool>(
    context: context,
    builder: (_) => const _CompanyActivationDialog(),
  );
  return changed ?? false;
}

class _CompanyActivationDialog extends StatefulWidget {
  const _CompanyActivationDialog();

  @override
  State<_CompanyActivationDialog> createState() =>
      _CompanyActivationDialogState();
}

class _CompanyActivationDialogState extends State<_CompanyActivationDialog> {
  final _code = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  String _messageFor(CompanyActivationError error, AppLocalizations l10n) {
    switch (error) {
      case CompanyActivationError.invalidCode:
        return l10n.activationInvalid;
      case CompanyActivationError.appNotEnabled:
        return l10n.activationAppDisabled;
      case CompanyActivationError.rateLimited:
        return l10n.activationRateLimited;
      case CompanyActivationError.network:
        return l10n.activationNetworkError;
    }
  }

  Future<void> _activate() async {
    final l10n = AppLocalizations.of(context);
    setState(() {
      _busy = true;
      _error = null;
    });
    final error = await CompanyActivation.activate(_code.text);
    if (!mounted) return;
    if (error == null) {
      Navigator.of(context).pop(true);
      return;
    }
    setState(() {
      _busy = false;
      _error = _messageFor(error, l10n);
    });
  }

  Future<void> _unlink() async {
    setState(() => _busy = true);
    await CompanyActivation.clear();
    if (!mounted) return;
    Navigator.of(context).pop(true);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return AlertDialog(
      title: Text(l10n.activationTitle),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (CompanyActivation.isActive &&
              (CompanyActivation.companyName ?? '').isNotEmpty) ...[
            Text(
              CompanyActivation.companyName!,
              style: Theme.of(context)
                  .textTheme
                  .titleSmall
                  ?.copyWith(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 12),
          ],
          TextField(
            controller: _code,
            enabled: !_busy,
            autofocus: true,
            textCapitalization: TextCapitalization.characters,
            textDirection: TextDirection.ltr,
            decoration: InputDecoration(
              labelText: l10n.activationCodeLabel,
              hintText: 'ABCD-2345',
              errorText: _error,
            ),
            onSubmitted: (_) => _busy ? null : _activate(),
          ),
          const SizedBox(height: 8),
          Text(
            l10n.activationHint,
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
      actions: [
        if (CompanyActivation.isActive)
          TextButton(
            onPressed: _busy ? null : _unlink,
            child: Text(l10n.activationRemove),
          ),
        TextButton(
          onPressed: _busy ? null : () => Navigator.of(context).pop(false),
          child: Text(l10n.activationCancel),
        ),
        FilledButton(
          onPressed: _busy ? null : _activate,
          child: _busy
              ? const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : Text(l10n.activationSubmit),
        ),
      ],
    );
  }
}

/// Snackbar helpers.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';

abstract final class DsSnackbar {
  static void show(
    BuildContext context, {
    required String message,
    DsSnackbarKind kind = DsSnackbarKind.neutral,
  }) {
    final scheme = Theme.of(context).colorScheme;
    Color? bg;
    switch (kind) {
      case DsSnackbarKind.success:
        bg = AppColors.success;
      case DsSnackbarKind.error:
        bg = scheme.error;
      case DsSnackbarKind.neutral:
        bg = null;
    }
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: bg,
        duration: const Duration(seconds: 3),
      ),
    );
  }
}

enum DsSnackbarKind { neutral, success, error }

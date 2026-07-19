/// Text / Search / Date fields — presentation only.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';

class DsTextField extends StatelessWidget {
  const DsTextField({
    super.key,
    this.controller,
    this.label,
    this.hint,
    this.obscureText = false,
    this.keyboardType,
    this.textInputAction,
    this.onChanged,
    this.enabled = true,
    this.prefixIcon,
    this.suffixIcon,
    this.maxLines = 1,
  });

  final TextEditingController? controller;
  final String? label;
  final String? hint;
  final bool obscureText;
  final TextInputType? keyboardType;
  final TextInputAction? textInputAction;
  final ValueChanged<String>? onChanged;
  final bool enabled;
  final IconData? prefixIcon;
  final Widget? suffixIcon;
  final int maxLines;

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      obscureText: obscureText,
      keyboardType: keyboardType,
      textInputAction: textInputAction,
      onChanged: onChanged,
      enabled: enabled,
      maxLines: maxLines,
      style: Theme.of(context).textTheme.bodyLarge,
      decoration: InputDecoration(
        labelText: label,
        hintText: hint,
        prefixIcon: prefixIcon == null ? null : Icon(prefixIcon),
        suffixIcon: suffixIcon,
      ),
    );
  }
}

class DsSearchField extends StatelessWidget {
  const DsSearchField({
    super.key,
    this.controller,
    this.hint,
    this.onChanged,
    this.onClear,
  });

  final TextEditingController? controller;
  final String? hint;
  final ValueChanged<String>? onChanged;
  final VoidCallback? onClear;

  @override
  Widget build(BuildContext context) {
    return DsTextField(
      controller: controller,
      hint: hint,
      onChanged: onChanged,
      prefixIcon: AppIcons.search,
      textInputAction: TextInputAction.search,
      suffixIcon: onClear == null
          ? null
          : IconButton(
              tooltip: MaterialLocalizations.of(context).deleteButtonTooltip,
              onPressed: onClear,
              icon: const Icon(AppIcons.close),
            ),
    );
  }
}

class DsDatePickerField extends StatelessWidget {
  const DsDatePickerField({
    super.key,
    required this.label,
    this.valueText,
    this.hint,
    this.onPick,
  });

  final String label;
  final String? valueText;
  final String? hint;
  final VoidCallback? onPick;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onPick,
      borderRadius: BorderRadius.circular(AppRadius.field),
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: label,
          hintText: hint,
          suffixIcon: const Icon(AppIcons.calendar),
        ),
        child: Text(
          valueText ?? hint ?? '',
          style: Theme.of(context).textTheme.bodyLarge,
        ),
      ),
    );
  }

  /// Helper: shows Material date picker (no business rules).
  static Future<DateTime?> showPicker(
    BuildContext context, {
    DateTime? initialDate,
    DateTime? firstDate,
    DateTime? lastDate,
  }) {
    final now = DateTime.now();
    return showDatePicker(
      context: context,
      initialDate: initialDate ?? now,
      firstDate: firstDate ?? DateTime(now.year - 5),
      lastDate: lastDate ?? DateTime(now.year + 5),
    );
  }
}

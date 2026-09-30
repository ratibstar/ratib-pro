/// Second-chance activation after Flutter engine is up (MethodChannel + app_links).
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/activation/activation_bootstrap.dart';
import 'package:ratib_hr_mobile/core/activation/company_activation.dart';

class ActivationStartupGate extends StatefulWidget {
  const ActivationStartupGate({super.key, required this.child});

  final Widget child;

  @override
  State<ActivationStartupGate> createState() => _ActivationStartupGateState();
}

class _ActivationStartupGateState extends State<ActivationStartupGate> {
  bool _done = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _secondPass());
  }

  Future<void> _secondPass() async {
    try {
      final uri = await ActivationBootstrap.resolveLaunchUri();
      if (uri != null) {
        await ActivationBootstrap.applyLaunchUri(uri);
        await CompanyActivation.purgeWrongTenantForUnifiedPackage();
      }
    } catch (_) {}
    if (mounted) setState(() => _done = true);
  }

  @override
  Widget build(BuildContext context) {
    if (!_done) {
      return const ColoredBox(
        color: Color(0xFF0F172A),
        child: Center(
          child: CircularProgressIndicator(color: Color(0xFF14B8A6)),
        ),
      );
    }
    return widget.child;
  }
}

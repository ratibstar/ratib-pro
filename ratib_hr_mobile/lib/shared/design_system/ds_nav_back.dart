/// Back navigation for ESS shell routes (go_router often has no pop stack).
library;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

const _tabRoots = <String>{
  '/home',
  '/attendance',
  '/leave',
  '/requests',
  '/more',
};

String? dsParentShellPath(String path) {
  if (_tabRoots.contains(path)) return null;
  if (path.startsWith('/more/')) return '/more';
  if (path.startsWith('/leave/')) return '/leave';
  if (path.startsWith('/attendance/')) return '/attendance';
  if (path.startsWith('/requests/')) return '/requests';
  return null;
}

bool dsShouldShowBackButton(BuildContext context) {
  if (context.canPop()) return true;
  final path = GoRouterState.of(context).uri.path;
  return dsParentShellPath(path) != null;
}

void dsNavigateBack(BuildContext context) {
  if (context.canPop()) {
    context.pop();
    return;
  }
  final parent = dsParentShellPath(GoRouterState.of(context).uri.path);
  if (parent != null) {
    context.go(parent);
  }
}

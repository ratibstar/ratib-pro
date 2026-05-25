import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../features/agency/screens/agency_dashboard.dart';
import '../../features/auth/providers/auth_provider.dart';
import '../../features/auth/screens/login_screen.dart';
import '../../features/company/screens/company_dashboard.dart';
import '../../features/worker/screens/worker_dashboard.dart';
import '../models/user_role.dart';

class AppRouter {
  AppRouter(this.authProvider);

  final AuthProvider authProvider;

  static const login = '/login';
  static const workerHome = '/worker';
  static const companyHome = '/company';
  static const agencyHome = '/agency';

  late final GoRouter router = GoRouter(
    initialLocation: login,
    refreshListenable: authProvider,
    errorBuilder: (context, state) => Scaffold(
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Text(
            state.error?.toString() ?? 'Something went wrong',
            textAlign: TextAlign.center,
          ),
        ),
      ),
    ),
    redirect: (context, state) {
      final status = authProvider.status;
      final location = state.matchedLocation;
      final isLogin = location == login;

      if (status == AuthStatus.unauthenticated && !isLogin) {
        return login;
      }

      if (status == AuthStatus.authenticated) {
        if (isLogin) {
          return _homeForRole(authProvider.role);
        }
        final role = authProvider.role;
        if (role == UserRole.worker && !location.startsWith('/worker')) {
          return workerHome;
        }
        if (role == UserRole.company && !location.startsWith('/company')) {
          return companyHome;
        }
        if (role == UserRole.agency && !location.startsWith('/agency')) {
          return agencyHome;
        }
      }

      return null;
    },
    routes: [
      GoRoute(
        path: login,
        builder: (context, state) => const LoginScreen(),
      ),
      GoRoute(
        path: workerHome,
        builder: (context, state) => const WorkerDashboard(),
        routes: [
          GoRoute(
            path: 'profile',
            builder: (context, state) =>
                const WorkerDashboard(initialIndex: 1),
          ),
          GoRoute(
            path: 'tasks',
            builder: (context, state) =>
                const WorkerDashboard(initialIndex: 2),
          ),
        ],
      ),
      GoRoute(
        path: companyHome,
        builder: (context, state) => const CompanyDashboard(),
        routes: [
          GoRoute(
            path: 'workers',
            builder: (context, state) =>
                const CompanyDashboard(initialIndex: 1),
          ),
          GoRoute(
            path: 'requests',
            builder: (context, state) =>
                const CompanyDashboard(initialIndex: 2),
          ),
        ],
      ),
      GoRoute(
        path: agencyHome,
        builder: (context, state) => const AgencyDashboard(),
        routes: [
          GoRoute(
            path: 'pipeline',
            builder: (context, state) =>
                const AgencyDashboard(initialIndex: 1),
          ),
          GoRoute(
            path: 'assignments',
            builder: (context, state) =>
                const AgencyDashboard(initialIndex: 2),
          ),
        ],
      ),
    ],
  );

  static String _homeForRole(UserRole? role) {
    switch (role) {
      case UserRole.worker:
        return workerHome;
      case UserRole.company:
        return companyHome;
      case UserRole.agency:
        return agencyHome;
      case null:
        return login;
    }
  }
}

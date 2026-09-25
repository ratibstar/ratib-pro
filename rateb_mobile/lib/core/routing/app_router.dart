import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../features/agency/screens/agency_home_tab.dart';
import '../../features/agency/screens/assignments.dart';
import '../../features/agency/screens/recruitment_pipeline.dart';
import '../../features/auth/providers/auth_provider.dart';
import '../../features/auth/screens/login_screen.dart';
import '../../features/company/screens/company_home_tab.dart';
import '../../features/company/screens/requests.dart';
import '../../features/company/screens/workers_management.dart';
import '../../features/worker/screens/worker_home_tab.dart';
import '../../features/worker/screens/worker_profile.dart';
import '../../features/worker/screens/worker_tasks.dart';
import '../models/user_role.dart';
import '../../l10n/app_localizations.dart';
import '../../shared/widgets/portal_shell.dart';

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
            state.error?.toString() ??
                AppLocalizations.of(context).somethingWentWrong,
            textAlign: TextAlign.center,
          ),
        ),
      ),
    ),
    redirect: (context, state) {
      final status = authProvider.status;
      final location = state.matchedLocation;
      final isLogin = location == login;

      if (status == AuthStatus.unknown) {
        if (!isLogin) return login;
        return null;
      }

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
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) {
          final l10n = AppLocalizations.of(context);
          return PortalShell(
            title: l10n.workerPortal,
            navigationShell: navigationShell,
            destinations: [
              NavigationDestination(
                icon: const Icon(Icons.dashboard_outlined),
                selectedIcon: const Icon(Icons.dashboard),
                label: l10n.navDashboard,
              ),
              NavigationDestination(
                icon: const Icon(Icons.person_outline),
                selectedIcon: const Icon(Icons.person),
                label: l10n.navProfile,
              ),
              NavigationDestination(
                icon: const Icon(Icons.task_alt_outlined),
                selectedIcon: const Icon(Icons.task_alt),
                label: l10n.navTasks,
              ),
            ],
          );
        },
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: workerHome,
                builder: (context, state) {
                  final auth = context.watch<AuthProvider>();
                  return WorkerHomeTab(
                    username: auth.username ??
                        AppLocalizations.of(context).roleName(UserRole.worker),
                  );
                },
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '$workerHome/profile',
                builder: (context, state) => const WorkerProfile(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '$workerHome/tasks',
                builder: (context, state) => const WorkerTasks(),
              ),
            ],
          ),
        ],
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) {
          final l10n = AppLocalizations.of(context);
          return PortalShell(
            title: l10n.companyPortal,
            navigationShell: navigationShell,
            destinations: [
              NavigationDestination(
                icon: const Icon(Icons.dashboard_outlined),
                selectedIcon: const Icon(Icons.dashboard),
                label: l10n.navDashboard,
              ),
              NavigationDestination(
                icon: const Icon(Icons.groups_outlined),
                selectedIcon: const Icon(Icons.groups),
                label: l10n.navWorkers,
              ),
              NavigationDestination(
                icon: const Icon(Icons.inbox_outlined),
                selectedIcon: const Icon(Icons.inbox),
                label: l10n.navRequests,
              ),
            ],
          );
        },
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: companyHome,
                builder: (context, state) {
                  final auth = context.watch<AuthProvider>();
                  return CompanyHomeTab(
                    username: auth.username ??
                        AppLocalizations.of(context).roleName(UserRole.company),
                  );
                },
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '$companyHome/workers',
                builder: (context, state) => const WorkersManagement(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '$companyHome/requests',
                builder: (context, state) => const Requests(),
              ),
            ],
          ),
        ],
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) {
          final l10n = AppLocalizations.of(context);
          return PortalShell(
            title: l10n.agencyPortal,
            navigationShell: navigationShell,
            destinations: [
              NavigationDestination(
                icon: const Icon(Icons.dashboard_outlined),
                selectedIcon: const Icon(Icons.dashboard),
                label: l10n.navDashboard,
              ),
              NavigationDestination(
                icon: const Icon(Icons.timeline_outlined),
                selectedIcon: const Icon(Icons.timeline),
                label: l10n.navPipeline,
              ),
              NavigationDestination(
                icon: const Icon(Icons.assignment_ind_outlined),
                selectedIcon: const Icon(Icons.assignment_ind),
                label: l10n.navAssignments,
              ),
            ],
          );
        },
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: agencyHome,
                builder: (context, state) {
                  final auth = context.watch<AuthProvider>();
                  return AgencyHomeTab(
                    username: auth.username ??
                        AppLocalizations.of(context).roleName(UserRole.agency),
                  );
                },
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '$agencyHome/pipeline',
                builder: (context, state) => const RecruitmentPipeline(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '$agencyHome/assignments',
                builder: (context, state) => const Assignments(),
              ),
            ],
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

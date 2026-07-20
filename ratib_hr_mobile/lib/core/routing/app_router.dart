/// go_router — auth gate + mobile config + feature-flagged routes.
library;

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/identity/employee_context.dart';
import 'package:ratib_hr_mobile/core/routing/app_routes.dart';
import 'package:ratib_hr_mobile/core/shell/shell_nav_policy.dart';
import 'package:ratib_hr_mobile/features/attendance/attendance_history_screen.dart';
import 'package:ratib_hr_mobile/features/attendance/attendance_screen.dart';
import 'package:ratib_hr_mobile/features/documents/document_detail_screen.dart';
import 'package:ratib_hr_mobile/features/documents/documents_list_screen.dart';
import 'package:ratib_hr_mobile/features/home/home_page.dart';
import 'package:ratib_hr_mobile/features/inquiries/inquiries_page.dart';
import 'package:ratib_hr_mobile/features/leave/leave_apply_screen.dart';
import 'package:ratib_hr_mobile/features/leave/leave_balances_screen.dart';
import 'package:ratib_hr_mobile/features/leave/leave_detail_screen.dart';
import 'package:ratib_hr_mobile/features/leave/leave_requests_screen.dart';
import 'package:ratib_hr_mobile/features/login/auth_session.dart';
import 'package:ratib_hr_mobile/features/login/login_page.dart';
import 'package:ratib_hr_mobile/features/more/more_page.dart';
import 'package:ratib_hr_mobile/features/notifications/notifications_page.dart';
import 'package:ratib_hr_mobile/features/payments/payments_page.dart';
import 'package:ratib_hr_mobile/features/payslips/payslip_detail_screen.dart';
import 'package:ratib_hr_mobile/features/payslips/payslips_list_screen.dart';
import 'package:ratib_hr_mobile/features/profile/profile_screen.dart';
import 'package:ratib_hr_mobile/features/ratings/ratings_page.dart';
import 'package:ratib_hr_mobile/features/requests/employee_requests_page.dart';
import 'package:ratib_hr_mobile/features/requests/request_detail_page.dart';
import 'package:ratib_hr_mobile/features/settings/settings_page.dart';
import 'package:ratib_hr_mobile/shared/widgets/ess_shell.dart';
import 'package:ratib_hr_mobile/shared/widgets/phase0_placeholder_page.dart';

typedef LocaleChanged = void Function(Locale locale);

abstract final class AppRouter {
  static GoRouter router({
    required AuthSession session,
    required LocaleChanged onLocaleChanged,
  }) {
    final mobile = AppLocator.mobileConfiguration;
    return GoRouter(
      initialLocation: AppRoutes.login,
      refreshListenable: Listenable.merge([session, mobile]),
      redirect: (context, state) {
        final loc = state.matchedLocation;
        final onLogin = loc == AppRoutes.login;

        if (session.status == AuthStatus.unknown) {
          return onLogin ? null : AppRoutes.login;
        }
        if (session.status == AuthStatus.signedOut && !onLogin) {
          return AppRoutes.login;
        }
        if (session.status == AuthStatus.signedIn && onLogin) {
          return AppRoutes.home;
        }
        if (session.status == AuthStatus.signedIn &&
            !EmployeeContext.isResolved &&
            !onLogin) {
          return AppRoutes.login;
        }
        if (session.status == AuthStatus.signedIn && !onLogin) {
          final cfg = mobile.current;
          if (cfg == null || !cfg.mobileActive) {
            return AppRoutes.login;
          }
          if (!ShellNavPolicy.isRouteAllowed(cfg, loc)) {
            return AppRoutes.home;
          }
        }
        return null;
      },
      routes: [
        GoRoute(
          path: AppRoutes.login,
          builder: (context, state) => LoginPage(
            session: session,
            onLocaleChanged: onLocaleChanged,
            onSignedIn: () => context.go(AppRoutes.home),
          ),
        ),
        StatefulShellRoute.indexedStack(
          builder: (context, state, navigationShell) {
            return EssShell(
              navigationShell: navigationShell,
              onLocaleChanged: onLocaleChanged,
            );
          },
          branches: [
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: AppRoutes.home,
                  builder: (context, state) => const HomePage(),
                ),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: AppRoutes.attendance,
                  builder: (context, state) => const AttendanceScreen(),
                  routes: [
                    GoRoute(
                      path: 'check-in',
                      builder: (context, state) => const AttendanceScreen(),
                    ),
                    GoRoute(
                      path: 'check-out',
                      builder: (context, state) => const AttendanceScreen(),
                    ),
                    GoRoute(
                      path: 'history',
                      builder: (context, state) =>
                          const AttendanceHistoryScreen(),
                    ),
                  ],
                ),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: AppRoutes.leave,
                  builder: (context, state) => const LeaveBalancesScreen(),
                  routes: [
                    GoRoute(
                      path: 'balance',
                      builder: (context, state) =>
                          const LeaveBalancesScreen(),
                    ),
                    GoRoute(
                      path: 'apply',
                      builder: (context, state) => const LeaveApplyScreen(),
                    ),
                    GoRoute(
                      path: 'status',
                      builder: (context, state) =>
                          const LeaveRequestsScreen(),
                    ),
                    GoRoute(
                      path: 'detail',
                      builder: (context, state) {
                        final id = state.uri.queryParameters['id'] ?? '';
                        return LeaveDetailScreen(requestId: id);
                      },
                    ),
                  ],
                ),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: AppRoutes.requests,
                  builder: (context, state) => const Phase0PlaceholderPage(
                    titleKey: Phase0TitleKey.requests,
                    childLinks: [
                      Phase0Link(
                        route: AppRoutes.permissionRequests,
                        titleKey: Phase0TitleKey.permissionRequests,
                      ),
                      Phase0Link(
                        route: AppRoutes.employeeRequests,
                        titleKey: Phase0TitleKey.employeeRequests,
                      ),
                    ],
                  ),
                  routes: [
                    GoRoute(
                      path: 'permissions',
                      builder: (context, state) => const Phase0PlaceholderPage(
                        titleKey: Phase0TitleKey.permissionRequests,
                      ),
                    ),
                    GoRoute(
                      path: 'employee',
                      builder: (context, state) =>
                          const EmployeeRequestsPage(),
                    ),
                    GoRoute(
                      path: 'detail',
                      builder: (context, state) {
                        final id = state.uri.queryParameters['id'] ?? '';
                        return RequestDetailPage(requestId: id);
                      },
                    ),
                  ],
                ),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: AppRoutes.more,
                  builder: (context, state) => const MorePage(),
                  routes: [
                    GoRoute(
                      path: 'documents',
                      builder: (context, state) => const DocumentsListScreen(),
                      routes: [
                        GoRoute(
                          path: 'detail',
                          builder: (context, state) {
                            final id = state.uri.queryParameters['id'] ?? '';
                            return DocumentDetailScreen(documentId: id);
                          },
                        ),
                      ],
                    ),
                    GoRoute(
                      path: 'payslips',
                      builder: (context, state) => const PayslipsListScreen(),
                      routes: [
                        GoRoute(
                          path: 'detail',
                          builder: (context, state) {
                            final id = state.uri.queryParameters['id'] ?? '';
                            return PayslipDetailScreen(payslipId: id);
                          },
                        ),
                      ],
                    ),
                    GoRoute(
                      path: 'notifications',
                      builder: (context, state) => const NotificationsPage(),
                    ),
                    GoRoute(
                      path: 'ratings',
                      builder: (context, state) => const RatingsPage(),
                    ),
                    GoRoute(
                      path: 'inquiries',
                      builder: (context, state) => const InquiriesPage(),
                    ),
                    GoRoute(
                      path: 'payments',
                      builder: (context, state) => const PaymentsPage(),
                    ),
                    GoRoute(
                      path: 'settings',
                      builder: (context, state) => SettingsPage(
                        onLocaleChanged: onLocaleChanged,
                      ),
                    ),
                    GoRoute(
                      path: 'profile',
                      builder: (context, state) => const ProfileScreen(),
                    ),
                    GoRoute(
                      path: 'approvals',
                      builder: (context, state) => const Phase0PlaceholderPage(
                        titleKey: Phase0TitleKey.approvals,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ],
        ),
      ],
    );
  }
}

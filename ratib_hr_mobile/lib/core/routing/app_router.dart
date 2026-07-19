/// go_router skeleton — 5-tab ESS shell + login.
///
/// Phase 0: placeholder destinations only. No auth gate. No ERP calls.
library;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/routing/app_routes.dart';
import 'package:ratib_hr_mobile/features/login/login_placeholder_page.dart';
import 'package:ratib_hr_mobile/shared/widgets/ess_shell.dart';
import 'package:ratib_hr_mobile/shared/widgets/phase0_placeholder_page.dart';

typedef LocaleChanged = void Function(Locale locale);

abstract final class AppRouter {
  static GoRouter router({
    required LocaleChanged onLocaleChanged,
    required Locale currentLocale,
  }) {
    return GoRouter(
      initialLocation: AppRoutes.login,
      routes: [
        GoRoute(
          path: AppRoutes.login,
          builder: (context, state) => LoginPlaceholderPage(
            onContinue: () => context.go(AppRoutes.home),
            onLocaleChanged: onLocaleChanged,
            currentLocale: currentLocale,
          ),
        ),
        StatefulShellRoute.indexedStack(
          builder: (context, state, navigationShell) {
            return EssShell(
              navigationShell: navigationShell,
              onLocaleChanged: onLocaleChanged,
              currentLocale: currentLocale,
            );
          },
          branches: [
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: AppRoutes.home,
                  builder: (context, state) => const Phase0PlaceholderPage(
                    titleKey: Phase0TitleKey.home,
                  ),
                ),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: AppRoutes.attendance,
                  builder: (context, state) => const Phase0PlaceholderPage(
                    titleKey: Phase0TitleKey.attendance,
                    childLinks: [
                      Phase0Link(
                        route: AppRoutes.attendanceCheckIn,
                        titleKey: Phase0TitleKey.checkIn,
                      ),
                      Phase0Link(
                        route: AppRoutes.attendanceCheckOut,
                        titleKey: Phase0TitleKey.checkOut,
                      ),
                      Phase0Link(
                        route: AppRoutes.attendanceHistory,
                        titleKey: Phase0TitleKey.attendanceHistory,
                      ),
                    ],
                  ),
                  routes: [
                    GoRoute(
                      path: 'check-in',
                      builder: (context, state) => const Phase0PlaceholderPage(
                        titleKey: Phase0TitleKey.checkIn,
                      ),
                    ),
                    GoRoute(
                      path: 'check-out',
                      builder: (context, state) => const Phase0PlaceholderPage(
                        titleKey: Phase0TitleKey.checkOut,
                      ),
                    ),
                    GoRoute(
                      path: 'history',
                      builder: (context, state) => const Phase0PlaceholderPage(
                        titleKey: Phase0TitleKey.attendanceHistory,
                      ),
                    ),
                  ],
                ),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: AppRoutes.leave,
                  builder: (context, state) => const Phase0PlaceholderPage(
                    titleKey: Phase0TitleKey.leave,
                    childLinks: [
                      Phase0Link(
                        route: AppRoutes.leaveBalance,
                        titleKey: Phase0TitleKey.leaveBalance,
                      ),
                      Phase0Link(
                        route: AppRoutes.leaveApply,
                        titleKey: Phase0TitleKey.applyLeave,
                      ),
                      Phase0Link(
                        route: AppRoutes.leaveStatus,
                        titleKey: Phase0TitleKey.leaveStatus,
                      ),
                    ],
                  ),
                  routes: [
                    GoRoute(
                      path: 'balance',
                      builder: (context, state) => const Phase0PlaceholderPage(
                        titleKey: Phase0TitleKey.leaveBalance,
                      ),
                    ),
                    GoRoute(
                      path: 'apply',
                      builder: (context, state) => const Phase0PlaceholderPage(
                        titleKey: Phase0TitleKey.applyLeave,
                      ),
                    ),
                    GoRoute(
                      path: 'status',
                      builder: (context, state) => const Phase0PlaceholderPage(
                        titleKey: Phase0TitleKey.leaveStatus,
                      ),
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
                      builder: (context, state) => const Phase0PlaceholderPage(
                        titleKey: Phase0TitleKey.employeeRequests,
                      ),
                    ),
                  ],
                ),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: AppRoutes.more,
                  builder: (context, state) => const Phase0PlaceholderPage(
                    titleKey: Phase0TitleKey.more,
                    childLinks: [
                      Phase0Link(
                        route: AppRoutes.documents,
                        titleKey: Phase0TitleKey.documents,
                      ),
                      Phase0Link(
                        route: AppRoutes.payslips,
                        titleKey: Phase0TitleKey.payslips,
                      ),
                      Phase0Link(
                        route: AppRoutes.notifications,
                        titleKey: Phase0TitleKey.notifications,
                      ),
                      Phase0Link(
                        route: AppRoutes.profile,
                        titleKey: Phase0TitleKey.profile,
                      ),
                      Phase0Link(
                        route: AppRoutes.approvals,
                        titleKey: Phase0TitleKey.approvals,
                      ),
                    ],
                  ),
                  routes: [
                    GoRoute(
                      path: 'documents',
                      builder: (context, state) => const Phase0PlaceholderPage(
                        titleKey: Phase0TitleKey.documents,
                      ),
                    ),
                    GoRoute(
                      path: 'payslips',
                      builder: (context, state) => const Phase0PlaceholderPage(
                        titleKey: Phase0TitleKey.payslips,
                      ),
                    ),
                    GoRoute(
                      path: 'notifications',
                      builder: (context, state) => const Phase0PlaceholderPage(
                        titleKey: Phase0TitleKey.notifications,
                      ),
                    ),
                    GoRoute(
                      path: 'profile',
                      builder: (context, state) => const Phase0PlaceholderPage(
                        titleKey: Phase0TitleKey.profile,
                      ),
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

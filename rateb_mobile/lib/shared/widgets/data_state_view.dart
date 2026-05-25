import 'package:flutter/material.dart';

import 'empty_state.dart';
import 'offline_banner.dart';
import 'skeleton_loader.dart';

/// Role-aware empty state copy.
class EmptyStateCopy {
  EmptyStateCopy._();

  static const workerTasksTitle = 'No tasks assigned yet';
  static const workerTasksMessage =
      'When your employer assigns work, tasks will appear here.';

  static const workerProfileTitle = 'Profile unavailable';
  static const workerProfileMessage =
      'We could not load your profile details right now.';

  static const companyWorkersTitle = 'No workers available';
  static const companyWorkersMessage =
      'Your workforce roster is empty. Workers will appear once added to RATEB.';

  static const companyRequestsTitle = 'No requests yet';
  static const companyRequestsMessage =
      'Recruitment and case requests will show here when created.';

  static const agencyPipelineTitle = 'No pipeline data found';
  static const agencyPipelineMessage =
      'Candidates and deployments will appear as your agency processes recruitment.';

  static const agencyAssignmentsTitle = 'No assignments yet';
  static const agencyAssignmentsMessage =
      'Client assignments will appear when workers are deployed.';
}

/// Renders skeleton loading, error (with retry), empty, or content.
class DataStateView extends StatelessWidget {
  const DataStateView({
    super.key,
    required this.isLoading,
    required this.errorMessage,
    required this.onRetry,
    required this.isEmpty,
    required this.emptyTitle,
    required this.emptyMessage,
    required this.child,
    this.emptyIcon = Icons.inbox_outlined,
    this.skeletonType = SkeletonType.list,
    this.isFromCache = false,
    this.isAutoRetrying = false,
    this.autoRetryAttempt = 0,
    this.staleMessage,
  });

  final bool isLoading;
  final String? errorMessage;
  final VoidCallback? onRetry;
  final bool isEmpty;
  final String emptyTitle;
  final String emptyMessage;
  final IconData emptyIcon;
  final Widget child;
  final SkeletonType skeletonType;
  final bool isFromCache;
  final bool isAutoRetrying;
  final int autoRetryAttempt;
  final String? staleMessage;

  bool get _showSkeleton => isLoading && !isFromCache;
  bool get _showError => errorMessage != null && !isFromCache && !isLoading;

  @override
  Widget build(BuildContext context) {
    if (_showSkeleton) {
      return SkeletonLoader(type: skeletonType);
    }

    if (_showError) {
      return _ErrorBody(
        message: errorMessage!,
        onRetry: onRetry,
        isAutoRetrying: isAutoRetrying,
        autoRetryAttempt: autoRetryAttempt,
      );
    }

    if (isEmpty && !isLoading) {
      return EmptyState(
        title: emptyTitle,
        message: emptyMessage,
        icon: emptyIcon,
      );
    }

    final showTopBar = (isLoading && isFromCache) ||
        (staleMessage != null && staleMessage!.isNotEmpty);

    if (!showTopBar) {
      return child;
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (isLoading && isFromCache)
          const LinearProgressIndicator(minHeight: 2),
        if (staleMessage != null && staleMessage!.isNotEmpty)
          StaleDataBanner(message: staleMessage!, onRetry: onRetry),
        Expanded(child: child),
      ],
    );
  }
}

class _ErrorBody extends StatelessWidget {
  const _ErrorBody({
    required this.message,
    this.onRetry,
    this.isAutoRetrying = false,
    this.autoRetryAttempt = 0,
  });

  final String message;
  final VoidCallback? onRetry;
  final bool isAutoRetrying;
  final int autoRetryAttempt;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.error_outline,
              size: 48,
              color: Theme.of(context).colorScheme.error,
            ),
            const SizedBox(height: 16),
            Text(
              'Could not load data',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 8),
            Text(
              message,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: Theme.of(context)
                        .colorScheme
                        .onSurface
                        .withValues(alpha: 0.65),
                  ),
              textAlign: TextAlign.center,
            ),
            if (isAutoRetrying) ...[
              const SizedBox(height: 16),
              const SizedBox(
                width: 22,
                height: 22,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
              const SizedBox(height: 8),
              Text(
                'Retrying… (attempt $autoRetryAttempt/2)',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
            if (onRetry != null && !isAutoRetrying) ...[
              const SizedBox(height: 20),
              FilledButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh),
                label: const Text('Retry'),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

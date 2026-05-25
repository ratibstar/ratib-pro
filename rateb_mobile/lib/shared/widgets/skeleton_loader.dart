import 'package:flutter/material.dart';

enum SkeletonType { list, cards, profile, dashboard }

class SkeletonLoader extends StatefulWidget {
  const SkeletonLoader({
    super.key,
    required this.type,
  });

  final SkeletonType type;

  @override
  State<SkeletonLoader> createState() => _SkeletonLoaderState();
}

class _SkeletonLoaderState extends State<SkeletonLoader>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1100),
    )..repeat(reverse: true);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, child) {
        return Opacity(
          opacity: 0.45 + (_controller.value * 0.55),
          child: child,
        );
      },
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: switch (widget.type) {
          SkeletonType.list => _ListSkeleton(),
          SkeletonType.cards => _CardsSkeleton(),
          SkeletonType.profile => _ProfileSkeleton(),
          SkeletonType.dashboard => _DashboardSkeleton(),
        },
      ),
    );
  }
}

class _SkeletonBox extends StatelessWidget {
  const _SkeletonBox({
    required this.height,
    this.width,
    this.radius = 10,
  });

  final double height;
  final double? width;
  final double radius;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width,
      height: height,
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(radius),
      ),
    );
  }
}

class _ListSkeleton extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const _SkeletonBox(height: 28, width: 160),
        const SizedBox(height: 16),
        for (var i = 0; i < 4; i++) ...[
          Row(
            children: [
              const _SkeletonBox(height: 44, width: 44, radius: 22),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: const [
                    _SkeletonBox(height: 14),
                    SizedBox(height: 8),
                    _SkeletonBox(height: 12, width: 180),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
        ],
      ],
    );
  }
}

class _CardsSkeleton extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const _SkeletonBox(height: 28, width: 180),
        const SizedBox(height: 16),
        for (var i = 0; i < 3; i++) ...[
          const _SkeletonBox(height: 72),
          const SizedBox(height: 12),
        ],
      ],
    );
  }
}

class _ProfileSkeleton extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: const [
            _SkeletonBox(height: 56, width: 56, radius: 28),
            SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _SkeletonBox(height: 18),
                  SizedBox(height: 8),
                  _SkeletonBox(height: 14, width: 120),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 24),
        for (var i = 0; i < 5; i++) ...[
          const _SkeletonBox(height: 16),
          const SizedBox(height: 12),
        ],
      ],
    );
  }
}

class _DashboardSkeleton extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const _SkeletonBox(height: 32, width: 220),
        const SizedBox(height: 8),
        const _SkeletonBox(height: 16, width: 280),
        const SizedBox(height: 24),
        for (var i = 0; i < 3; i++) ...[
          const _SkeletonBox(height: 84),
          const SizedBox(height: 12),
        ],
      ],
    );
  }
}

import 'dart:math' as math;

import 'package:flutter/material.dart';

const compactBreakpoint = 600.0;
const expandedBreakpoint = 1024.0;

enum AdaptivePageFamily { reading, form, operational }

class AdaptiveScaffold extends StatelessWidget {
  const AdaptiveScaffold({
    super.key,
    required this.family,
    this.appBar,
    this.body,
    this.floatingActionButton,
  });

  final AdaptivePageFamily family;
  final PreferredSizeWidget? appBar;
  final Widget? body;
  final Widget? floatingActionButton;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: appBar,
      floatingActionButton: floatingActionButton,
      body: body == null ? null : AdaptivePage(family: family, child: body!),
    );
  }
}

class AdaptivePage extends StatelessWidget {
  const AdaptivePage({
    super.key,
    required this.child,
    this.family = AdaptivePageFamily.operational,
    this.padding = const EdgeInsets.symmetric(horizontal: 16, vertical: 24),
  });

  final Widget child;
  final AdaptivePageFamily family;
  final EdgeInsetsGeometry padding;

  double get _maxWidth => switch (family) {
    AdaptivePageFamily.reading => 736,
    AdaptivePageFamily.form => 896,
    AdaptivePageFamily.operational => 1400,
  };

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final availableWidth = constraints.maxWidth.isFinite
            ? constraints.maxWidth
            : MediaQuery.sizeOf(context).width;

        return Align(
          alignment: Alignment.topCenter,
          child: SizedBox(
            key: const ValueKey('adaptive-page-content'),
            width: math.min(availableWidth, _maxWidth),
            child: Padding(padding: padding, child: child),
          ),
        );
      },
    );
  }
}

class AdaptiveSplit extends StatelessWidget {
  const AdaptiveSplit({
    super.key,
    required this.main,
    required this.aside,
    this.gap = 24,
    this.mainFlex = 2,
    this.asideFlex = 1,
  });

  final Widget main;
  final Widget aside;
  final double gap;
  final int mainFlex;
  final int asideFlex;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        if (constraints.maxWidth < compactBreakpoint) {
          return Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              main,
              SizedBox(height: gap),
              aside,
            ],
          );
        }

        return Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(flex: mainFlex, child: main),
            SizedBox(width: gap),
            Expanded(flex: asideFlex, child: aside),
          ],
        );
      },
    );
  }
}

class AdaptiveMetricGrid extends StatelessWidget {
  const AdaptiveMetricGrid({
    super.key,
    required this.children,
    this.spacing = 16,
  });

  final List<Widget> children;
  final double spacing;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= expandedBreakpoint ? 4 : 2;
        final itemWidth = math.max(
          0.0,
          (constraints.maxWidth - (spacing * (columns - 1))) / columns,
        );

        return Wrap(
          spacing: spacing,
          runSpacing: spacing,
          children: [
            for (final child in children)
              SizedBox(width: itemWidth, child: child),
          ],
        );
      },
    );
  }
}

class AdaptiveActionBar extends StatelessWidget {
  const AdaptiveActionBar({
    super.key,
    required this.children,
    this.spacing = 12,
  });

  final List<Widget> children;
  final double spacing;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        if (constraints.maxWidth < compactBreakpoint) {
          return Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              for (var index = 0; index < children.length; index++) ...[
                if (index > 0) SizedBox(height: spacing),
                children[index],
              ],
            ],
          );
        }

        return Wrap(spacing: spacing, runSpacing: spacing, children: children);
      },
    );
  }
}

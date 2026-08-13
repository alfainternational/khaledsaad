import 'package:flutter/material.dart';

import '../../core/api/platform_repository.dart';
import '../../core/widgets/common.dart';
import '../projects/dashboard_screen.dart';
import 'learning_dashboard_screen.dart';
import 'experience_selection_screen.dart';
import 'learning_application_screen.dart';

class ExperienceShell extends StatefulWidget {
  const ExperienceShell({
    super.key,
    required this.repository,
    required this.onLogout,
    this.requestedExperience,
    this.requestedLearningApplication,
  });

  final PlatformRepository repository;
  final VoidCallback onLogout;
  final String? requestedExperience;
  final String? requestedLearningApplication;

  @override
  State<ExperienceShell> createState() => _ExperienceShellState();
}

class _ExperienceShellState extends State<ExperienceShell> {
  late Future<Map<String, dynamic>> _account = widget.repository.me();
  late String? _requestedLearningApplication =
      widget.requestedLearningApplication;

  @override
  void didUpdateWidget(covariant ExperienceShell oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.requestedLearningApplication !=
        oldWidget.requestedLearningApplication) {
      _requestedLearningApplication = widget.requestedLearningApplication;
    }
  }

  void _reload() => setState(() => _account = widget.repository.me());

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _account,
      builder: (context, snapshot) => AsyncView(
        snapshot: snapshot,
        onRetry: _reload,
        builder: (account) {
          final active = account['active_experience']?.toString();

          if (widget.requestedExperience != null &&
              widget.requestedExperience != active) {
            return ExperienceSelectionScreen(
              repository: widget.repository,
              account: account,
              requestedExperience: widget.requestedExperience,
              onChanged: _reload,
            );
          }

          if (active == null) {
            return ExperienceSelectionScreen(
              repository: widget.repository,
              account: account,
              onChanged: _reload,
            );
          }

          if (active == 'learning') {
            if (_requestedLearningApplication != null) {
              return LearningApplicationScreen(
                repository: widget.repository,
                exerciseKey: _requestedLearningApplication!,
                onExit: () =>
                    setState(() => _requestedLearningApplication = null),
              );
            }

            return LearningDashboardScreen(
              repository: widget.repository,
              account: account,
              onChanged: _reload,
              onLogout: widget.onLogout,
            );
          }

          return DashboardScreen(
            repository: widget.repository,
            onLogout: widget.onLogout,
            onExperienceChanged: _reload,
          );
        },
      ),
    );
  }
}

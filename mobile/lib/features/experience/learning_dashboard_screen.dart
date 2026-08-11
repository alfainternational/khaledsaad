import 'package:flutter/material.dart';

import '../../core/api/platform_repository.dart';
import '../../core/firebase/firebase_service.dart';
import '../../core/i18n/app_strings.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';
import 'experience_selection_screen.dart';
import 'learning_application_screen.dart';

class LearningDashboardScreen extends StatefulWidget {
  const LearningDashboardScreen({
    super.key,
    required this.repository,
    required this.account,
    required this.onChanged,
    required this.onLogout,
  });

  final PlatformRepository repository;
  final Map<String, dynamic> account;
  final VoidCallback onChanged;
  final VoidCallback onLogout;

  @override
  State<LearningDashboardScreen> createState() =>
      _LearningDashboardScreenState();
}

class _LearningDashboardScreenState extends State<LearningDashboardScreen> {
  late Future<Map<String, dynamic>> _future = widget.repository
      .marketingLearningOverview();

  void _reload() =>
      setState(() => _future = widget.repository.marketingLearningOverview());

  @override
  Widget build(BuildContext context) {
    final strings = AppStrings.of(context);

    return AdaptiveScaffold(
      family: AdaptivePageFamily.operational,
      appBar: AppBar(
        title: Text(strings.text('my_path')),
        actions: [
          IconButton(
            tooltip: strings.text('change_path'),
            icon: const Icon(Icons.swap_horiz),
            onPressed: () async {
              await Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => ExperienceSelectionScreen(
                    repository: widget.repository,
                    account: widget.account,
                    onChanged: () => Navigator.of(context).pop(true),
                  ),
                ),
              );
              widget.onChanged();
            },
          ),
          IconButton(
            tooltip: strings.text('logout'),
            icon: const Icon(Icons.logout),
            onPressed: () async {
              await FirebaseService.instance.removeDevice(widget.repository);
              await widget.repository.logout();
              widget.onLogout();
            },
          ),
        ],
      ),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) => AsyncView(
          snapshot: snapshot,
          onRetry: _reload,
          builder: (data) {
            final progress = Map<String, dynamic>.from(
              data['progress'] as Map? ?? const {},
            );
            final next = data['next'] is Map
                ? Map<String, dynamic>.from(data['next'] as Map)
                : null;

            return ListView(
              padding: EdgeInsets.zero,
              children: [
                Text(
                  '${progress['completed'] ?? 0} / ${progress['total'] ?? 0}',
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                const SizedBox(height: 16),
                if (next != null)
                  BrandCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(strings.text('next_task')),
                        const SizedBox(height: 6),
                        Text(
                          next['title']?.toString() ?? '',
                          style: const TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(next['reason']?.toString() ?? ''),
                        const SizedBox(height: 8),
                        Text(
                          strings.text(
                            'duration_result',
                            values: {
                              'minutes': '${next['duration_minutes'] ?? 0}',
                              'result': next['deliverable']?.toString() ?? '',
                            },
                          ),
                        ),
                        const SizedBox(height: 14),
                        SizedBox(
                          height: 48,
                          child: FilledButton(
                            onPressed: () async {
                              await Navigator.of(context).push(
                                MaterialPageRoute(
                                  builder: (_) => LearningApplicationScreen(
                                    repository: widget.repository,
                                    exerciseKey: next['key'].toString(),
                                  ),
                                ),
                              );
                              _reload();
                            },
                            child: Text(strings.text('start_application')),
                          ),
                        ),
                      ],
                    ),
                  ),
              ],
            );
          },
        ),
      ),
    );
  }
}

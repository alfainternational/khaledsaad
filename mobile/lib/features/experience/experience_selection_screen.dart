import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/platform_repository.dart';
import '../../core/i18n/app_strings.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';

class ExperienceSelectionScreen extends StatefulWidget {
  const ExperienceSelectionScreen({
    super.key,
    required this.repository,
    required this.account,
    required this.onChanged,
    this.requestedExperience,
  });

  final PlatformRepository repository;
  final Map<String, dynamic> account;
  final VoidCallback onChanged;
  final String? requestedExperience;

  @override
  State<ExperienceSelectionScreen> createState() =>
      _ExperienceSelectionScreenState();
}

class _ExperienceSelectionScreenState extends State<ExperienceSelectionScreen> {
  bool _busy = false;
  String? _error;

  Future<void> _choose(String experience) async {
    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      final enabled =
          (widget.account['enabled_experiences'] as List? ?? const [])
              .map((value) => value.toString())
              .toSet();

      if (enabled.contains(experience)) {
        await widget.repository.switchExperience(experience);
      } else {
        await widget.repository.activateExperience(experience);
      }

      if (mounted) widget.onChanged();
    } on ApiException catch (exception) {
      if (mounted) setState(() => _error = exception.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final strings = AppStrings.of(context);
    final enabled = (widget.account['enabled_experiences'] as List? ?? const [])
        .map((value) => value.toString())
        .toSet();
    final showBusiness =
        widget.requestedExperience == null ||
        widget.requestedExperience == 'business';
    final showLearning =
        widget.requestedExperience == null ||
        widget.requestedExperience == 'learning';

    return AdaptiveScaffold(
      family: AdaptivePageFamily.form,
      appBar: AppBar(title: Text(strings.text('change_path'))),
      body: ListView(
        padding: EdgeInsets.zero,
        children: [
          Text(
            strings.text('experience_question'),
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 16),
          if (_error != null) ...[
            ErrorNotice(message: _error!),
            const SizedBox(height: 12),
          ],
          if (showBusiness)
            _ExperienceCard(
              title: strings.text('business_choice'),
              description: strings.text('business_description'),
              buttonLabel: strings.text(
                enabled.contains('business') ? 'switch' : 'activate',
              ),
              enabled: !_busy,
              onPressed: () => _choose('business'),
            ),
          if (showBusiness && showLearning) const SizedBox(height: 12),
          if (showLearning)
            _ExperienceCard(
              title: strings.text('learning_choice'),
              description: strings.text('learning_description'),
              buttonLabel: strings.text(
                enabled.contains('learning') ? 'switch' : 'activate',
              ),
              enabled: !_busy,
              onPressed: () => _choose('learning'),
            ),
        ],
      ),
    );
  }
}

class _ExperienceCard extends StatelessWidget {
  const _ExperienceCard({
    required this.title,
    required this.description,
    required this.buttonLabel,
    required this.enabled,
    required this.onPressed,
  });

  final String title;
  final String description;
  final String buttonLabel;
  final bool enabled;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return BrandCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
          const SizedBox(height: 6),
          Text(description),
          const SizedBox(height: 12),
          SizedBox(
            height: 48,
            child: FilledButton(
              onPressed: enabled ? onPressed : null,
              child: Text(buttonLabel),
            ),
          ),
        ],
      ),
    );
  }
}

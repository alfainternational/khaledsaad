import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/platform_repository.dart';
import '../../core/i18n/app_strings.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';

class LearningApplicationScreen extends StatefulWidget {
  const LearningApplicationScreen({
    super.key,
    required this.repository,
    required this.exerciseKey,
    this.onExit,
  });

  final PlatformRepository repository;
  final String exerciseKey;
  final VoidCallback? onExit;

  @override
  State<LearningApplicationScreen> createState() =>
      _LearningApplicationScreenState();
}

class _LearningApplicationScreenState extends State<LearningApplicationScreen> {
  final _formKey = GlobalKey<FormState>();
  final _answerController = TextEditingController();
  int _loadGeneration = 0;
  int _selectedProjectId = 0;
  late Future<Map<String, dynamic>> _future = _load();
  Map<String, dynamic>? _data;
  int _step = 0;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _loadGeneration++;
    _answerController.dispose();
    super.dispose();
  }

  Future<Map<String, dynamic>> _load() async {
    final generation = ++_loadGeneration;
    final data = await widget.repository.marketingLearningApplication(
      widget.exerciseKey,
      projectId: _selectedProjectId == 0 ? null : _selectedProjectId,
    );

    if (!mounted || generation != _loadGeneration) {
      return data;
    }

    _data = data;
    _selectedProjectId =
        ((data['project'] as Map?)?['id'] as num?)?.toInt() ?? 0;
    final questions = _questions(data);
    final answers = _answers(data);
    final firstMissing = questions.indexWhere(
      (question) => !answers.containsKey(question['key'].toString()),
    );
    _step = firstMissing < 0 ? questions.length - 1 : firstMissing;
    _syncAnswer();
    return data;
  }

  void _reload() => setState(() => _future = _load());

  List<Map<String, dynamic>> _questions(Map<String, dynamic> data) =>
      ((data['exercise'] as Map?)?['questions'] as List? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();

  Map<String, dynamic> _answers(Map<String, dynamic> data) =>
      Map<String, dynamic>.from(
        (data['attempt'] as Map?)?['answers'] as Map? ?? const {},
      );

  void _syncAnswer() {
    final data = _data;
    if (data == null) return;
    final questions = _questions(data);
    if (questions.isEmpty || _step >= questions.length) return;
    final key = questions[_step]['key'].toString();
    _answerController.text = _answers(data)[key]?.toString() ?? '';
  }

  void _moveTo(int step) {
    setState(() {
      _step = step;
      _error = null;
      _syncAnswer();
    });
  }

  Future<void> _save() async {
    if (!(_formKey.currentState?.validate() ?? false) || _data == null) return;
    final questions = _questions(_data!);
    final question = questions[_step];
    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      final saved = await widget.repository.saveMarketingLearningAnswer(
        widget.exerciseKey,
        question['key'].toString(),
        _answerController.text.trim(),
        projectId: _selectedProjectId == 0 ? null : _selectedProjectId,
      );
      _data!['attempt'] = saved['attempt'];
      if (_step < questions.length - 1) {
        _moveTo(_step + 1);
      } else {
        setState(() {});
      }
    } on ApiException catch (exception) {
      if (mounted) setState(() => _error = exception.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _review() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final reviewed = await widget.repository
          .reviewMarketingLearningApplication(
            widget.exerciseKey,
            projectId: _selectedProjectId == 0 ? null : _selectedProjectId,
          );
      _data!['attempt'] = reviewed['attempt'];
      if (mounted) setState(() {});
    } on ApiException catch (exception) {
      if (mounted) setState(() => _error = exception.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _exit() {
    if (widget.onExit != null) {
      widget.onExit!();
    } else {
      Navigator.of(context).maybePop();
    }
  }

  @override
  Widget build(BuildContext context) {
    final strings = AppStrings.of(context);

    return AdaptiveScaffold(
      family: AdaptivePageFamily.form,
      appBar: AppBar(
        leading: IconButton(
          tooltip: strings.text('back'),
          onPressed: _exit,
          icon: const Icon(Icons.arrow_back),
        ),
        title: Text(strings.text('my_path')),
      ),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) => AsyncView(
          snapshot: snapshot,
          onRetry: _reload,
          builder: (data) {
            final exercise = Map<String, dynamic>.from(data['exercise'] as Map);
            final attempt = Map<String, dynamic>.from(
              data['attempt'] as Map? ?? const {},
            );
            final questions = _questions(data);
            final projectChoices =
                (data['project_choices'] as List? ?? const [])
                    .map((item) => Map<String, dynamic>.from(item as Map))
                    .toList();
            final question = questions[_step];
            final status = attempt['status']?.toString() ?? 'draft';
            final underReview = status == 'queued' || status == 'evaluating';
            final completed = status == 'completed';
            final allAnswered = questions.every(
              (item) => _answers(data).containsKey(item['key'].toString()),
            );

            return ListView(
              padding: EdgeInsets.zero,
              children: [
                Text(
                  exercise['title']?.toString() ?? '',
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                const SizedBox(height: 8),
                Text(exercise['purpose']?.toString() ?? ''),
                const SizedBox(height: 16),
                if (projectChoices.isNotEmpty) ...[
                  BrandCard(
                    muted: true,
                    child: DropdownButtonFormField<int>(
                      key: const Key('learning-project-selector'),
                      initialValue: _selectedProjectId,
                      decoration: const InputDecoration(
                        labelText: 'اربط التطبيق بمشروع (اختياري)',
                      ),
                      items: [
                        const DropdownMenuItem<int>(
                          value: 0,
                          child: Text('بدون مشروع'),
                        ),
                        ...projectChoices.map(
                          (project) => DropdownMenuItem<int>(
                            value: (project['id'] as num).toInt(),
                            child: Text(project['name']?.toString() ?? ''),
                          ),
                        ),
                      ],
                      onChanged: _busy
                          ? null
                          : (value) {
                              if (value == null ||
                                  value == _selectedProjectId) {
                                return;
                              }

                              setState(() {
                                _selectedProjectId = value;
                                _future = _load();
                              });
                            },
                    ),
                  ),
                  const SizedBox(height: 16),
                ],
                BrandCard(
                  muted: true,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(strings.text('expected_result')),
                      const SizedBox(height: 4),
                      Text(exercise['deliverable']?.toString() ?? ''),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                if (_error != null) ...[
                  ErrorNotice(message: _error!),
                  const SizedBox(height: 12),
                ],
                if (underReview)
                  BrandCard(child: Text(strings.text('review_in_progress')))
                else if (completed)
                  BrandCard(child: Text(strings.text('application_completed')))
                else
                  Form(
                    key: _formKey,
                    child: BrandCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Text(
                            strings.text(
                              'question_progress',
                              values: {
                                'current': '${_step + 1}',
                                'total': '${questions.length}',
                              },
                            ),
                          ),
                          const SizedBox(height: 10),
                          Text(
                            question['label']?.toString() ?? '',
                            style: const TextStyle(
                              fontSize: 20,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text(question['help']?.toString() ?? ''),
                          const SizedBox(height: 6),
                          Text(
                            strings.text(
                              'answer_example',
                              values: {
                                'example':
                                    question['example']?.toString() ?? '',
                              },
                            ),
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                          const SizedBox(height: 14),
                          TextFormField(
                            controller: _answerController,
                            minLines: 4,
                            maxLines: 8,
                            enabled: !_busy,
                            validator: (value) {
                              final minimum =
                                  (question['min'] as num?)?.toInt() ?? 1;
                              return (value?.trim().length ?? 0) < minimum
                                  ? strings.text('answer_required')
                                  : null;
                            },
                          ),
                          const SizedBox(height: 14),
                          AdaptiveActionBar(
                            children: [
                              if (_step > 0)
                                OutlinedButton(
                                  onPressed: _busy
                                      ? null
                                      : () => _moveTo(_step - 1),
                                  child: Text(
                                    strings.text('previous_question'),
                                  ),
                                ),
                              FilledButton(
                                onPressed: _busy ? null : _save,
                                child: Text(
                                  strings.text(
                                    _step < questions.length - 1
                                        ? 'save_continue'
                                        : 'save_answer',
                                  ),
                                ),
                              ),
                              if (allAnswered)
                                FilledButton.tonal(
                                  onPressed: _busy ? null : _review,
                                  child: Text(strings.text('submit_review')),
                                ),
                            ],
                          ),
                        ],
                      ),
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

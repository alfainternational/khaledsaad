import 'package:flutter/material.dart';

import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';
import '../../core/widgets/worked_example.dart';
import 'models.dart';

/// يقابل resources/views/app/tasks/index.blade.php
class TasksScreen extends StatefulWidget {
  const TasksScreen({
    super.key,
    required this.repository,
    required this.slug,
    required this.projectName,
  });

  final PlatformRepository repository;
  final String slug;
  final String projectName;

  @override
  State<TasksScreen> createState() => _TasksScreenState();
}

class _TasksScreenState extends State<TasksScreen> {
  late Future<Map<String, List<TaskModel>>> _future = widget.repository.tasks(
    widget.slug,
  );

  static const Map<String, String> _columns = {
    'todo': 'لم تبدأ',
    'doing': 'قيد التنفيذ',
    'done': 'منجزة',
  };

  /// المهام التي أُرسل طلب تطويرها في هذه الجلسة — لتعطيل الزر فورًا بدل
  /// انتظار دورة تحديث تُظهر الحالة القادمة من الخادم.
  final Set<int> _developing = <int>{};

  void _reload() =>
      setState(() => _future = widget.repository.tasks(widget.slug));

  Future<void> _develop(TaskModel task) async {
    setState(() => _developing.add(task.id));

    try {
      await widget.repository.developTask(task.id);

      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'بدأ تطوير المهمة. اسحب للتحديث بعد دقيقة لترى الخطوات والأمثلة.',
          ),
        ),
      );

      _reload();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(userErrorMessage(error))));
      }
    } finally {
      if (mounted) setState(() => _developing.remove(task.id));
    }
  }

  Future<void> _update(TaskModel task, String status) async {
    try {
      await widget.repository.updateTask(task.id, status);
      _reload();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(userErrorMessage(error))));
      }
    }
  }

  /// نظير `app/tasks/partials/guide.blade.php` — نفس الحالات ونفس النصوص.
  Widget _buildGuide(TaskModel task) {
    if (task.isDeveloping) {
      return const Padding(
        padding: EdgeInsets.only(top: 10),
        child: Text(
          'يُطوَّر دليل التنفيذ الآن. اسحب للتحديث بعد دقيقة.',
          style: TextStyle(color: BrandColors.muted, fontSize: 12),
        ),
      );
    }

    final guide = task.guide;

    if (guide == null) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (task.workedExample != null)
            WorkedExampleCard(example: task.workedExample!),
          const SizedBox(height: 10),
          OutlinedButton.icon(
            onPressed: _developing.contains(task.id)
                ? null
                : () => _develop(task),
            icon: const Icon(Icons.auto_awesome, size: 18),
            label: const Text('طوّر هذه المهمة: كيف ومتى وأين وأمثلة جاهزة'),
          ),
        ],
      );
    }

    final facets = <String, String?>{
      'كيف تُنفَّذ': guide.how,
      'متى': guide.when,
      'أين': guide.where,
      'ماذا تخرج به': guide.deliverable,
    };

    return Theme(
      data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
      child: ExpansionTile(
        tilePadding: EdgeInsets.zero,
        childrenPadding: EdgeInsets.zero,
        title: Wrap(
          spacing: 8,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            const Text(
              'دليل التنفيذ',
              style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
            ),
            // الدليل المبدئي يُعلن أنه مبدئي (§٤.١).
            if (task.isFallbackGuide)
              const SeverityBadge(label: 'مبدئي', severity: 'medium'),
          ],
        ),
        children: [
          for (final entry in facets.entries)
            if (entry.value != null && entry.value!.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      entry.key,
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 13,
                        color: BrandColors.navy,
                      ),
                    ),
                    Text(
                      entry.value!,
                      style: const TextStyle(fontSize: 13, height: 1.7),
                    ),
                  ],
                ),
              ),

          if (guide.checkpoints.isNotEmpty)
            _bullets('كيف تعرف أنك ماشٍ صح', guide.checkpoints),
          if (guide.pitfalls.isNotEmpty)
            _bullets('أخطاء شائعة هنا', guide.pitfalls),

          for (var i = 0; i < guide.examples.length; i++)
            WorkedExampleCard(
              example: guide.examples[i],
              initiallyExpanded: i == 0,
            ),

          const SizedBox(height: 10),
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: OutlinedButton(
              onPressed: _developing.contains(task.id)
                  ? null
                  : () => _develop(task),
              child: const Text('أعد تطوير الدليل'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _bullets(String title, List<String> items) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            fontWeight: FontWeight.w700,
            fontSize: 13,
            color: BrandColors.navy,
          ),
        ),
        for (final item in items)
          Text('• $item', style: const TextStyle(fontSize: 13, height: 1.6)),
      ],
    ),
  );

  @override
  Widget build(BuildContext context) {
    return AdaptiveScaffold(
      family: AdaptivePageFamily.operational,
      appBar: AppBar(title: const Text('المهام')),
      body: FutureBuilder<Map<String, List<TaskModel>>>(
        future: _future,
        builder: (context, snapshot) => AsyncView(
          snapshot: snapshot,
          onRetry: _reload,
          builder: (groups) {
            final isEmpty = groups.values.every((list) => list.isEmpty);

            return RefreshIndicator(
              onRefresh: () async => _reload(),
              child: ListView(
                padding: EdgeInsets.zero,
                children: [
                  Text(
                    widget.projectName,
                    style: const TextStyle(color: BrandColors.muted),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'من التوصية إلى التنفيذ',
                    style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'كل مهمة هنا جاءت من توصية في تقرير، ومعها أثرها وجهدها وموعدها.',
                    style: TextStyle(color: BrandColors.muted),
                  ),
                  const SizedBox(height: 18),

                  if (isEmpty)
                    const EmptyState(
                      title: 'لا مهام بعد',
                      message:
                          'افتح أحد التقارير وحوّل التوصيات التي تريد تنفيذها إلى مهام قابلة للمتابعة.',
                    )
                  else
                    for (final entry in _columns.entries) ...[
                      Text(
                        '${entry.value} (${groups[entry.key]?.length ?? 0})',
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 10),
                      for (final task
                          in groups[entry.key] ?? const <TaskModel>[]) ...[
                        BrandCard(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                task.title,
                                style: const TextStyle(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              if (task.description != null) ...[
                                const SizedBox(height: 4),
                                Text(
                                  task.description!,
                                  style: const TextStyle(
                                    color: BrandColors.muted,
                                    fontSize: 13,
                                  ),
                                ),
                              ],
                              const SizedBox(height: 10),
                              Wrap(
                                spacing: 6,
                                runSpacing: 6,
                                children: [
                                  if (task.dueDate != null)
                                    SeverityBadge(
                                      label:
                                          '${task.isOverdue ? 'تأخرت عن' : 'حتى'} ${task.dueDate}',
                                      severity: task.isOverdue ? 'high' : 'low',
                                    ),
                                  if (task.impact != null)
                                    SeverityBadge(
                                      label: 'الأثر: ${task.impact}',
                                      severity: 'low',
                                    ),
                                  if (task.effort != null)
                                    SeverityBadge(
                                      label: 'الجهد: ${task.effort}',
                                      severity: 'low',
                                    ),
                                  if (task.timeframe != null)
                                    SeverityBadge(
                                      label: 'المدة: ${task.timeframe}',
                                      severity: 'low',
                                    ),
                                ],
                              ),

                              if (task.steps.isNotEmpty) ...[
                                const SizedBox(height: 10),
                                for (var i = 0; i < task.steps.length; i++)
                                  Padding(
                                    padding: const EdgeInsets.only(bottom: 4),
                                    child: Text(
                                      '${i + 1}) ${task.steps[i]}',
                                      style: const TextStyle(
                                        fontSize: 13,
                                        height: 1.6,
                                      ),
                                    ),
                                  ),
                              ],

                              _buildGuide(task),

                              const SizedBox(height: 10),
                              DropdownButtonFormField<String>(
                                initialValue: task.status,
                                decoration: const InputDecoration(
                                  isDense: true,
                                  contentPadding: EdgeInsets.symmetric(
                                    horizontal: 12,
                                    vertical: 10,
                                  ),
                                ),
                                items: _columns.entries
                                    .map(
                                      (entry) => DropdownMenuItem(
                                        value: entry.key,
                                        child: Text(entry.value),
                                      ),
                                    )
                                    .toList(),
                                onChanged: (status) {
                                  if (status != null && status != task.status) {
                                    _update(task, status);
                                  }
                                },
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 10),
                      ],
                      const SizedBox(height: 14),
                    ],
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}

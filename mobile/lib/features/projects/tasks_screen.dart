import 'package:flutter/material.dart';

import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
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
  late Future<Map<String, List<TaskModel>>> _future = widget.repository.tasks(widget.slug);

  static const Map<String, String> _columns = {
    'todo': 'لم تبدأ',
    'doing': 'قيد التنفيذ',
    'done': 'منجزة',
  };

  void _reload() => setState(() => _future = widget.repository.tasks(widget.slug));

  Future<void> _update(TaskModel task, String status) async {
    try {
      await widget.repository.updateTask(task.id, status);
      _reload();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.toString())));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
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
                padding: const EdgeInsets.all(16),
                children: [
                  Text(widget.projectName, style: const TextStyle(color: BrandColors.muted)),
                  const SizedBox(height: 6),
                  const Text('من التوصية إلى التنفيذ',
                      style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700)),
                  const SizedBox(height: 4),
                  const Text(
                    'كل مهمة هنا جاءت من توصية في تقرير، ومعها أثرها وجهدها وموعدها.',
                    style: TextStyle(color: BrandColors.muted),
                  ),
                  const SizedBox(height: 18),

                  if (isEmpty)
                    const EmptyState(
                      title: 'لا مهام بعد',
                      message: 'افتح أي تقرير وحوّل توصياته إلى مهام — هنا يتحول التحليل إلى عمل.',
                    )
                  else
                    for (final entry in _columns.entries) ...[
                      Text('${entry.value} (${groups[entry.key]?.length ?? 0})',
                          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                      const SizedBox(height: 10),
                      for (final task in groups[entry.key] ?? const <TaskModel>[]) ...[
                        BrandCard(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(task.title,
                                  style: const TextStyle(fontWeight: FontWeight.w700)),
                              if (task.description != null) ...[
                                const SizedBox(height: 4),
                                Text(task.description!,
                                    style: const TextStyle(
                                        color: BrandColors.muted, fontSize: 13)),
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
                                        label: 'الأثر: ${task.impact}', severity: 'low'),
                                  if (task.effort != null)
                                    SeverityBadge(
                                        label: 'الجهد: ${task.effort}', severity: 'low'),
                                ],
                              ),
                              const SizedBox(height: 10),
                              DropdownButtonFormField<String>(
                                initialValue: task.status,
                                decoration: const InputDecoration(
                                  isDense: true,
                                  contentPadding:
                                      EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                                ),
                                items: _columns.entries
                                    .map((entry) => DropdownMenuItem(
                                        value: entry.key, child: Text(entry.value)))
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

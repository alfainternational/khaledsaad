import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../core/error/api_exception.dart';
import '../../data/repositories/lifecycle_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';

/// تعريف حقل في ملف المشروع.
class _BriefField {
  const _BriefField(this.key, this.label, {this.long = false});

  final String key; // بصيغة group.field
  final String label;
  final bool long;
}

/// مجموعة حقول (قسم قابل للطي).
class _BriefGroup {
  const _BriefGroup(this.title, this.icon, this.fields);

  final String title;
  final IconData icon;
  final List<_BriefField> fields;
}

/// ملف المشروع التسويقي — أقسام هادئة قابلة للطي، تُفتح واحدة تلو الأخرى.
class BriefPage extends StatefulWidget {
  const BriefPage({super.key});

  @override
  State<BriefPage> createState() => _BriefPageState();
}

class _BriefPageState extends State<BriefPage> {
  static const _groups = <_BriefGroup>[
    _BriefGroup('نشاطك وعرضك', Icons.storefront_outlined, [
      _BriefField('business.summary', 'ملخّص النشاط', long: true),
      _BriefField('business.offer', 'ما الذي تبيعه؟', long: true),
      _BriefField('business.market', 'السوق'),
    ]),
    _BriefGroup('جمهورك', Icons.people_outline, [
      _BriefField('audience.ideal_customer', 'عميلك المثالي', long: true),
      _BriefField('audience.pain_points', 'أوجاعه ومشاكله', long: true),
      _BriefField('audience.buying_trigger', 'ما الذي يدفعه للشراء؟', long: true),
    ]),
    _BriefGroup('أهدافك', Icons.flag_outlined, [
      _BriefField('goals.primary_goal', 'هدفك الأساسي'),
      _BriefField('goals.success_metric', 'مقياس النجاح'),
      _BriefField('goals.timeframe', 'الإطار الزمني'),
    ]),
    _BriefGroup('تسويقك الحالي', Icons.campaign_outlined, [
      _BriefField('current_marketing.channels', 'قنواتك الحالية'),
      _BriefField('current_marketing.current_state', 'وضعك الحالي', long: true),
      _BriefField('current_marketing.assets', 'أصولك التسويقية', long: true),
    ]),
    _BriefGroup('هويتك', Icons.brush_outlined, [
      _BriefField('brand.voice', 'نبرة العلامة'),
      _BriefField('brand.tone_rules', 'قواعد الأسلوب', long: true),
    ]),
    _BriefGroup('تموضعك', Icons.center_focus_strong_outlined, [
      _BriefField('positioning.edge', 'ما الذي يميّزك؟', long: true),
      _BriefField('positioning.promise', 'وعدك للعميل', long: true),
    ]),
    _BriefGroup('منافسوك', Icons.compare_arrows_outlined, [
      _BriefField('competition.competitors', 'أبرز المنافسين', long: true),
      _BriefField('competition.gap', 'الفجوة التي تملؤها', long: true),
    ]),
    _BriefGroup('التنفيذ', Icons.checklist_outlined, [
      _BriefField('execution.priority', 'أولويتك الآن', long: true),
      _BriefField('execution.next_asset', 'أقرب مخرج تحتاجه'),
      _BriefField('execution.delivery_notes', 'ملاحظات التسليم', long: true),
    ]),
    _BriefGroup('الجانب التجاري', Icons.payments_outlined, [
      _BriefField('commercial.budget_range', 'نطاق الميزانية'),
      _BriefField('commercial.decision_maker', 'صاحب القرار'),
    ]),
  ];

  final _controllers = <String, TextEditingController>{};
  final _loading = true.obs;
  final _saving = false.obs;
  final _error = RxnString();
  final _assessment = Rxn<Map<String, dynamic>>();

  late final String _projectId = Get.arguments as String;
  late final LifecycleRepository _repo = Get.find<LifecycleRepository>();
  late final WorkspaceService _workspaces = Get.find<WorkspaceService>();

  @override
  void initState() {
    super.initState();
    for (final group in _groups) {
      for (final field in group.fields) {
        _controllers[field.key] = TextEditingController();
      }
    }
    _load();
  }

  @override
  void dispose() {
    for (final c in _controllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    final ws = _workspaces.activeId;
    if (ws == null) return;
    _loading.value = true;
    _error.value = null;
    try {
      final result = await _repo.brief(ws, _projectId);
      _fill(result.brief);
      _assessment.value = result.assessment;
    } on ApiException catch (e) {
      _error.value = e.message;
    } finally {
      _loading.value = false;
    }
  }

  void _fill(Map<String, dynamic> brief) {
    _controllers.forEach((key, controller) {
      final parts = key.split('.');
      final group = brief[parts[0]];
      final value = group is Map ? group[parts[1]] : null;
      controller.text = value?.toString() ?? '';
    });
  }

  Map<String, dynamic> _payload() {
    final payload = <String, Map<String, String>>{};
    _controllers.forEach((key, controller) {
      final parts = key.split('.');
      payload.putIfAbsent(parts[0], () => {})[parts[1]] = controller.text.trim();
    });
    return payload;
  }

  Future<void> _save() async {
    final ws = _workspaces.activeId;
    if (ws == null || _saving.value) return;
    _saving.value = true;
    try {
      final result = await _repo.updateBrief(ws, _projectId, _payload());
      _assessment.value = result.assessment;
      Get.snackbar('ملف المشروع', 'تم الحفظ بنجاح.',
          snackPosition: SnackPosition.BOTTOM);
    } on ApiException catch (e) {
      Get.snackbar('ملف المشروع', e.message,
          snackPosition: SnackPosition.BOTTOM);
    } finally {
      _saving.value = false;
    }
  }

  /// عدد الحقول المعبأة في مجموعة (لإظهار تقدّم هادئ على رأس القسم).
  int _filledIn(_BriefGroup group) =>
      group.fields.where((f) => _controllers[f.key]!.text.trim().isNotEmpty).length;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('ملف المشروع')),
      floatingActionButton: Obx(() => FloatingActionButton.extended(
            onPressed: _saving.value ? null : _save,
            icon: _saving.value
                ? const SizedBox(
                    height: 18,
                    width: 18,
                    child: CircularProgressIndicator(strokeWidth: 2.2))
                : const Icon(Icons.save_outlined),
            label: Text(_saving.value ? 'جارٍ الحفظ...' : 'حفظ'),
          )),
      body: Obx(() {
        if (_loading.value) return AppStateView.loading();
        if (_error.value != null) {
          return AppStateView.error(message: _error.value, onRetry: _load);
        }
        return ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
          children: [
            Obx(() {
              final a = _assessment.value;
              final score = a?['score'];
              final verdict = a?['verdict']?.toString() ?? a?['summary']?.toString();
              if (score == null && (verdict == null || verdict.isEmpty)) {
                return const SizedBox.shrink();
              }
              return Card(
                color: theme.colorScheme.secondaryContainer,
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Row(
                    children: [
                      Icon(Icons.insights, color: theme.colorScheme.primary),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          verdict ?? '',
                          style: theme.textTheme.bodyMedium,
                        ),
                      ),
                      if (score is num) ...[
                        const SizedBox(width: 8),
                        Text('${score.toInt()}%',
                            style: theme.textTheme.titleMedium?.copyWith(
                                color: theme.colorScheme.primary,
                                fontWeight: FontWeight.w800)),
                      ],
                    ],
                  ),
                ),
              );
            }),
            const SizedBox(height: 8),
            ..._groups.map((group) => Card(
                  margin: const EdgeInsets.only(bottom: 10),
                  clipBehavior: Clip.antiAlias,
                  child: ExpansionTile(
                    leading: Icon(group.icon,
                        color: theme.colorScheme.primary, size: 22),
                    title: Text(group.title,
                        style: theme.textTheme.titleSmall
                            ?.copyWith(fontWeight: FontWeight.w700)),
                    subtitle: Text(
                      '${_filledIn(group)} من ${group.fields.length} معبّأ',
                      style: theme.textTheme.bodySmall,
                    ),
                    childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                    children: group.fields
                        .map((f) => Padding(
                              padding: const EdgeInsets.only(top: 12),
                              child: TextField(
                                controller: _controllers[f.key],
                                minLines: f.long ? 3 : 1,
                                maxLines: f.long ? 6 : 1,
                                onChanged: (_) => setState(() {}),
                                decoration: InputDecoration(labelText: f.label),
                              ),
                            ))
                        .toList(),
                  ),
                )),
          ],
        );
      }),
    );
  }
}

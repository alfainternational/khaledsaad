import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../core/error/api_exception.dart';
import '../../data/repositories/collab_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';

/// إعداد مساحة العمل (Onboarding) — خطوات هادئة في شاشة واحدة قابلة للطي.
class OnboardingPage extends StatefulWidget {
  const OnboardingPage({super.key});

  @override
  State<OnboardingPage> createState() => _OnboardingPageState();
}

class _OnboardingPageState extends State<OnboardingPage> {
  late final CollabRepository _repo = Get.find<CollabRepository>();
  late final WorkspaceService _workspaces = Get.find<WorkspaceService>();

  final _loading = true.obs;
  final _saving = false.obs;
  final _error = RxnString();
  final _options = <String, List<MapEntry<String, String>>>{}.obs;

  final _text = <String, TextEditingController>{
    'account_name': TextEditingController(),
    'workspace_name': TextEditingController(),
    'audience': TextEditingController(),
    'country': TextEditingController(),
    'current_challenge': TextEditingController(),
    'client_name': TextEditingController(),
    'project_name': TextEditingController(),
    'primary_domain': TextEditingController(),
  };
  final _select = <String, RxnString>{
    'workspace_type': RxnString('personal'),
    'persona': RxnString(),
    'awareness_level': RxnString(),
    'primary_goal': RxnString(),
    'content_locale': RxnString('ar_modern_fusha'),
    'sector': RxnString(),
  };
  final _projectStage = 1.obs;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    for (final c in _text.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    final ws = _workspaces.activeId;
    if (ws == null) {
      await _workspaces.loadWorkspaces();
    }
    final wsId = _workspaces.activeId;
    if (wsId == null) {
      _error.value = 'لا توجد مساحة عمل.';
      _loading.value = false;
      return;
    }
    _loading.value = true;
    _error.value = null;
    try {
      final data = await _repo.onboarding(wsId);
      if (data['completed'] == true) {
        Get.offAllNamed(Routes.dashboard);
        return;
      }
      final workspace = data['workspace'] is Map
          ? Map<String, dynamic>.from(data['workspace'] as Map)
          : {};
      _text['workspace_name']!.text = workspace['name']?.toString() ?? '';
      _text['account_name']!.text = workspace['name']?.toString() ?? '';
      _select['workspace_type']!.value =
          workspace['type']?.toString() ?? 'personal';

      final options = data['options'] is Map
          ? Map<String, dynamic>.from(data['options'] as Map)
          : {};
      _options['persona'] = _parse(options['personas']);
      _options['awareness_level'] = _parse(options['awareness_levels']);
      _options['primary_goal'] = _parse(options['goals']);
      _options['content_locale'] = _parse(options['content_locales']);
      _options['sector'] = _parse(options['sectors']);
      _options['workspace_type'] = [
        const MapEntry('personal', 'شخصية'),
        const MapEntry('team', 'فريق'),
        const MapEntry('agency', 'وكالة'),
      ];

      // افتراضات مريحة
      _select['persona']!.value ??= _options['persona']?.firstOrNull?.key;
      _select['awareness_level']!.value ??=
          _options['awareness_level']?.firstOrNull?.key;
      _select['primary_goal']!.value ??=
          _options['primary_goal']?.firstOrNull?.key;
    } on ApiException catch (e) {
      _error.value = e.message;
    } finally {
      _loading.value = false;
    }
  }

  List<MapEntry<String, String>> _parse(dynamic raw) {
    if (raw is Map) {
      return raw.entries.map((e) {
        final v = e.value;
        final label =
            v is Map ? (v['label'] ?? v['title'] ?? e.key).toString() : v.toString();
        return MapEntry(e.key.toString(), label);
      }).toList();
    }
    if (raw is List) {
      return raw.whereType<Map>().map((e) {
        final value = (e['value'] ?? e['key'] ?? '').toString();
        return MapEntry(value, (e['label'] ?? e['title'] ?? value).toString());
      }).toList();
    }
    return const [];
  }

  Future<void> _submit() async {
    final ws = _workspaces.activeId;
    if (ws == null || _saving.value) return;
    _saving.value = true;
    try {
      await _repo.completeOnboarding(ws, {
        'account_name': _text['account_name']!.text.trim(),
        'workspace_name': _text['workspace_name']!.text.trim(),
        'workspace_type': _select['workspace_type']!.value,
        'persona': _select['persona']!.value,
        'awareness_level': _select['awareness_level']!.value,
        'primary_goal': _select['primary_goal']!.value,
        'audience': _text['audience']!.text.trim(),
        'country': _text['country']!.text.trim(),
        'content_locale': _select['content_locale']!.value,
        'current_challenge': _text['current_challenge']!.text.trim().isEmpty
            ? null
            : _text['current_challenge']!.text.trim(),
        'client_name': _text['client_name']!.text.trim().isEmpty
            ? _text['account_name']!.text.trim()
            : _text['client_name']!.text.trim(),
        'project_name': _text['project_name']!.text.trim(),
        'project_stage': _projectStage.value,
        'sector': _select['sector']!.value,
        'primary_domain': _text['primary_domain']!.text.trim().isEmpty
            ? null
            : _text['primary_domain']!.text.trim(),
      });
      await _workspaces.loadWorkspaces();
      Get.offAllNamed(Routes.dashboard);
    } on ApiException catch (e) {
      final firstError =
          e.errors.isNotEmpty ? e.errors.values.first.first : e.message;
      Get.snackbar('الإعداد', firstError, snackPosition: SnackPosition.BOTTOM);
    } finally {
      _saving.value = false;
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('إعداد مساحتك'), automaticallyImplyLeading: false),
      body: Obx(() {
        if (_loading.value) return AppStateView.loading();
        if (_error.value != null) {
          return AppStateView.error(message: _error.value, onRetry: _load);
        }
        return ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
          children: [
            Text(
              'خطوات قليلة ويصبح كل شيء جاهزاً لمشروعك الأول.',
              style: theme.textTheme.bodyMedium,
            ),
            const SizedBox(height: 16),
            _group('حسابك ومساحتك', Icons.badge_outlined, [
              _field('account_name', 'اسم الحساب'),
              _field('workspace_name', 'اسم مساحة العمل'),
              _dropdown('workspace_type', 'نوع المساحة'),
            ], initiallyExpanded: true),
            _group('من أنت وماذا تريد؟', Icons.psychology_outlined, [
              _dropdown('persona', 'صفتك'),
              _dropdown('awareness_level', 'مستوى وعيك التسويقي'),
              _dropdown('primary_goal', 'هدفك الأساسي'),
              _field('audience', 'جمهورك'),
              _field('country', 'الدولة'),
              _dropdown('content_locale', 'لهجة المحتوى'),
              _field('current_challenge', 'تحدّيك الحالي (اختياري)'),
            ]),
            _group('مشروعك الأول', Icons.rocket_launch_outlined, [
              _field('client_name', 'اسم العميل/النشاط'),
              _field('project_name', 'اسم المشروع'),
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: Obx(() => DropdownButtonFormField<int>(
                      initialValue: _projectStage.value,
                      decoration:
                          const InputDecoration(labelText: 'مرحلة المشروع'),
                      items: List.generate(
                          5,
                          (i) => DropdownMenuItem(
                              value: i + 1, child: Text('المرحلة ${i + 1}'))),
                      onChanged: (v) => _projectStage.value = v ?? 1,
                    )),
              ),
              _dropdown('sector', 'القطاع'),
              _field('primary_domain', 'موقعك الإلكتروني (اختياري)'),
            ]),
            const SizedBox(height: 16),
            Obx(() => FilledButton.icon(
                  onPressed: _saving.value ? null : _submit,
                  icon: _saving.value
                      ? const SizedBox(
                          height: 18,
                          width: 18,
                          child: CircularProgressIndicator(strokeWidth: 2.2))
                      : const Icon(Icons.check),
                  label: Text(_saving.value ? 'جارٍ الإعداد...' : 'ابدأ الآن'),
                )),
          ],
        );
      }),
    );
  }

  Widget _group(String title, IconData icon, List<Widget> children,
      {bool initiallyExpanded = false}) {
    final theme = Theme.of(context);
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      clipBehavior: Clip.antiAlias,
      child: ExpansionTile(
        initiallyExpanded: initiallyExpanded,
        leading: Icon(icon, color: theme.colorScheme.primary, size: 22),
        title: Text(title,
            style:
                theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
        childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        children: children,
      ),
    );
  }

  Widget _field(String key, String label) => Padding(
        padding: const EdgeInsets.only(top: 12),
        child: TextField(
          controller: _text[key],
          decoration: InputDecoration(labelText: label),
        ),
      );

  Widget _dropdown(String key, String label) => Padding(
        padding: const EdgeInsets.only(top: 12),
        child: Obx(() {
          final options = _options[key] ?? const <MapEntry<String, String>>[];
          final current = _select[key]!.value;
          final valid = options.any((o) => o.key == current) ? current : null;
          return DropdownButtonFormField<String>(
            initialValue: valid,
            isExpanded: true,
            decoration: InputDecoration(labelText: label),
            items: options
                .map((o) => DropdownMenuItem(value: o.key, child: Text(o.value)))
                .toList(),
            onChanged: (v) => _select[key]!.value = v,
          );
        }),
      );
}

extension _FirstOrNull<E> on List<E> {
  E? get firstOrNull => isEmpty ? null : first;
}

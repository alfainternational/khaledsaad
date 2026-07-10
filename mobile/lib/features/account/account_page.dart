import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../core/error/api_exception.dart';
import '../../data/repositories/collab_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';

/// الحساب — ثلاثة أقسام هادئة: هويتك، الحساب والمساحة، ملفك التسويقي.
class AccountPage extends StatefulWidget {
  const AccountPage({super.key});

  @override
  State<AccountPage> createState() => _AccountPageState();
}

class _AccountPageState extends State<AccountPage> {
  late final CollabRepository _repo = Get.find<CollabRepository>();
  late final WorkspaceService _workspaces = Get.find<WorkspaceService>();

  final _loading = true.obs;
  final _saving = false.obs;
  final _error = RxnString();

  // خيارات القوائم من الخادم: [{value/key, label}] أو {key: label}
  final _options = <String, List<MapEntry<String, String>>>{}.obs;
  final _planName = RxnString();

  // قيم الحقول
  final _text = <String, TextEditingController>{};
  final _select = <String, RxnString>{};

  static const _textKeys = <String, String>{
    'name': 'اسمك',
    'account_name': 'اسم الحساب',
    'billing_email': 'بريد الفوترة',
    'workspace_name': 'اسم مساحة العمل',
    'audience': 'جمهورك',
    'country': 'الدولة',
    'current_challenge': 'تحدّيك الحالي (اختياري)',
  };

  static const _selectKeys = <String, String>{
    'locale': 'اللغة',
    'workspace_type': 'نوع المساحة',
    'persona': 'صفتك',
    'awareness_level': 'مستوى وعيك التسويقي',
    'primary_goal': 'هدفك الأساسي',
    'recommended_path': 'مسارك المقترح',
    'content_locale': 'لهجة المحتوى',
  };

  @override
  void initState() {
    super.initState();
    for (final key in _textKeys.keys) {
      _text[key] = TextEditingController();
    }
    for (final key in _selectKeys.keys) {
      _select[key] = RxnString();
    }
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
    if (ws == null) return;
    _loading.value = true;
    _error.value = null;
    try {
      final data = await _repo.account(ws);

      final user = _asMap(data['user']);
      final account = _asMap(data['account']);
      final workspace = _asMap(data['workspace']);
      final profile = _asMap(data['profile']);
      final plan = _asMap(data['plan']);
      _planName.value = plan['name']?.toString();

      _text['name']!.text = user['name']?.toString() ?? '';
      _text['account_name']!.text = account['name']?.toString() ?? '';
      _text['billing_email']!.text = account['billing_email']?.toString() ?? '';
      _text['workspace_name']!.text = workspace['name']?.toString() ?? '';
      _text['audience']!.text = profile['audience']?.toString() ?? '';
      _text['country']!.text = profile['country']?.toString() ?? '';
      _text['current_challenge']!.text =
          profile['current_challenge']?.toString() ?? '';

      _select['locale']!.value = user['locale']?.toString() ?? 'ar';
      _select['workspace_type']!.value = workspace['type']?.toString();
      for (final key in [
        'persona',
        'awareness_level',
        'primary_goal',
        'recommended_path',
        'content_locale'
      ]) {
        _select[key]!.value = profile[key]?.toString();
      }

      // خيارات القوائم
      final options = _asMap(data['options']);
      _options['persona'] = _parseOptions(options['personas']);
      _options['awareness_level'] = _parseOptions(options['awareness_levels']);
      _options['primary_goal'] = _parseOptions(options['goals']);
      _options['recommended_path'] = _parseOptions(options['paths']);
      _options['content_locale'] = _parseOptions(options['content_locales']);
      _options['locale'] = [
        const MapEntry('ar', 'العربية'),
        const MapEntry('en', 'English'),
      ];
      _options['workspace_type'] = [
        const MapEntry('personal', 'شخصية'),
        const MapEntry('team', 'فريق'),
        const MapEntry('agency', 'وكالة'),
      ];
    } on ApiException catch (e) {
      _error.value = e.message;
    } finally {
      _loading.value = false;
    }
  }

  /// يحوّل خيارات الخادم (خريطة {key: label} أو قائمة [{value,label}]) لقائمة موحّدة.
  List<MapEntry<String, String>> _parseOptions(dynamic raw) {
    if (raw is Map) {
      return raw.entries
          .map((e) {
            final v = e.value;
            final label = v is Map
                ? (v['label'] ?? v['title'] ?? e.key).toString()
                : v.toString();
            return MapEntry(e.key.toString(), label);
          })
          .toList();
    }
    if (raw is List) {
      return raw.whereType<Map>().map((e) {
        final value = (e['value'] ?? e['key'] ?? '').toString();
        final label = (e['label'] ?? e['title'] ?? value).toString();
        return MapEntry(value, label);
      }).toList();
    }
    return const [];
  }

  Future<void> _save() async {
    final ws = _workspaces.activeId;
    if (ws == null || _saving.value) return;
    _saving.value = true;
    try {
      await _repo.updateAccount(ws, {
        for (final e in _text.entries)
          e.key: e.value.text.trim().isEmpty && e.key == 'current_challenge'
              ? null
              : e.value.text.trim(),
        for (final e in _select.entries) e.key: e.value.value,
      });
      Get.snackbar('الحساب', 'تم حفظ الإعدادات.',
          snackPosition: SnackPosition.BOTTOM);
      await _workspaces.loadWorkspaces();
    } on ApiException catch (e) {
      final firstFieldError = e.errors.isNotEmpty
          ? e.errors.values.first.first
          : null;
      Get.snackbar('الحساب', firstFieldError ?? e.message,
          snackPosition: SnackPosition.BOTTOM);
    } finally {
      _saving.value = false;
    }
  }

  static Map<String, dynamic> _asMap(dynamic v) =>
      v is Map ? Map<String, dynamic>.from(v) : <String, dynamic>{};

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('الحساب')),
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
            if (_planName.value != null)
              Card(
                color: theme.colorScheme.secondaryContainer,
                child: ListTile(
                  leading: Icon(Icons.workspace_premium_outlined,
                      color: theme.colorScheme.primary),
                  title: Text('باقتك: ${_planName.value}'),
                ),
              ),
            _group('هويتك', Icons.person_outline, [
              _field('name'),
              _dropdown('locale'),
            ]),
            _group('الحساب والمساحة', Icons.business_outlined, [
              _field('account_name'),
              _field('billing_email'),
              _field('workspace_name'),
              _dropdown('workspace_type'),
            ]),
            _group('ملفك التسويقي', Icons.insights_outlined, [
              _dropdown('persona'),
              _dropdown('awareness_level'),
              _dropdown('primary_goal'),
              _dropdown('recommended_path'),
              _field('audience'),
              _field('country'),
              _dropdown('content_locale'),
              _field('current_challenge'),
            ]),
          ],
        );
      }),
    );
  }

  Widget _group(String title, IconData icon, List<Widget> children) {
    final theme = Theme.of(context);
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      clipBehavior: Clip.antiAlias,
      child: ExpansionTile(
        leading: Icon(icon, color: theme.colorScheme.primary, size: 22),
        title: Text(title,
            style:
                theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
        childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        children: children,
      ),
    );
  }

  Widget _field(String key) => Padding(
        padding: const EdgeInsets.only(top: 12),
        child: TextField(
          controller: _text[key],
          decoration: InputDecoration(labelText: _textKeys[key]),
        ),
      );

  Widget _dropdown(String key) => Padding(
        padding: const EdgeInsets.only(top: 12),
        child: Obx(() {
          final options = _options[key] ?? const <MapEntry<String, String>>[];
          final current = _select[key]!.value;
          final valid = options.any((o) => o.key == current) ? current : null;
          return DropdownButtonFormField<String>(
            initialValue: valid,
            isExpanded: true,
            decoration: InputDecoration(labelText: _selectKeys[key]),
            items: options
                .map((o) => DropdownMenuItem(value: o.key, child: Text(o.value)))
                .toList(),
            onChanged: (v) => _select[key]!.value = v,
          );
        }),
      );
}

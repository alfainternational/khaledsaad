import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../core/error/api_exception.dart';
import '../../data/repositories/collab_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';
import '../shared/widgets/ui_feedback.dart';

/// علامة الوكالة (white-label): تفعيل + اسم + لون + شعار.
class BrandingPage extends StatefulWidget {
  const BrandingPage({super.key});

  @override
  State<BrandingPage> createState() => _BrandingPageState();
}

class _BrandingPageState extends State<BrandingPage> {
  late final CollabRepository _repo = Get.find<CollabRepository>();
  late final WorkspaceService _workspaces = Get.find<WorkspaceService>();

  final _loading = true.obs;
  final _saving = false.obs;
  final _error = RxnString();
  final _notEntitled = false.obs;

  final _enabled = false.obs;
  final _name = TextEditingController();
  final _color = TextEditingController();
  final _logoUrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _name.dispose();
    _color.dispose();
    _logoUrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final ws = _workspaces.activeId;
    if (ws == null) return;
    _loading.value = true;
    _error.value = null;
    _notEntitled.value = false;
    try {
      final data = await _repo.branding(ws);
      final branding =
          data['branding'] is Map ? Map<String, dynamic>.from(data['branding'] as Map) : {};
      _enabled.value = branding['enabled'] == true;
      _name.text = branding['name']?.toString() ?? '';
      _color.text = branding['color']?.toString() ?? '#6366f1';
      _logoUrl.text = branding['logo_url']?.toString() ?? '';
    } on ApiException catch (e) {
      if (e.isEntitlementRequired || e.isForbidden) {
        _notEntitled.value = true;
      } else {
        _error.value = e.message;
      }
    } finally {
      _loading.value = false;
    }
  }

  Future<void> _save() async {
    final ws = _workspaces.activeId;
    if (ws == null || _saving.value) return;
    _saving.value = true;
    try {
      await _repo.updateBranding(ws, {
        'enabled': _enabled.value,
        'name': _name.text.trim().isEmpty ? null : _name.text.trim(),
        'color': _color.text.trim().isEmpty ? null : _color.text.trim(),
        'logo_url': _logoUrl.text.trim().isEmpty ? null : _logoUrl.text.trim(),
      });
      UiFeedback.success('تم الحفظ.', title: 'علامة الوكالة');
    } on ApiException catch (e) {
      final firstFieldError =
          e.errors.isNotEmpty ? e.errors.values.first.first : null;
      UiFeedback.error(firstFieldError ?? e.message, title: 'علامة الوكالة');
    } finally {
      _saving.value = false;
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('علامة الوكالة')),
      body: Obx(() {
        if (_loading.value) return AppStateView.loading();
        if (_notEntitled.value) {
          return AppStateView.empty(
            icon: Icons.workspace_premium_outlined,
            title: 'ميزة الوكالات',
            message:
                'العلامة البيضاء متاحة في باقات الوكالة. رقِّ باقتك لتخصيص تقاريرك باسمك وشعارك.',
          );
        }
        if (_error.value != null) {
          return AppStateView.error(message: _error.value, onRetry: _load);
        }
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: Obx(() => SwitchListTile(
                    title: const Text('تفعيل العلامة البيضاء'),
                    subtitle: const Text(
                        'تظهر تقاريرك ومخرجاتك باسم وكالتك بدل المنصة.'),
                    value: _enabled.value,
                    onChanged: (v) => _enabled.value = v,
                  )),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _name,
              decoration: const InputDecoration(labelText: 'اسم العلامة'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _color,
              decoration: const InputDecoration(
                labelText: 'اللون الأساسي (hex)',
                hintText: '#6366f1',
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _logoUrl,
              keyboardType: TextInputType.url,
              decoration:
                  const InputDecoration(labelText: 'رابط الشعار (اختياري)'),
            ),
            const SizedBox(height: 24),
            Obx(() => FilledButton.icon(
                  onPressed: _saving.value ? null : _save,
                  icon: _saving.value
                      ? const SizedBox(
                          height: 18,
                          width: 18,
                          child: CircularProgressIndicator(strokeWidth: 2.2))
                      : const Icon(Icons.save_outlined),
                  label: Text(_saving.value ? 'جارٍ الحفظ...' : 'حفظ'),
                )),
            const SizedBox(height: 8),
            Text(
              'ملاحظة: يظهر أثر العلامة في تصدير PDF وتقارير العملاء.',
              style: theme.textTheme.bodySmall,
              textAlign: TextAlign.center,
            ),
          ],
        );
      }),
    );
  }
}

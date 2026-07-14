import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../core/error/api_exception.dart';
import '../../data/repositories/collab_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';
import '../shared/widgets/ui_feedback.dart';

/// ربط مفتاح ذكاء خاص بالحساب (BYOK): تعمل توليداتك على مفتاحك بدل رصيد المنصة.
/// openrouter يمنحك Claude و ChatGPT بمفتاح واحد.
class AiKeyPage extends StatefulWidget {
  const AiKeyPage({super.key});

  @override
  State<AiKeyPage> createState() => _AiKeyPageState();
}

class _AiKeyPageState extends State<AiKeyPage> {
  late final CollabRepository _repo = Get.find<CollabRepository>();
  late final WorkspaceService _workspaces = Get.find<WorkspaceService>();

  final _loading = true.obs;
  final _saving = false.obs;
  final _error = RxnString();

  final _connected = false.obs;
  final _provider = 'openrouter'.obs;
  final _maskedKey = RxnString();
  final _providers = <String>['openrouter', 'groq', 'cerebras'].obs;

  final _keyController = TextEditingController();

  // أسماء ودّية للمزوّدين.
  static const _labels = <String, String>{
    'openrouter': 'OpenRouter (Claude + ChatGPT بمفتاح واحد)',
    'groq': 'Groq',
    'cerebras': 'Cerebras',
  };

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _keyController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final ws = _workspaces.activeId;
    if (ws == null) {
      _loading.value = false;
      _error.value = 'لا توجد مساحة عمل نشطة.';
      return;
    }
    _loading.value = true;
    _error.value = null;
    try {
      final data = await _repo.aiKeyStatus(ws);
      _connected.value = data['connected'] == true;
      _maskedKey.value = data['masked_key']?.toString();
      final provider = data['provider']?.toString();
      final available = (data['available_providers'] as List?)
          ?.map((e) => e.toString())
          .toList();
      if (available != null && available.isNotEmpty) {
        _providers.assignAll(available);
      }
      if (provider != null && _providers.contains(provider)) {
        _provider.value = provider;
      }
    } on ApiException catch (e) {
      _error.value = e.message;
    } finally {
      _loading.value = false;
    }
  }

  Future<void> _connect() async {
    final ws = _workspaces.activeId;
    if (ws == null || _saving.value) return;
    final key = _keyController.text.trim();
    if (key.length < 20) {
      UiFeedback.error('المفتاح غير صالح. الصقه كاملاً.', title: 'مفتاح الذكاء');
      return;
    }
    _saving.value = true;
    try {
      final data = await _repo.setAiKey(ws, provider: _provider.value, key: key);
      _connected.value = true;
      _maskedKey.value = data['masked_key']?.toString();
      _keyController.clear();
      UiFeedback.success('تم ربط مفتاحك. توليداتك تعمل على حسابك الآن.',
          title: 'مفتاح الذكاء');
    } on ApiException catch (e) {
      final firstFieldError =
          e.errors.isNotEmpty ? e.errors.values.first.first : null;
      UiFeedback.error(firstFieldError ?? e.message, title: 'مفتاح الذكاء');
    } finally {
      _saving.value = false;
    }
  }

  Future<void> _disconnect() async {
    final ws = _workspaces.activeId;
    if (ws == null || _saving.value) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('إلغاء ربط المفتاح'),
        content: const Text(
            'ستعود توليداتك لرصيد المنصة. هل تريد إلغاء ربط مفتاحك الخاص؟'),
        actions: [
          TextButton(
              onPressed: () => Get.back(result: false),
              child: const Text('تراجع')),
          FilledButton(
              onPressed: () => Get.back(result: true),
              child: const Text('إلغاء الربط')),
        ],
      ),
    );
    if (ok != true) return;
    _saving.value = true;
    try {
      await _repo.clearAiKey(ws);
      _connected.value = false;
      _maskedKey.value = null;
      UiFeedback.success('أُلغي ربط المفتاح.', title: 'مفتاح الذكاء');
    } on ApiException catch (e) {
      UiFeedback.error(e.message, title: 'مفتاح الذكاء');
    } finally {
      _saving.value = false;
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('مفتاح الذكاء الخاص')),
      body: Obx(() {
        if (_loading.value) return AppStateView.loading();
        if (_error.value != null) {
          return AppStateView.error(message: _error.value, onRetry: _load);
        }
        return ListView(
          padding: const EdgeInsets.all(20),
          children: [
            Text(
              'اربط مفتاح مزوّد ذكاء خاص بك، فتعمل كل توليداتك على حسابك ومفتاحك '
              'بدل استهلاك رصيد المنصة. المفتاح يُخزَّن مشفّراً ولا يظهر بعد الحفظ.',
              style: theme.textTheme.bodyMedium,
            ),
            const SizedBox(height: 20),
            if (_connected.value) ...[
              Card(
                child: ListTile(
                  leading: Icon(Icons.check_circle,
                      color: theme.colorScheme.primary),
                  title: const Text('مفتاحك مربوط'),
                  subtitle: Text(
                    '${_labels[_provider.value] ?? _provider.value}\n'
                    'المفتاح: ${_maskedKey.value ?? ''}',
                  ),
                  isThreeLine: true,
                ),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: _saving.value ? null : _disconnect,
                icon: const Icon(Icons.link_off),
                label: const Text('إلغاء ربط المفتاح'),
              ),
              const Divider(height: 40),
              Text('استبدال المفتاح',
                  style: theme.textTheme.titleMedium),
              const SizedBox(height: 12),
            ],
            DropdownButtonFormField<String>(
              initialValue: _provider.value,
              decoration: const InputDecoration(
                labelText: 'المزوّد',
                border: OutlineInputBorder(),
              ),
              items: _providers
                  .map((p) => DropdownMenuItem(
                        value: p,
                        child: Text(_labels[p] ?? p),
                      ))
                  .toList(),
              onChanged: (v) {
                if (v != null) _provider.value = v;
              },
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _keyController,
              obscureText: true,
              decoration: const InputDecoration(
                labelText: 'المفتاح (API key)',
                hintText: 'الصق مفتاحك هنا',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 20),
            FilledButton.icon(
              onPressed: _saving.value ? null : _connect,
              icon: _saving.value
                  ? const SizedBox(
                      height: 18,
                      width: 18,
                      child: CircularProgressIndicator(strokeWidth: 2.2))
                  : const Icon(Icons.link),
              label: Text(_connected.value ? 'حفظ المفتاح الجديد' : 'ربط المفتاح'),
            ),
          ],
        );
      }),
    );
  }
}

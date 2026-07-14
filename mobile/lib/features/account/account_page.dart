import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../app/theme/theme_controller.dart';
import '../../core/error/api_exception.dart';
import '../../data/models/dashboard_models.dart';
import '../../data/models/workspace_model.dart';
import '../../data/repositories/auth_repository.dart';
import '../../data/repositories/collab_repository.dart';
import '../../data/services/app_lock_service.dart';
import '../../data/services/session_service.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';
import '../shared/widgets/ui_feedback.dart';

/// الحساب — ثلاثة أقسام هادئة: هويتك، الحساب والمساحة، ملفك التسويقي.
class AccountPage extends StatefulWidget {
  const AccountPage({super.key});

  @override
  State<AccountPage> createState() => _AccountPageState();
}

class _AccountPageState extends State<AccountPage> {
  late final CollabRepository _repo = Get.find<CollabRepository>();
  late final WorkspaceService _workspaces = Get.find<WorkspaceService>();
  late final ThemeController _theme = Get.find<ThemeController>();
  late final AuthRepository _auth = Get.find<AuthRepository>();
  late final SessionService _session = Get.find<SessionService>();
  late final AppLockService _lock = Get.find<AppLockService>();

  final _loading = true.obs;
  final _saving = false.obs;
  final _error = RxnString();

  // خيارات القوائم من الخادم، مفهرسة بمفتاح الحقل.
  final _options = <String, List<SelectOption>>{}.obs;
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
    if (ws == null) {
      _loading.value = false;
      _error.value = 'لا توجد مساحة عمل نشطة.';
      return;
    }
    _loading.value = true;
    _error.value = null;
    try {
      final overview = await _repo.account(ws);
      final profile = overview.profile;
      _planName.value = overview.planName;

      _text['name']!.text = overview.name;
      _text['account_name']!.text = overview.accountName;
      _text['billing_email']!.text = overview.billingEmail;
      _text['workspace_name']!.text = overview.workspaceName;
      _text['audience']!.text = profile.audience;
      _text['country']!.text = profile.country;
      _text['current_challenge']!.text = profile.currentChallenge;

      _select['locale']!.value = overview.locale;
      _select['workspace_type']!.value = overview.workspaceType;
      _select['persona']!.value = profile.persona;
      _select['awareness_level']!.value = profile.awarenessLevel;
      _select['primary_goal']!.value = profile.primaryGoal;
      _select['recommended_path']!.value = profile.recommendedPath;
      _select['content_locale']!.value = profile.contentLocale;

      // خيارات القوائم: من الخادم + خيارات ثابتة للغة ونوع المساحة.
      _options.addAll(overview.options);
      _options['locale'] = const [
        SelectOption('ar', 'العربية'),
        SelectOption('en', 'English'),
      ];
      _options['workspace_type'] = const [
        SelectOption('personal', 'شخصية'),
        SelectOption('team', 'فريق'),
        SelectOption('agency', 'وكالة'),
      ];
    } on ApiException catch (e) {
      _error.value = e.message;
    } finally {
      _loading.value = false;
    }
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
      UiFeedback.success('تم حفظ الإعدادات.', title: 'الحساب');
      await _workspaces.loadWorkspaces();
    } on ApiException catch (e) {
      final firstFieldError = e.errors.isNotEmpty
          ? e.errors.values.first.first
          : null;
      UiFeedback.error(firstFieldError ?? e.message, title: 'الحساب');
    } finally {
      _saving.value = false;
    }
  }

  /// تسجيل الخروج — نفس منطق الداشبورد: نُبطل التوكن على الخادم (إن أمكن)
  /// ثم نمسح الجلسة محلياً ونعود لتسجيل الدخول.
  Future<void> _logout() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('تسجيل الخروج'),
        content: const Text('هل تريد تسجيل الخروج من حسابك؟'),
        actions: [
          TextButton(
              onPressed: () => Get.back(result: false),
              child: const Text('تراجع')),
          FilledButton(
              onPressed: () => Get.back(result: true),
              child: const Text('تسجيل الخروج')),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await _auth.logout();
    } on ApiException catch (_) {
      // نتابع الخروج محلياً حتى لو فشل الطلب.
    }
    await _session.clear();
    Get.offAllNamed(Routes.login);
  }

  Future<void> _switchWorkspace(WorkspaceModel workspace) async {
    if (workspace.publicId == _workspaces.activeId) return;
    await _workspaces.setActive(workspace);
    await _load();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('الحساب')),
      // startFloat في RTL = أسفل اليمين، بعيداً عن زر المساعد العائم (أسفل اليسار).
      floatingActionButtonLocation: FloatingActionButtonLocation.startFloat,
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
                clipBehavior: Clip.antiAlias,
                child: ListTile(
                  leading: Icon(Icons.workspace_premium_outlined,
                      color: theme.colorScheme.primary),
                  title: Text('باقتك: ${_planName.value}'),
                  subtitle: const Text('اعرض التفاصيل وأدر الاشتراك'),
                  trailing: Icon(Icons.chevron_left,
                      color: theme.colorScheme.onSurfaceVariant),
                  onTap: () => Get.toNamed(Routes.billing),
                ),
              ),
            _appearanceAndSession(),
            _group('هويتك', Icons.person_outline, [
              _field('name'),
              _dropdown('locale'),
            ], initiallyExpanded: true),
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

  /// المظهر والجلسة: مبدّل الثيم، حجم الخط، تبديل المساحة، وتسجيل الخروج.
  Widget _appearanceAndSession() {
    final theme = Theme.of(context);
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      clipBehavior: Clip.antiAlias,
      child: ExpansionTile(
        initiallyExpanded: false,
        leading: Icon(Icons.tune, color: theme.colorScheme.primary, size: 22),
        title: Text('المظهر والجلسة',
            style: theme.textTheme.titleSmall
                ?.copyWith(fontWeight: FontWeight.w700)),
        childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        children: [
          // مبدّل الثيم الثلاثي.
          const SizedBox(height: 12),
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: Text('المظهر', style: theme.textTheme.labelLarge),
          ),
          const SizedBox(height: 8),
          Obx(() => SizedBox(
                width: double.infinity,
                child: SegmentedButton<ThemeMode>(
                  segments: const [
                    ButtonSegment(
                        value: ThemeMode.light, label: Text('فاتح')),
                    ButtonSegment(
                        value: ThemeMode.dark, label: Text('داكن')),
                    ButtonSegment(
                        value: ThemeMode.system, label: Text('تلقائي')),
                  ],
                  showSelectedIcon: false,
                  selected: {_theme.themeMode.value},
                  onSelectionChanged: (s) => _theme.setThemeMode(s.first),
                ),
              )),
          const SizedBox(height: 16),
          // متحكّم حجم الخط.
          Obx(() => Row(
                children: [
                  const Icon(Icons.format_size, size: 20),
                  const SizedBox(width: 8),
                  Text('حجم الخط', style: theme.textTheme.labelLarge),
                  const Spacer(),
                  Text('${(_theme.textScale.value * 100).round()}%',
                      style: theme.textTheme.bodySmall),
                ],
              )),
          Obx(() => Slider(
                min: ThemeController.minScale,
                max: ThemeController.maxScale,
                divisions: 9,
                value: _theme.textScale.value
                    .clamp(ThemeController.minScale, ThemeController.maxScale)
                    .toDouble(),
                label: '${(_theme.textScale.value * 100).round()}%',
                onChanged: _theme.setTextScale,
              )),
          const SizedBox(height: 4),
          // تبديل مساحة العمل (كما في الداشبورد).
          Obx(() {
            final list = _workspaces.workspaces;
            if (list.length <= 1) return const SizedBox.shrink();
            final active = _workspaces.active.value;
            return Padding(
              padding: const EdgeInsets.only(top: 8),
              child: DropdownButtonFormField<String>(
                initialValue: active?.publicId,
                isExpanded: true,
                decoration: const InputDecoration(
                  labelText: 'مساحة العمل الحالية',
                  prefixIcon: Icon(Icons.workspaces_outline),
                ),
                items: list
                    .map((w) => DropdownMenuItem(
                        value: w.publicId, child: Text(w.name)))
                    .toList(),
                onChanged: (id) {
                  final ws =
                      list.firstWhereOrNull((w) => w.publicId == id);
                  if (ws != null) _switchWorkspace(ws);
                },
              ),
            );
          }),
          const SizedBox(height: 4),
          // مفتاح الذكاء الخاص (BYOK).
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.key_outlined),
            title: const Text('مفتاح الذكاء الخاص'),
            subtitle: const Text(
                'اربط مفتاحك لتشغيل التوليد على حسابك وتوفير رصيد المنصة'),
            trailing: const Icon(Icons.chevron_left),
            onTap: () => Get.toNamed(Routes.aiKey),
          ),
          const SizedBox(height: 4),
          // قفل بيومتري اختياري.
          Obx(() => SwitchListTile(
                contentPadding: EdgeInsets.zero,
                secondary: const Icon(Icons.fingerprint),
                title: const Text('قفل بيومتري'),
                subtitle: const Text('اطلب البصمة أو الوجه عند فتح التطبيق'),
                value: _lock.enabled.value,
                onChanged: (v) async {
                  if (v && !await _lock.canAuthenticate()) {
                    UiFeedback.error('جهازك لا يدعم المصادقة البيومترية.',
                        title: 'القفل البيومتري');
                    return;
                  }
                  await _lock.setEnabled(v);
                },
              )),
          const SizedBox(height: 16),
          // تسجيل الخروج.
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: _logout,
              icon: const Icon(Icons.logout),
              label: const Text('تسجيل الخروج'),
            ),
          ),
        ],
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
          final options = _options[key] ?? const <SelectOption>[];
          final current = _select[key]!.value;
          final valid = options.any((o) => o.value == current) ? current : null;
          return DropdownButtonFormField<String>(
            initialValue: valid,
            isExpanded: true,
            decoration: InputDecoration(labelText: _selectKeys[key]),
            items: options
                .map((o) =>
                    DropdownMenuItem(value: o.value, child: Text(o.label)))
                .toList(),
            onChanged: (v) => _select[key]!.value = v,
          );
        }),
      );
}

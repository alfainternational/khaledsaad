import 'dart:convert';

import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';

class AdminHubScreen extends StatefulWidget {
  const AdminHubScreen({super.key, required this.repository});

  final PlatformRepository repository;

  @override
  State<AdminHubScreen> createState() => _AdminHubScreenState();
}

class _AdminHubScreenState extends State<AdminHubScreen> {
  late Future<List<dynamic>> _future;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  void _reload() {
    _future = Future.wait([
      widget.repository.adminDashboard(),
      widget.repository.adminUsage(),
      widget.repository.adminUsers(),
      widget.repository.adminTools(),
      widget.repository.adminCatalog(),
      widget.repository.adminPayments(),
      widget.repository.adminManualReports(),
      widget.repository.adminSettings(),
    ]);
  }

  Future<void> _action(Future<void> Function() callback, String message) async {
    setState(() => _busy = true);
    try {
      await callback();
      if (!mounted) return;
      setState(_reload);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    } on ApiException catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(exception.message)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _grant(Map<String, dynamic> user) async {
    final controller = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('إضافة رصيد إلى ${user['name']}'),
        content: TextField(
          controller: controller,
          autofocus: true,
          keyboardType: TextInputType.number,
          decoration: const InputDecoration(labelText: 'عدد الأرصدة'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('إلغاء'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('تأكيد الإضافة'),
          ),
        ],
      ),
    );
    final credits = int.tryParse(controller.text.trim());
    if (confirmed != true || credits == null || credits < 1) return;
    await _action(
      () => widget.repository.adminGrantCredits(user['id'] as int, credits),
      'أضيف الرصيد بنجاح.',
    );
  }

  Future<void> _assignPlan(
    Map<String, dynamic> user,
    Map<String, dynamic> catalog,
  ) async {
    final plans = (catalog['plans'] as List? ?? const [])
        .map((raw) => Map<String, dynamic>.from(raw as Map))
        .toList();
    if (plans.isEmpty || user['workspace_id'] == null) return;
    int planId = (user['plan_id'] as int?) ?? plans.first['id'] as int;
    String creditPolicy = 'keep';
    String effective = 'now';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text('تغيير خطة ${user['name']}'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                DropdownButtonFormField<int>(
                  initialValue: planId,
                  decoration: const InputDecoration(labelText: 'الخطة'),
                  items: [
                    for (final plan in plans)
                      DropdownMenuItem(
                        value: plan['id'] as int,
                        child: Text(plan['name'].toString()),
                      ),
                  ],
                  onChanged: (value) =>
                      setDialogState(() => planId = value ?? planId),
                ),
                DropdownButtonFormField<String>(
                  initialValue: effective,
                  decoration: const InputDecoration(labelText: 'موعد التطبيق'),
                  items: const [
                    DropdownMenuItem(value: 'now', child: Text('الآن')),
                    DropdownMenuItem(
                      value: 'period_end',
                      child: Text('نهاية الفترة'),
                    ),
                  ],
                  onChanged: (value) =>
                      setDialogState(() => effective = value ?? effective),
                ),
                DropdownButtonFormField<String>(
                  initialValue: creditPolicy,
                  decoration: const InputDecoration(labelText: 'سياسة الرصيد'),
                  items: const [
                    DropdownMenuItem(
                      value: 'keep',
                      child: Text('الاحتفاظ بالرصيد'),
                    ),
                    DropdownMenuItem(
                      value: 'plan_grant',
                      child: Text('منح رصيد الخطة'),
                    ),
                  ],
                  onChanged: (value) => setDialogState(
                    () => creditPolicy = value ?? creditPolicy,
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('إلغاء'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('اعتماد التغيير'),
            ),
          ],
        ),
      ),
    );
    if (confirmed != true) return;
    await _action(
      () => widget.repository.adminAssignPlan(
        workspaceId: user['workspace_id'] as int,
        planId: planId,
        creditPolicy: creditPolicy,
        effective: effective,
      ),
      'تم تحديث الخطة.',
    );
  }

  Future<bool> _confirm(String title, String body) async =>
      await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: Text(title),
          content: Text(body),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('إلغاء'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('تأكيد'),
            ),
          ],
        ),
      ) ??
      false;

  Future<void> _editPrompt(
    Map<String, dynamic> tool,
    Map<String, dynamic> prompt,
  ) async {
    if (prompt['locked'] == true) return;
    final content = TextEditingController(text: prompt['content']?.toString());
    var tier = prompt['tier']?.toString() ?? 'standard';
    final saved = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text('برومبت ${tool['title']}'),
          content: SizedBox(
            width: 640,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  DropdownButtonFormField<String>(
                    initialValue: tier,
                    items: const [
                      DropdownMenuItem(
                        value: 'economy',
                        child: Text('اقتصادي'),
                      ),
                      DropdownMenuItem(value: 'standard', child: Text('قياسي')),
                      DropdownMenuItem(value: 'advanced', child: Text('متقدم')),
                    ],
                    onChanged: (value) =>
                        setDialogState(() => tier = value ?? tier),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: content,
                    minLines: 10,
                    maxLines: 20,
                    decoration: const InputDecoration(labelText: 'النص الكامل'),
                  ),
                ],
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('إلغاء'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('حفظ'),
            ),
          ],
        ),
      ),
    );
    if (saved != true || content.text.trim().isEmpty) return;
    await _action(
      () => widget.repository.adminUpdatePrompt(
        tool['key'].toString(),
        prompt['id'] as int,
        content: content.text,
        tier: tier,
      ),
      'حُدّث البرومبت.',
    );
  }

  Future<Map<String, String>?> _fieldsDialog(
    String title,
    Map<String, String> labels, {
    Map<String, dynamic> initial = const {},
    Set<String> multiline = const {},
  }) async {
    final controllers = {
      for (final field in labels.keys)
        field: TextEditingController(text: initial[field]?.toString() ?? ''),
    };
    final result = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(title),
        content: SizedBox(
          width: 640,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                for (final entry in labels.entries) ...[
                  TextField(
                    controller: controllers[entry.key],
                    minLines: multiline.contains(entry.key) ? 4 : 1,
                    maxLines: multiline.contains(entry.key) ? 12 : 1,
                    decoration: InputDecoration(labelText: entry.value),
                  ),
                  const SizedBox(height: 10),
                ],
              ],
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('إلغاء'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('حفظ'),
          ),
        ],
      ),
    );
    if (result != true) return null;
    return controllers.map((key, value) => MapEntry(key, value.text.trim()));
  }

  Future<void> _editUser(Map<String, dynamic> user) async {
    final values = await _fieldsDialog('تعديل المستخدم', const {
      'name': 'الاسم',
      'email': 'البريد الإلكتروني',
    }, initial: user);
    if (values == null) return;
    await _action(
      () => widget.repository.adminUpdateUser(user['id'] as int, values),
      'حُدث المستخدم.',
    );
  }

  Future<void> _createTool() async {
    final values = await _fieldsDialog(
      'أداة جديدة',
      const {
        'key': 'المفتاح الإنجليزي',
        'name': 'الاسم الداخلي',
        'title': 'العنوان الظاهر',
        'description': 'الوصف',
        'category': 'التصنيف',
      },
      multiline: const {'description'},
    );
    if (values == null) return;
    final payload = <String, dynamic>{
      ...values,
      'status': 'coming_soon',
      'credit_cost': 5,
      'sort_order': 99,
      'output_schema':
          '{"type":"object","required":["summary","findings","next_step"],"properties":{}}',
      'scoring_rules': '{"rules":[]}',
      'section_plan': '[]',
      'fields': '[]',
    };
    await _action(
      () => widget.repository.adminCreateTool(payload),
      'أُنشئت الأداة.',
    );
  }

  Future<void> _editTool(Map<String, dynamic> tool) async {
    final version = Map<String, dynamic>.from(
      tool['current_version'] as Map? ?? const {},
    );
    final values = await _fieldsDialog(
      'تعديل الأداة',
      const {
        'name': 'الاسم الداخلي',
        'title': 'العنوان',
        'description': 'الوصف',
        'category': 'التصنيف',
        'pain': 'المشكلة',
        'promise': 'الوعد',
        'audience': 'الجمهور',
        'duration_minutes': 'المدة بالدقائق',
      },
      initial: tool,
      multiline: const {'description', 'pain', 'promise', 'audience'},
    );
    if (values == null) return;
    await _action(
      () => widget.repository.adminUpdateTool(tool['key'].toString(), {
        'key': tool['key'],
        ...values,
        'status': tool['status'],
        'sort_order': tool['sort_order'] ?? 0,
        'credit_cost': version['credit_cost'] ?? 5,
        'output_schema': _json(version['output_schema'] ?? const {}),
        'scoring_rules': _json(version['scoring_rules'] ?? const {'rules': []}),
        'section_plan': _json(version['section_plan'] ?? const []),
        'fields': _json(version['fields'] ?? const []),
      }),
      'حُدثت الأداة.',
    );
  }

  Future<void> _addCatalogItem(String type) async {
    if (type == 'feature') {
      final values = await _fieldsDialog(
        'ميزة جديدة',
        const {
          'key': 'المفتاح',
          'name': 'الاسم',
          'description': 'الوصف',
          'group': 'المجموعة',
        },
        multiline: const {'description'},
      );
      if (values == null) return;
      await _action(
        () => widget.repository.adminCreateFeature({
          ...values,
          'type': 'boolean',
          'enforcement': 'display',
          'default_enabled': false,
          'is_active': true,
          'sort_order': 99,
        }),
        'أضيفت الميزة.',
      );
      return;
    }
    if (type == 'plan') {
      final values = await _fieldsDialog('خطة جديدة', const {
        'key': 'المفتاح',
        'name': 'الاسم',
        'price': 'السعر',
        'monthly_credits': 'الرصيد الشهري',
        'project_limit': 'حد المشاريع',
      });
      if (values == null) return;
      await _action(
        () => widget.repository.adminCreatePlan({
          ...values,
          'interval': 'monthly',
          'features_text': '',
          'is_public': true,
          'sort_order': 99,
        }),
        'أُنشئت الخطة.',
      );
      return;
    }
    if (type == 'pack') {
      final values = await _fieldsDialog(
        'حزمة رصيد جديدة',
        const {
          'name': 'الاسم',
          'credits': 'عدد الأرصدة',
          'price': 'السعر',
          'currency': 'العملة',
        },
        initial: const {'currency': 'SAR'},
      );
      if (values == null) return;
      await _action(
        () => widget.repository.adminCreatePack({
          ...values,
          'is_active': true,
          'sort_order': 99,
        }),
        'أُنشئت الحزمة.',
      );
      return;
    }
    final values = await _fieldsDialog(
      'بوابة دفع جديدة',
      const {
        'provider': 'المزود: paypal أو moyasar أو tap أو manual',
        'label': 'الاسم الظاهر',
        'currency': 'العملة',
        'instructions': 'تعليمات الدفع اليدوي',
        'client_id': 'PayPal client_id',
        'secret': 'PayPal secret',
        'webhook_id': 'PayPal webhook_id',
        'secret_key': 'Moyasar/Tap secret_key',
        'webhook_secret': 'Moyasar webhook_secret',
        'merchant_id': 'Tap merchant_id',
      },
      initial: const {'currency': 'SAR', 'provider': 'manual'},
      multiline: const {'instructions'},
    );
    if (values == null) return;
    await _action(
      () => widget.repository.adminCreateGateway({
        ...values,
        'mode': 'test',
        'credentials': {
          for (final key in [
            'client_id',
            'secret',
            'webhook_id',
            'secret_key',
            'webhook_secret',
            'merchant_id',
          ])
            if ((values[key] ?? '').toString().isNotEmpty) key: values[key],
        },
      }),
      'أُنشئت البوابة.',
    );
  }

  Future<void> _editCatalogItem(String type, Map<String, dynamic> item) async {
    if (type == 'feature') {
      final values = await _fieldsDialog(
        'تعديل الميزة',
        const {
          'key': 'المفتاح',
          'name': 'الاسم',
          'description': 'الوصف',
          'group': 'المجموعة',
        },
        initial: item,
        multiline: const {'description'},
      );
      if (values == null) return;
      await _action(
        () => widget.repository.adminUpdateFeature(item['id'] as int, {
          ...values,
          'type': item['type'],
          'unit': item['unit'],
          'enforcement': item['enforcement'],
          'default_value': item['default_value'],
          'default_enabled': item['default_enabled'] == true,
          'is_active': item['is_active'] == true,
          'sort_order': item['sort_order'] ?? 0,
        }),
        'حُدثت الميزة.',
      );
      return;
    }
    if (type == 'plan') {
      final values = await _fieldsDialog('تعديل الخطة', const {
        'key': 'المفتاح',
        'name': 'الاسم',
        'price': 'السعر',
        'monthly_credits': 'الرصيد الشهري',
        'project_limit': 'حد المشاريع',
      }, initial: item);
      if (values == null) return;
      final featureRows = <String, dynamic>{};
      for (final raw in (item['feature_items'] as List? ?? const [])) {
        final feature = Map<String, dynamic>.from(raw as Map);
        featureRows[feature['feature_id'].toString()] = {
          'enabled': true,
          'value': feature['value'],
          'note': feature['note'],
        };
      }
      await _action(
        () => widget.repository.adminUpdatePlan(item['id'] as int, {
          ...values,
          'interval': item['interval'] ?? 'monthly',
          'features_text': (item['features'] as List? ?? const []).join('\n'),
          'is_public': item['is_public'] == true,
          'sort_order': item['sort_order'] ?? 0,
          'features': featureRows,
        }),
        'حُدثت الخطة.',
      );
      return;
    }
    if (type == 'pack') {
      final values = await _fieldsDialog('تعديل حزمة الرصيد', const {
        'name': 'الاسم',
        'credits': 'عدد الأرصدة',
        'price': 'السعر',
        'currency': 'العملة',
      }, initial: item);
      if (values == null) return;
      await _action(
        () => widget.repository.adminUpdatePack(item['id'] as int, {
          ...values,
          'is_active': item['is_active'] == true,
          'sort_order': item['sort_order'] ?? 0,
        }),
        'حُدثت الحزمة.',
      );
      return;
    }
    final credentialKeys = (item['credential_fields'] as List? ?? const [])
        .map((raw) => (raw as Map)['key'].toString())
        .toList();
    final gatewayFields = <String, String>{
      'label': 'الاسم الظاهر',
      'currency': 'العملة',
      'fx_rate': 'معامل التحويل',
      'instructions': 'التعليمات',
      for (final key in credentialKeys)
        'credential__$key': '$key — اتركه فارغًا للإبقاء على المحفوظ',
    };
    final values = await _fieldsDialog(
      'تعديل بوابة الدفع',
      gatewayFields,
      initial: item,
      multiline: const {'instructions'},
    );
    if (values == null) return;
    await _action(
      () => widget.repository.adminUpdateGateway(item['id'] as int, {
        ...values,
        'mode': item['mode'] ?? 'test',
        'credentials': {
          for (final key in credentialKeys)
            if ((values['credential__$key'] ?? '').toString().isNotEmpty)
              key: values['credential__$key'],
        },
      }),
      'حُدثت البوابة.',
    );
  }

  Future<void> _deleteCatalogItem(
    String type,
    Map<String, dynamic> item,
  ) async {
    if (!await _confirm(
      'تأكيد الحذف',
      'سيُحذف هذا العنصر إن لم يكن مرتبطًا ببيانات مستخدمة.',
    )) {
      return;
    }
    await _action(
      () => switch (type) {
        'feature' => widget.repository.adminDeleteFeature(item['id'] as int),
        'plan' => widget.repository.adminDeletePlan(item['id'] as int),
        'pack' => widget.repository.adminDeletePack(item['id'] as int),
        _ => widget.repository.adminDeleteGateway(item['id'] as int),
      },
      'حُذف العنصر.',
    );
  }

  Widget _catalogMenu(String type, Map<String, dynamic> item) =>
      PopupMenuButton<String>(
        onSelected: (value) async {
          if (value == 'edit') return _editCatalogItem(type, item);
          if (value == 'test') {
            return _action(
              () => widget.repository.adminTestGateway(item['id'] as int),
              'اكتمل اختبار الاتصال.',
            );
          }
          if (value == 'default') {
            return _action(
              () => widget.repository.adminDefaultGateway(item['id'] as int),
              'تغيرت البوابة الافتراضية.',
            );
          }
          return _deleteCatalogItem(type, item);
        },
        itemBuilder: (_) => [
          const PopupMenuItem(value: 'edit', child: Text('تعديل')),
          if (type == 'gateway')
            const PopupMenuItem(value: 'test', child: Text('اختبار الاتصال')),
          if (type == 'gateway' &&
              item['is_active'] == true &&
              item['is_default'] != true)
            const PopupMenuItem(
              value: 'default',
              child: Text('اجعلها الافتراضية'),
            ),
          const PopupMenuItem(value: 'delete', child: Text('حذف')),
        ],
      );

  Future<void> _editSettings(List<Map<String, dynamic>> settings) async {
    final fields = <String, String>{};
    final initial = <String, dynamic>{};
    for (final group in settings) {
      for (final raw in (group['fields'] as List? ?? const [])) {
        final field = Map<String, dynamic>.from(raw as Map);
        final input = field['key'].toString().replaceAll('.', '__');
        final label = field['label']?.toString() ?? field['key'].toString();
        fields[input] = field['type'] == 'secret'
            ? '$label — اكتب قيمة جديدة فقط'
            : label;
        initial[input] = field['type'] == 'secret' ? '' : field['value'] ?? '';
      }
    }
    final values = await _fieldsDialog(
      'تعديل الإعدادات',
      fields,
      initial: initial,
    );
    if (values == null) return;
    await _action(
      () => widget.repository.adminUpdateSettings(values),
      'حُفظت الإعدادات.',
    );
  }

  String _json(dynamic value) =>
      const JsonEncoder.withIndent('  ').convert(value);

  Future<void> _reviewManual(String uuid) async {
    setState(() => _busy = true);
    try {
      final data = await widget.repository.adminManualReport(uuid);
      final package = Map<String, dynamic>.from(
        data['package'] as Map? ?? const {},
      );
      final controller = TextEditingController(
        text: _json(package['template'] ?? package),
      );
      if (!mounted) return;
      final submit = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('مراجعة التقرير البشري'),
          content: SizedBox(
            width: 700,
            child: TextField(
              controller: controller,
              minLines: 16,
              maxLines: 24,
              decoration: const InputDecoration(
                labelText: 'محتوى التقرير بصيغة JSON',
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('إلغاء'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('اعتماد التقرير'),
            ),
          ],
        ),
      );
      if (submit != true) return;
      final decoded = jsonDecode(controller.text);
      if (decoded is! Map) {
        throw const FormatException('يجب أن يكون التقرير كائن JSON.');
      }
      await widget.repository.adminSubmitManualReport(
        uuid,
        Map<String, dynamic>.from(decoded),
      );
      if (mounted) {
        setState(_reload);
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('اعتمد التقرير البشري.')));
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AdaptiveScaffold(
      family: AdaptivePageFamily.operational,
      appBar: AppBar(
        title: const Text('لوحة الإدارة'),
        actions: [
          IconButton(
            tooltip: 'إضافة أداة',
            onPressed: _busy ? null : _createTool,
            icon: const Icon(Icons.add_box_outlined),
          ),
        ],
      ),
      body: FutureBuilder<List<dynamic>>(
        future: _future,
        builder: (context, snapshot) => AsyncView(
          snapshot: snapshot,
          onRetry: () => setState(_reload),
          builder: (data) {
            final dashboard = Map<String, dynamic>.from(data[0] as Map);
            final usage = Map<String, dynamic>.from(data[1] as Map);
            final users = (data[2] as List).cast<Map<String, dynamic>>();
            final tools = (data[3] as List).cast<Map<String, dynamic>>();
            final catalog = Map<String, dynamic>.from(data[4] as Map);
            final payments = (data[5] as List).cast<Map<String, dynamic>>();
            final manual = Map<String, dynamic>.from(data[6] as Map);
            final settings = (data[7] as List).cast<Map<String, dynamic>>();

            return RefreshIndicator(
              onRefresh: () async => setState(_reload),
              child: ListView(
                padding: EdgeInsets.zero,
                children: [
                  const Text(
                    'ملخص المنصة',
                    style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 12),
                  _DataGrid(
                    data: Map<String, dynamic>.from(
                      dashboard['stats'] as Map? ?? const {},
                    ),
                  ),
                  const SizedBox(height: 12),
                  _AdminSection(
                    title: 'استهلاك الذكاء الاصطناعي',
                    children: [
                      _DataGrid(
                        data: Map<String, dynamic>.from(
                          usage['totals'] as Map? ?? const {},
                        ),
                      ),
                    ],
                  ),
                  _AdminSection(
                    title: 'المستخدمون (${users.length})',
                    children: [
                      for (final user in users)
                        ListTile(
                          contentPadding: EdgeInsets.zero,
                          title: Text(user['name']?.toString() ?? ''),
                          subtitle: Text(
                            '${user['email']} · ${user['plan'] ?? 'بلا خطة'} · الرصيد ${user['balance'] ?? 0}',
                          ),
                          trailing: PopupMenuButton<String>(
                            enabled: !_busy,
                            onSelected: (value) async {
                              if (value == 'edit') return _editUser(user);
                              if (value == 'credits') return _grant(user);
                              if (value == 'plan') {
                                return _assignPlan(user, catalog);
                              }
                              if (await _confirm(
                                'تغيير الصلاحية',
                                'سيتم تغيير صلاحية الإدارة لهذا المستخدم.',
                              )) {
                                await _action(() async {
                                  await widget.repository.adminToggleUser(
                                    user['id'] as int,
                                  );
                                }, 'تغيرت الصلاحية.');
                              }
                            },
                            itemBuilder: (_) => const [
                              PopupMenuItem(
                                value: 'edit',
                                child: Text('تعديل المستخدم'),
                              ),
                              PopupMenuItem(
                                value: 'credits',
                                child: Text('إضافة رصيد'),
                              ),
                              PopupMenuItem(
                                value: 'plan',
                                child: Text('تغيير الخطة'),
                              ),
                              PopupMenuItem(
                                value: 'admin',
                                child: Text('تغيير صلاحية الإدارة'),
                              ),
                            ],
                          ),
                        ),
                    ],
                  ),
                  _AdminSection(
                    title: 'الأدوات والبرومبتات (${tools.length})',
                    children: [
                      for (final tool in tools)
                        ExpansionTile(
                          tilePadding: EdgeInsets.zero,
                          title: Text(tool['title']?.toString() ?? ''),
                          subtitle: Text('${tool['status']} · ${tool['key']}'),
                          trailing: PopupMenuButton<String>(
                            onSelected: (value) async {
                              if (value == 'edit') return _editTool(tool);
                              if (value == 'status') {
                                final next = tool['status'] == 'published'
                                    ? 'coming_soon'
                                    : 'published';
                                if (await _confirm(
                                  'تغيير حالة الأداة',
                                  'سيتم تغيير ظهور الأداة للعملاء.',
                                )) {
                                  await _action(
                                    () => widget.repository.adminSetToolStatus(
                                      tool['key'].toString(),
                                      next,
                                    ),
                                    'تغيرت حالة الأداة.',
                                  );
                                }
                              }
                              if (value == 'delete' &&
                                  await _confirm(
                                    'حذف الأداة',
                                    'الحذف نهائي ولن ينجح إذا كانت الأداة مستخدمة.',
                                  )) {
                                await _action(
                                  () => widget.repository.adminDeleteTool(
                                    tool['key'].toString(),
                                  ),
                                  'حُذفت الأداة.',
                                );
                              }
                            },
                            itemBuilder: (_) => const [
                              PopupMenuItem(
                                value: 'edit',
                                child: Text('تعديل'),
                              ),
                              PopupMenuItem(
                                value: 'status',
                                child: Text('تغيير الحالة'),
                              ),
                              PopupMenuItem(
                                value: 'delete',
                                child: Text('حذف'),
                              ),
                            ],
                          ),
                          children: [
                            for (final raw
                                in ((tool['current_version']
                                            as Map?)?['prompts']
                                        as List? ??
                                    const []))
                              Builder(
                                builder: (_) {
                                  final prompt = Map<String, dynamic>.from(
                                    raw as Map,
                                  );
                                  return ListTile(
                                    title: Text(
                                      prompt['stage']?.toString() ?? 'برومبت',
                                    ),
                                    subtitle: Text(
                                      prompt['locked'] == true
                                          ? 'مقفل بعد الاستخدام'
                                          : 'قابل للتعديل',
                                    ),
                                    trailing: IconButton(
                                      onPressed:
                                          prompt['locked'] == true || _busy
                                          ? null
                                          : () => _editPrompt(tool, prompt),
                                      icon: const Icon(Icons.edit_outlined),
                                    ),
                                  );
                                },
                              ),
                          ],
                        ),
                    ],
                  ),
                  _AdminSection(
                    title: 'عناصر الميزات',
                    action: IconButton(
                      tooltip: 'إضافة ميزة',
                      onPressed: _busy
                          ? null
                          : () => _addCatalogItem('feature'),
                      icon: const Icon(Icons.add),
                    ),
                    children: [
                      for (final raw
                          in (catalog['features'] as List? ?? const []))
                        Builder(
                          builder: (_) {
                            final item = Map<String, dynamic>.from(raw as Map);
                            return ListTile(
                              contentPadding: EdgeInsets.zero,
                              title: Text(item['name']?.toString() ?? ''),
                              subtitle: Text(
                                '${item['key']} · ${item['is_active'] == true ? 'مفعلة' : 'معطلة'}',
                              ),
                              trailing: _catalogMenu('feature', item),
                            );
                          },
                        ),
                    ],
                  ),
                  _AdminSection(
                    title: 'الخطط',
                    action: IconButton(
                      tooltip: 'إضافة خطة',
                      onPressed: _busy ? null : () => _addCatalogItem('plan'),
                      icon: const Icon(Icons.add),
                    ),
                    children: [
                      for (final raw in (catalog['plans'] as List? ?? const []))
                        Builder(
                          builder: (_) {
                            final item = Map<String, dynamic>.from(raw as Map);
                            return ListTile(
                              contentPadding: EdgeInsets.zero,
                              title: Text(item['name']?.toString() ?? ''),
                              subtitle: Text(
                                '${item['price']} · ${item['monthly_credits']} رصيد',
                              ),
                              trailing: _catalogMenu('plan', item),
                            );
                          },
                        ),
                    ],
                  ),
                  _AdminSection(
                    title: 'حزم الرصيد',
                    action: IconButton(
                      tooltip: 'إضافة حزمة',
                      onPressed: _busy ? null : () => _addCatalogItem('pack'),
                      icon: const Icon(Icons.add),
                    ),
                    children: [
                      for (final raw in (catalog['packs'] as List? ?? const []))
                        Builder(
                          builder: (_) {
                            final item = Map<String, dynamic>.from(raw as Map);
                            return ListTile(
                              contentPadding: EdgeInsets.zero,
                              title: Text(item['name']?.toString() ?? ''),
                              subtitle: Text(
                                '${item['credits']} رصيد · ${item['price']} ${item['currency']}',
                              ),
                              trailing: _catalogMenu('pack', item),
                            );
                          },
                        ),
                    ],
                  ),
                  _AdminSection(
                    title: 'بوابات الدفع',
                    action: IconButton(
                      tooltip: 'إضافة بوابة',
                      onPressed: _busy
                          ? null
                          : () => _addCatalogItem('gateway'),
                      icon: const Icon(Icons.add),
                    ),
                    children: [
                      for (final raw
                          in (catalog['gateways'] as List? ?? const []))
                        Builder(
                          builder: (_) {
                            final gateway = Map<String, dynamic>.from(
                              raw as Map,
                            );
                            return SwitchListTile(
                              contentPadding: EdgeInsets.zero,
                              title: Text(
                                gateway['label']?.toString() ??
                                    gateway['provider'].toString(),
                              ),
                              subtitle: Text(
                                gateway['configured'] == true
                                    ? 'مهيأة'
                                    : 'تحتاج بيانات الربط',
                              ),
                              value: gateway['is_active'] == true,
                              secondary: _catalogMenu('gateway', gateway),
                              onChanged: _busy
                                  ? null
                                  : (_) async {
                                      if (await _confirm(
                                        'تغيير حالة البوابة',
                                        'سيؤثر هذا فورًا في خيارات الدفع المتاحة.',
                                      )) {
                                        await _action(() async {
                                          await widget.repository
                                              .adminToggleGateway(
                                                gateway['id'] as int,
                                              );
                                        }, 'تغيرت حالة البوابة.');
                                      }
                                    },
                            );
                          },
                        ),
                    ],
                  ),
                  _AdminSection(
                    title: 'المدفوعات (${payments.length})',
                    children: [
                      for (final payment in payments)
                        ListTile(
                          contentPadding: EdgeInsets.zero,
                          title: Text(
                            '${payment['item'] ?? payment['purpose']} · ${payment['amount']} ${payment['currency']}',
                          ),
                          subtitle: Text(
                            payment['status_label']?.toString() ??
                                payment['status'].toString(),
                          ),
                          trailing: payment['awaiting_approval'] == true
                              ? PopupMenuButton<String>(
                                  onSelected: (value) async {
                                    if (!await _confirm(
                                      'تأكيد معالجة الدفعة',
                                      value == 'approve'
                                          ? 'ستُعتمد الدفعة ويُضاف الاستحقاق.'
                                          : 'ستُرفض الدفعة.',
                                    )) {
                                      return;
                                    }
                                    await _action(
                                      () => value == 'approve'
                                          ? widget.repository
                                                .adminApprovePayment(
                                                  payment['id'] as int,
                                                )
                                          : widget.repository
                                                .adminRejectPayment(
                                                  payment['id'] as int,
                                                ),
                                      'عولجت الدفعة.',
                                    );
                                  },
                                  itemBuilder: (_) => const [
                                    PopupMenuItem(
                                      value: 'approve',
                                      child: Text('اعتماد'),
                                    ),
                                    PopupMenuItem(
                                      value: 'reject',
                                      child: Text('رفض'),
                                    ),
                                  ],
                                )
                              : null,
                        ),
                    ],
                  ),
                  _AdminSection(
                    title: 'التقارير البشرية',
                    children: [
                      _DataGrid(
                        data: manual.map(
                          (key, value) => MapEntry(
                            key,
                            value is List ? value.length : value,
                          ),
                        ),
                      ),
                      for (final raw
                          in (manual['pending'] as List? ?? const []))
                        Builder(
                          builder: (_) {
                            final run = Map<String, dynamic>.from(raw as Map);
                            return ListTile(
                              contentPadding: EdgeInsets.zero,
                              title: Text(run['tool']?.toString() ?? ''),
                              subtitle: Text(run['project']?.toString() ?? ''),
                              trailing: FilledButton.tonal(
                                onPressed: _busy
                                    ? null
                                    : () =>
                                          _reviewManual(run['uuid'].toString()),
                                child: const Text('مراجعة'),
                              ),
                            );
                          },
                        ),
                    ],
                  ),
                  _AdminSection(
                    title: 'الإعدادات الآمنة (${settings.length} مجموعات)',
                    action: TextButton(
                      onPressed: _busy ? null : () => _editSettings(settings),
                      child: const Text('تعديل'),
                    ),
                    children: [
                      const Text(
                        'المفاتيح السرية محجوبة. تعديلها متاح فقط بقيمة جديدة ولا يعرض القيمة المحفوظة.',
                      ),
                      for (final group in settings)
                        ListTile(
                          contentPadding: EdgeInsets.zero,
                          title: Text(group['group']?.toString() ?? ''),
                          subtitle: Text(
                            '${(group['fields'] as List? ?? const []).length} إعدادات',
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 28),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}

class _AdminSection extends StatelessWidget {
  const _AdminSection({
    required this.title,
    required this.children,
    this.action,
  });
  final String title;
  final List<Widget> children;
  final Widget? action;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 12),
    child: BrandCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              action ?? const SizedBox.shrink(),
            ],
          ),
          const Divider(height: 24),
          ...children,
        ],
      ),
    ),
  );
}

class _DataGrid extends StatelessWidget {
  const _DataGrid({required this.data});
  final Map<String, dynamic> data;

  @override
  Widget build(BuildContext context) => Wrap(
    spacing: 8,
    runSpacing: 8,
    children: [
      for (final item in data.entries)
        Container(
          constraints: const BoxConstraints(minWidth: 120),
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: BrandColors.surfaceSoft,
            borderRadius: BorderRadius.circular(14),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                item.value.toString(),
                style: const TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(
                _labels[item.key] ?? item.key,
                style: const TextStyle(color: BrandColors.muted),
              ),
            ],
          ),
        ),
    ],
  );

  static const _labels = {
    'users': 'المستخدمون',
    'tools_live': 'أدوات متاحة',
    'tools_total': 'كل الأدوات',
    'runs': 'عمليات التشخيص',
    'runs_completed': 'مكتملة',
    'runs_failed': 'فشلت',
    'reports': 'التقارير',
    'ai_cost_usd': 'تكلفة الذكاء الاصطناعي',
    'ai_calls': 'الاستدعاءات',
    'calls': 'الاستدعاءات',
    'cost_usd': 'التكلفة',
    'input_tokens': 'رموز الإدخال',
    'output_tokens': 'رموز الإخراج',
    'avg_latency_ms': 'متوسط الزمن',
    'invalid_outputs': 'مخرجات مرفوضة',
    'pending': 'قيد المراجعة',
    'completed': 'مكتملة',
  };
}

import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import '../projects/models.dart';

class GrowthHubScreen extends StatefulWidget {
  const GrowthHubScreen({
    super.key,
    required this.repository,
    required this.projectSlug,
    required this.projectName,
  });

  final PlatformRepository repository;
  final String projectSlug;
  final String projectName;

  @override
  State<GrowthHubScreen> createState() => _GrowthHubScreenState();
}

class _GrowthHubScreenState extends State<GrowthHubScreen> {
  late Future<ProjectOverview> _project;
  late Future<Map<String, dynamic>> _geo;
  late Future<Map<String, dynamic>> _personas;
  late Future<List<Map<String, dynamic>>> _pulse;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  void _reload() {
    _project = widget.repository.project(widget.projectSlug);
    _geo = widget.repository.geo(widget.projectSlug);
    _personas = widget.repository.personas(widget.projectSlug);
    _pulse = widget.repository.pulse();
  }

  Future<void> _action(
    Future<dynamic> Function() action, {
    String success = 'تم التحديث.',
  }) async {
    setState(() => _busy = true);
    try {
      await action();
      if (!mounted) return;
      setState(_reload);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(success)));
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

  Future<void> _createKpi() async {
    final name = TextEditingController();
    final unit = TextEditingController();
    final target = TextEditingController();
    final created = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('مؤشر قياس جديد'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: name,
                decoration: const InputDecoration(labelText: 'اسم المؤشر'),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: unit,
                decoration: const InputDecoration(labelText: 'الوحدة'),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: target,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  labelText: 'القيمة المستهدفة',
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
            child: const Text('حفظ'),
          ),
        ],
      ),
    );
    if (created != true || name.text.trim().isEmpty) return;
    await _action(
      () => widget.repository.createKpi(widget.projectSlug, {
        'name': name.text.trim(),
        'unit': unit.text.trim().isEmpty ? null : unit.text.trim(),
        'target_value': double.tryParse(target.text.trim()),
      }),
      success: 'أضيف مؤشر القياس.',
    );
  }

  Future<void> _recordKpi(KpiModel kpi) async {
    final value = TextEditingController();
    final saved = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('تسجيل ${kpi.name}'),
        content: TextField(
          controller: value,
          autofocus: true,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: InputDecoration(labelText: kpi.unit ?? 'القيمة'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('إلغاء'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('تسجيل'),
          ),
        ],
      ),
    );
    final parsed = double.tryParse(value.text.trim());
    if (saved != true || parsed == null) return;
    await _action(
      () => widget.repository.recordKpi(kpi.id, parsed),
      success: 'سُجلت القيمة الجديدة.',
    );
  }

  Future<void> _testAudience() async {
    final message = TextEditingController();
    final run = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('اختبر رسالة على الجمهور'),
        content: TextField(
          controller: message,
          minLines: 4,
          maxLines: 8,
          decoration: const InputDecoration(labelText: 'الرسالة التسويقية'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('إلغاء'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('اختبار'),
          ),
        ],
      ),
    );
    if (run != true || message.text.trim().length < 10) return;
    await _action(
      () => widget.repository.testPersonaPanel(
        widget.projectSlug,
        message.text.trim(),
      ),
      success: 'اكتمل اختبار الرسالة.',
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('متابعة التحسين')),
      body: RefreshIndicator(
        onRefresh: () async => setState(_reload),
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              widget.projectName,
              style: const TextStyle(color: BrandColors.muted),
            ),
            const SizedBox(height: 6),
            const Text(
              'تابع النتائج وحدد الخطوة التالية',
              style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 18),
            FutureBuilder<ProjectOverview>(
              future: _project,
              builder: (context, snapshot) => _Section(
                title: 'مؤشرات القياس',
                error: snapshot.error,
                action: TextButton.icon(
                  onPressed: _busy ? null : _createKpi,
                  icon: const Icon(Icons.add),
                  label: const Text('مؤشر جديد'),
                ),
                child: snapshot.hasData
                    ? Column(
                        children: [
                          if (snapshot.data!.kpis.isEmpty)
                            const Text('لم تضف مؤشرات بعد.'),
                          for (final kpi in snapshot.data!.kpis)
                            ListTile(
                              contentPadding: EdgeInsets.zero,
                              title: Text(kpi.name),
                              subtitle: Text(
                                kpi.latest == null
                                    ? 'لا توجد قراءة بعد'
                                    : '${kpi.latest} ${kpi.unit ?? ''}',
                              ),
                              trailing: IconButton(
                                onPressed: _busy ? null : () => _recordKpi(kpi),
                                icon: const Icon(Icons.add_chart),
                              ),
                            ),
                        ],
                      )
                    : const CircularProgressIndicator(),
              ),
            ),
            const SizedBox(height: 12),
            FutureBuilder<Map<String, dynamic>>(
              future: _geo,
              builder: (context, snapshot) => _Section(
                title: 'الظهور في محركات الإجابة',
                error: snapshot.error,
                action: FilledButton.tonal(
                  onPressed: _busy
                      ? null
                      : () => _action(
                          () =>
                              widget.repository.generateGeo(widget.projectSlug),
                          success: 'تحدثت حزمة الظهور.',
                        ),
                  child: const Text('إنشاء أو تحديث'),
                ),
                child: snapshot.hasData
                    ? _MapSummary(data: snapshot.data!)
                    : const CircularProgressIndicator(),
              ),
            ),
            const SizedBox(height: 12),
            FutureBuilder<Map<String, dynamic>>(
              future: _personas,
              builder: (context, snapshot) => _Section(
                title: 'مختبر الجمهور',
                error: snapshot.error,
                action: Wrap(
                  spacing: 6,
                  children: [
                    TextButton(
                      onPressed: _busy
                          ? null
                          : () => _action(
                              () => widget.repository.buildPersonaPanel(
                                widget.projectSlug,
                              ),
                              success: 'بُنيت لوحة الجمهور.',
                            ),
                      child: const Text('بناء اللوحة'),
                    ),
                    TextButton(
                      onPressed: _busy ? null : _testAudience,
                      child: const Text('اختبار رسالة'),
                    ),
                  ],
                ),
                child: snapshot.hasData
                    ? _MapSummary(data: snapshot.data!)
                    : const CircularProgressIndicator(),
              ),
            ),
            const SizedBox(height: 12),
            FutureBuilder<List<Map<String, dynamic>>>(
              future: _pulse,
              builder: (context, snapshot) => _Section(
                title: 'نبض النمو الأسبوعي',
                error: snapshot.error,
                child: snapshot.hasData
                    ? Column(
                        children: [
                          if (snapshot.data!.isEmpty)
                            const Text('لا توجد نشرات أسبوعية بعد.'),
                          for (final item in snapshot.data!)
                            _MapSummary(data: item),
                        ],
                      )
                    : const CircularProgressIndicator(),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({
    required this.title,
    required this.child,
    this.action,
    this.error,
  });
  final String title;
  final Widget child;
  final Widget? action;
  final Object? error;

  @override
  Widget build(BuildContext context) => BrandCard(
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
        const SizedBox(height: 10),
        if (error != null)
          ErrorNotice(message: error.toString())
        else
          Center(child: child),
      ],
    ),
  );
}

class _MapSummary extends StatelessWidget {
  const _MapSummary({required this.data});
  final Map<String, dynamic> data;

  @override
  Widget build(BuildContext context) {
    final visible = data.entries.where((entry) => entry.value != null).take(8);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (final entry in visible)
          Padding(
            padding: const EdgeInsets.only(bottom: 6),
            child: Text('${_label(entry.key)}: ${_value(entry.value)}'),
          ),
      ],
    );
  }

  String _value(dynamic value) {
    if (value is List) {
      return value.isEmpty ? 'لا يوجد' : '${value.length} عناصر';
    }
    if (value is Map) {
      return value.isEmpty ? 'غير منشأة' : '${value.length} حقول جاهزة';
    }
    return value.toString();
  }

  String _label(String key) =>
      const {
        'missing_fields': 'بيانات ناقصة',
        'pack': 'الحزمة',
        'panel': 'لوحة الجمهور',
        'tests': 'الاختبارات',
        'week_start': 'الأسبوع',
        'next_step': 'الخطوة التالية',
        'items': 'المستجدات',
      }[key] ??
      key;
}

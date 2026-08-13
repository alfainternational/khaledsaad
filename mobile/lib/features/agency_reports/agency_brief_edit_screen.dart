import 'package:flutter/material.dart';

import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';

/// تحرير موجز التكليف من التطبيق — نظير `views/app/agency-reports/brief.blade.php`.
///
/// نموذج ديناميكي: الأسئلة ومجموعاتها تأتي من الخادم (مصدر واحد للنموذج والمستند
/// والـAPI)، فأي تغيير في الأسئلة يظهر هنا بلا تعديل التطبيق. الحفظ يدخل الإصدار
/// القادم من المستند، ويُقاس كفاية الحقول المفتوحة على مسار الحفظ نفسه.
class AgencyBriefEditScreen extends StatefulWidget {
  const AgencyBriefEditScreen({
    super.key,
    required this.repository,
    required this.projectSlug,
    required this.projectName,
  });

  final PlatformRepository repository;
  final String projectSlug;
  final String projectName;

  @override
  State<AgencyBriefEditScreen> createState() => _AgencyBriefEditScreenState();
}

class _AgencyBriefEditScreenState extends State<AgencyBriefEditScreen> {
  late Future<Map<String, dynamic>> _future;

  /// القيمة الراهنة لكل حقل بمفتاحه. القوائم للـmultiselect، ونصّ لغيرها.
  final Map<String, dynamic> _values = {};
  bool _saving = false;
  bool _loaded = false;

  @override
  void initState() {
    super.initState();
    _future = widget.repository.agencyBrief(widget.projectSlug);
  }

  void _hydrate(Map<String, dynamic> data) {
    if (_loaded) return;
    _loaded = true;

    final saved = Map<String, dynamic>.from(data['saved'] as Map? ?? {});
    for (final entry in saved.entries) {
      _values[entry.key] = entry.value;
    }
    // الحقول المخزّنة في أعمدة لا في JSON الموجز.
    _values['services'] = List<String>.from(
      (data['agency_services'] as List? ?? const []).map((e) => e.toString()),
    );
    if (data['primary_goal'] != null) {
      _values['primary_goal'] = data['primary_goal'];
    }
    final inclusive = data['budget_includes_agency_fee'];
    if (inclusive is bool) {
      _values['budget_includes_agency_fee'] = inclusive ? 'yes' : 'no';
    }
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await widget.repository.saveAgencyBrief(
        widget.projectSlug,
        Map<String, dynamic>.from(_values),
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'حُفظ موجز التكليف. سيدخل في الإصدار القادم من المستند.',
          ),
        ),
      );
      Navigator.of(context).pop(true);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(userErrorMessage(error))));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('موجز التكليف للوكالة')),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) => AsyncView(
          snapshot: snapshot,
          onRetry: () => setState(() {
            _future = widget.repository.agencyBrief(widget.projectSlug);
          }),
          builder: (data) {
            _hydrate(data);
            return _form(data);
          },
        ),
      ),
    );
  }

  Widget _form(Map<String, dynamic> data) {
    final groups = (data['groups'] as List? ?? const [])
        .cast<Map<String, dynamic>>();

    return AdaptivePage(
      family: AdaptivePageFamily.form,
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          const Text(
            'ما تسأل عنه الوكالة قبل أن تسعّر. كل بند غيابه يعني عرضًا لا يُقارَن.',
            style: TextStyle(color: BrandColors.muted),
          ),
          const SizedBox(height: 12),
          for (final group in groups) _groupCard(group),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: _saving ? null : _save,
            child: _saving
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Text('احفظ الموجز'),
          ),
          const SizedBox(height: 24),
        ],
      ),
    );
  }

  Widget _groupCard(Map<String, dynamic> group) {
    final fields = (group['fields'] as List? ?? const [])
        .cast<Map<String, dynamic>>();

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: BrandCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Eyebrow(group['title']?.toString() ?? ''),
            if (group['intent'] != null) ...[
              const SizedBox(height: 4),
              Text(
                group['intent'].toString(),
                style: const TextStyle(color: BrandColors.muted, fontSize: 12),
              ),
            ],
            const SizedBox(height: 8),
            for (final field in fields) _field(field),
          ],
        ),
      ),
    );
  }

  Widget _field(Map<String, dynamic> field) {
    final key = field['key'].toString();
    final label = field['label']?.toString() ?? key;
    final type = field['type']?.toString() ?? 'text';
    final options = Map<String, dynamic>.from(field['options'] as Map? ?? {});

    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(
              fontWeight: FontWeight.w700,
              color: BrandColors.navy,
            ),
          ),
          if (field['why'] != null) ...[
            const SizedBox(height: 2),
            Text(
              field['why'].toString(),
              style: const TextStyle(color: BrandColors.muted, fontSize: 11),
            ),
          ],
          const SizedBox(height: 6),
          switch (type) {
            'multiselect' => _multiselect(key, options),
            'select' => _select(key, options),
            'bool' => _select(key, options),
            'textarea' => _text(key, lines: 3),
            _ => _text(key, lines: 1),
          },
        ],
      ),
    );
  }

  Widget _text(String key, {required int lines}) {
    return TextFormField(
      initialValue: _values[key]?.toString() ?? '',
      minLines: lines,
      maxLines: lines == 1 ? 1 : lines + 2,
      decoration: const InputDecoration(border: OutlineInputBorder()),
      onChanged: (value) => _values[key] = value,
    );
  }

  Widget _select(String key, Map<String, dynamic> options) {
    final current = _values[key]?.toString();
    final valid = options.containsKey(current) ? current : null;

    return DropdownButtonFormField<String>(
      initialValue: valid,
      isExpanded: true,
      decoration: const InputDecoration(border: OutlineInputBorder()),
      items: options.entries
          .map(
            (entry) => DropdownMenuItem(
              value: entry.key,
              child: Text(
                entry.value.toString(),
                overflow: TextOverflow.ellipsis,
              ),
            ),
          )
          .toList(),
      onChanged: (value) => setState(() => _values[key] = value),
    );
  }

  Widget _multiselect(String key, Map<String, dynamic> options) {
    final selected = <String>{
      ...((_values[key] as List?)?.map((e) => e.toString()) ?? const []),
    };

    return Wrap(
      spacing: 8,
      runSpacing: 4,
      children: options.entries.map((entry) {
        final isOn = selected.contains(entry.key);
        return FilterChip(
          label: Text(entry.value.toString()),
          selected: isOn,
          onSelected: (on) => setState(() {
            if (on) {
              selected.add(entry.key);
            } else {
              selected.remove(entry.key);
            }
            _values[key] = selected.toList();
          }),
        );
      }).toList(),
    );
  }
}

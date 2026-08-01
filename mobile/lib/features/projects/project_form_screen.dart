import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/platform_repository.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';
import 'models.dart';

/// يقابل resources/views/app/projects/create.blade.php وedit.blade.php.
///
/// شاشة واحدة للإنشاء والتعديل كما في الويب: النموذج نفسه والحقول نفسها،
/// ويختلف النداء وحده. كانت تنشئ ولا تعدّل، بينما تعلن مصفوفة التكافؤ
/// `customer.projects.manage` مطابقًا — أي أن مستخدم التطبيق لا يستطيع تصحيح
/// معلومة أدخلها خطأً، ومعلومةٌ خاطئة في الملف تنتقل إلى الدماغ وإلى الدرجة.
class ProjectFormScreen extends StatefulWidget {
  const ProjectFormScreen({super.key, required this.repository, this.project});

  final PlatformRepository repository;

  /// النشاط المُعدَّل، أو null للإنشاء.
  final ProjectOverview? project;

  @override
  State<ProjectFormScreen> createState() => _ProjectFormScreenState();
}

class _ProjectFormScreenState extends State<ProjectFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _industry = TextEditingController();
  final _description = TextEditingController();
  final _geography = TextEditingController();

  String _stage = 'growth';
  String? _sector;
  bool _busy = false;
  String? _error;

  bool get _isEdit => widget.project != null;

  /// هل غيّر المستخدم المرحلة بنفسه؟
  ///
  /// القائمة تبدأ بقيمة افتراضية، وإرسالها في وضع التعديل يعيد كل نشاط إلى
  /// «نمو» بلا أن يطلب صاحبه ذلك.
  bool _stageTouched = false;

  @override
  void initState() {
    super.initState();

    final project = widget.project;

    if (project == null) return;

    // ما يعرفه التطبيق يبدأ معبّأً. الوصف والنطاق ليسا في بطاقة النشاط،
    // فيُتركان فارغين ولا يُرسلان — والفارغ لا يُرسل أصلًا في وضع التعديل.
    _name.text = project.card.name;
    _industry.text = project.card.industry ?? '';
    _sector = project.card.sector;
  }

  static const Map<String, String> _stages = {
    'idea': 'فكرة',
    'launch': 'إطلاق',
    'growth': 'نمو',
    'scale': 'توسّع',
  };

  /// شرح كل قطاع معلَن — يظهر تحت القائمة عند الاختيار.
  static const Map<String, String> _sectorHints = {
    'education': 'مدرسة، جامعة، معهد، مركز تدريب، أو منصة تعليمية',
    'ecommerce': 'متجر إلكتروني أو بيع عبر المنصات الوسيطة',
    'real_estate': 'وساطة، تطوير، تسويق عقاري، أو إدارة أملاك',
    'other': 'أي نشاط آخر — تصلك كل القدرات بالمسار العام',
  };

  @override
  void dispose() {
    _name.dispose();
    _industry.dispose();
    _description.dispose();
    _geography.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _busy = true;
      _error = null;
    });

    final payload = <String, dynamic>{
      'name': _name.text.trim(),
      'sector': _sector,
      'industry': _industry.text.trim().isEmpty ? null : _industry.text.trim(),
      'stage': _stage,
      'description': _description.text.trim().isEmpty
          ? null
          : _description.text.trim(),
      'geography': _geography.text.trim().isEmpty
          ? null
          : _geography.text.trim(),
    };

    if (_isEdit) {
      /*
       * التعديل يرسل ما لُمس فقط. إرسال حقل فارغ يكتب فراغًا فوق قيمة قائمة،
       * فيمحو نموذجُ تصحيحٍ معلوماتٍ لم يقصد صاحبها المساس بها — والمعلومة
       * الممحوّة تنتقل إلى الدماغ فتخفض التغطية والدرجة معًا.
       */
      payload.removeWhere((key, value) => value == null);

      if (!_stageTouched) payload.remove('stage');
    }

    try {
      if (_isEdit) {
        await widget.repository.updateProject(widget.project!.card.slug, payload);
      } else {
        await widget.repository.createProject(payload);
      }

      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (exception) {
      setState(() => _error = exception.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AdaptiveScaffold(
      family: AdaptivePageFamily.form,
      appBar: AppBar(title: Text(_isEdit ? 'تعديل المشروع' : 'إضافة مشروع')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            const Text(
              'أدخل المعلومات الأساسية مرة واحدة لتخصيص الأسئلة والتقارير، ويمكنك تعديلها لاحقًا.',
              style: TextStyle(color: Color(0xFF5D6B82)),
            ),
            const SizedBox(height: 20),

            if (_error != null) ...[
              ErrorNotice(message: _error!),
              const SizedBox(height: 16),
            ],

            TextFormField(
              controller: _name,
              decoration: const InputDecoration(labelText: 'اسم المشروع'),
              validator: (value) => (value == null || value.trim().isEmpty)
                  ? 'الاسم مطلوب.'
                  : null,
            ),
            const SizedBox(height: 14),

            DropdownButtonFormField<String>(
              initialValue: _sector,
              decoration: InputDecoration(
                labelText: 'القطاع',
                helperText: _sector == null ? null : _sectorHints[_sector],
              ),
              items: sectorLabels.entries
                  .map(
                    (entry) => DropdownMenuItem(
                      value: entry.key,
                      child: Text(entry.value),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _sector = value),
            ),
            const SizedBox(height: 14),

            TextFormField(
              controller: _industry,
              decoration: const InputDecoration(
                labelText: 'وصف المجال',
                hintText: 'مدارس أهلية، متجر عطور…',
              ),
            ),
            const SizedBox(height: 14),

            DropdownButtonFormField<String>(
              initialValue: _stage,
              decoration: const InputDecoration(labelText: 'مرحلة المشروع'),
              items: _stages.entries
                  .map(
                    (entry) => DropdownMenuItem(
                      value: entry.key,
                      child: Text(entry.value),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() {
                _stage = value ?? 'growth';
                _stageTouched = true;
              }),
            ),
            const SizedBox(height: 14),

            TextFormField(
              controller: _description,
              decoration: const InputDecoration(
                labelText: 'ماذا يقدم المشروع؟',
                helperText:
                    'اكتب وصفًا مباشرًا يفهمه شخص يتعرف إلى مشروعك للمرة الأولى.',
              ),
              maxLines: 3,
            ),
            const SizedBox(height: 14),

            TextFormField(
              controller: _geography,
              decoration: const InputDecoration(labelText: 'السوق الجغرافي'),
            ),
            const SizedBox(height: 24),

            FilledButton(
              onPressed: _busy ? null : _submit,
              child: _busy
                  ? const SizedBox(
                      height: 20,
                      width: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Text('احفظ المشروع'),
            ),
          ],
        ),
      ),
    );
  }
}

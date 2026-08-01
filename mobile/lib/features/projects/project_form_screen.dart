import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/platform_repository.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';
import '../../core/widgets/question_assist_panel.dart';
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

  /*
   * «لماذا يشتري منك؟» كان غائبًا عن نموذج التطبيق وحاضرًا في الويب، وهو مدخل
   * وزنه ٣ في محور الوضوح الاستراتيجي. غيابه يعني أن مستخدم التطبيق يُقاس على
   * مدخل لا سبيل له إلى تعبئته — فيرى درجة أدنى بلا ذنب.
   */
  final _valueProposition = TextEditingController();

  /// مفاتيح لوحات المساعدة، لدفع القياس إليها عند كل تغيير في خانتها.
  final Map<String, GlobalKey<QuestionAssistPanelState>> _assistKeys = {};

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
    _valueProposition.dispose();
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
      'value_proposition': _valueProposition.text.trim().isEmpty
          ? null
          : _valueProposition.text.trim(),
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

  /// دفع القيمة الحالية إلى لوحة السؤال لتقيسها بعد سكون الكتابة.
  void _measure(String key, String value) {
    _assistKeys[key]?.currentState?.scheduleMeasure(value);
  }

  /// لوحة مساعدة سؤال واحد من ملف المشروع.
  ///
  /// تختفي في وضع الإنشاء: المقترح يُبنى على ما نعرفه عن النشاط، ولا نشاط بعد —
  /// وهي نفس قاعدة شاشة الإنشاء في الويب. زرٌّ هنا كان سيُنتج كلامًا عامًّا لا
  /// يفرّق بين عميل وعميل، ويستهلك من السقف ثمنه.
  Widget _assist(
    String key,
    String type,
    String Function() currentValue,
    ValueChanged<String> onApply,
  ) {
    final slug = widget.project?.card.slug;

    if (slug == null) return const SizedBox.shrink();

    final panelKey = _assistKeys.putIfAbsent(
      key,
      GlobalKey<QuestionAssistPanelState>.new,
    );

    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: QuestionAssistPanel(
        key: panelKey,
        repository: widget.repository,
        projectSlug: slug,
        surface: 'profile',
        questionKey: key,
        fieldKey: key,
        answerType: type,
        currentValue: currentValue(),
        onApply: onApply,
      ),
    );
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
            _assist('name', 'text', () => _name.text, (value) {
              _name.text = value;
            }),
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
            _assist('sector', 'select', () => _sector ?? '', (value) {
              if (sectorLabels.containsKey(value)) {
                setState(() => _sector = value);
              }
            }),
            const SizedBox(height: 14),

            TextFormField(
              controller: _industry,
              decoration: const InputDecoration(
                labelText: 'وصف المجال',
                hintText: 'مدارس أهلية، متجر عطور…',
              ),
              onChanged: (value) => _measure('industry', value),
            ),
            _assist('industry', 'text', () => _industry.text, (value) {
              _industry.text = value;
            }),
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
            _assist('stage', 'select', () => _stage, (value) {
              if (_stages.containsKey(value)) {
                setState(() {
                  _stage = value;
                  _stageTouched = true;
                });
              }
            }),
            const SizedBox(height: 14),

            TextFormField(
              controller: _description,
              decoration: const InputDecoration(
                labelText: 'ماذا يقدم المشروع؟',
                helperText:
                    'اكتب وصفًا مباشرًا يفهمه شخص يتعرف إلى مشروعك للمرة الأولى.',
              ),
              maxLines: 3,
              onChanged: (value) => _measure('description', value),
            ),
            _assist('description', 'textarea', () => _description.text, (value) {
              _description.text = value;
            }),
            const SizedBox(height: 14),

            TextFormField(
              controller: _valueProposition,
              decoration: const InputDecoration(
                labelText: 'لماذا يشتري منك العميل بدل غيرك؟',
                helperText:
                    'اكتب السبب الحقيقي بجملة أو جملتين، مثل: أوصّل في اليوم نفسه بينما يحتاج غيري ثلاثة أيام.',
              ),
              maxLines: 3,
              onChanged: (value) => _measure('value_proposition', value),
            ),
            _assist(
              'value_proposition',
              'textarea',
              () => _valueProposition.text,
              (value) {
                _valueProposition.text = value;
              },
            ),
            const SizedBox(height: 14),

            TextFormField(
              controller: _geography,
              decoration: const InputDecoration(labelText: 'السوق الجغرافي'),
              onChanged: (value) => _measure('geography', value),
            ),
            _assist('geography', 'text', () => _geography.text, (value) {
              _geography.text = value;
            }),
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

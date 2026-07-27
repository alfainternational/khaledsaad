import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import 'models.dart';

class AgencyReportScreen extends StatefulWidget {
  const AgencyReportScreen({
    super.key,
    required this.repository,
    required this.uuid,
    this.initial,
  });

  final PlatformRepository repository;
  final String uuid;
  final AgencyReportDetail? initial;

  @override
  State<AgencyReportScreen> createState() => _AgencyReportScreenState();
}

class _AgencyReportScreenState extends State<AgencyReportScreen> {
  late Future<AgencyReportDetail> _future = widget.initial == null
      ? widget.repository.agencyReport(widget.uuid)
      : Future.value(widget.initial);
  late String _activeUuid = widget.uuid;
  bool _showAgencyBrief = false;
  bool _downloading = false;
  bool _sharing = false;
  bool _regenerating = false;
  AgencyShare? _share;

  void _reload() {
    setState(() {
      _share = null;
      _future = widget.repository.agencyReport(_activeUuid);
    });
  }

  Future<void> _download({required bool agencyBrief}) async {
    setState(() => _downloading = true);

    try {
      final bytes = agencyBrief
          ? await widget.repository.agencyBriefPdf(_activeUuid)
          : await widget.repository.agencyReportPdf(_activeUuid);
      final dir = await getTemporaryDirectory();
      final name = agencyBrief ? 'agency-brief' : 'owner-report';
      final file = File('${dir.path}/$name-$_activeUuid.pdf');
      await file.writeAsBytes(bytes, flush: true);
      await OpenFilex.open(file.path);
    } catch (error) {
      _notify(error.toString());
    } finally {
      if (mounted) setState(() => _downloading = false);
    }
  }

  Future<void> _createShare(List<int> choices) async {
    final days = await showModalBottomSheet<int>(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text(
                'كم يومًا تريد أن يبقى الرابط متاحًا؟',
                style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
              ),
            ),
            for (final option in choices)
              ListTile(
                title: Text('$option يومًا'),
                onTap: () => Navigator.pop(context, option),
              ),
          ],
        ),
      ),
    );
    if (days == null) return;

    setState(() => _sharing = true);
    try {
      final share = await widget.repository.shareAgencyReport(
        _activeUuid,
        days,
      );
      if (mounted) setState(() => _share = share);
      _notify('أصبح رابط موجز الوكالة جاهزًا لمدة $days يومًا.');
    } catch (error) {
      _notify(error.toString());
    } finally {
      if (mounted) setState(() => _sharing = false);
    }
  }

  Future<void> _revokeShare() async {
    setState(() => _sharing = true);
    try {
      final share = await widget.repository.revokeAgencyReportShare(
        _activeUuid,
      );
      if (mounted) setState(() => _share = share);
      _notify('أُلغي الرابط، ولم يعد موجز الوكالة متاحًا من خلاله.');
    } catch (error) {
      _notify(error.toString());
    } finally {
      if (mounted) setState(() => _sharing = false);
    }
  }

  Future<void> _regenerate(AgencyReportDetail report) async {
    setState(() => _regenerating = true);
    try {
      final updated = await widget.repository.generateAgencyReport(
        report.projectSlug,
        report.visibility,
      );
      if (!mounted) return;
      setState(() {
        _activeUuid = updated.uuid;
        _share = updated.share;
        _showAgencyBrief = false;
        _future = Future.value(updated);
      });
      _notify('أُنشئ تقرير جديد بالمعلومات الحالية.');
    } catch (error) {
      _notify(error.toString());
    } finally {
      if (mounted) setState(() => _regenerating = false);
    }
  }

  void _notify(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('تقارير مشروعك')),
      body: FutureBuilder<AgencyReportDetail>(
        future: _future,
        builder: (context, snapshot) => AsyncView(
          snapshot: snapshot,
          onRetry: _reload,
          builder: (report) => ListView(
            padding: const EdgeInsets.all(16),
            children: _document(report),
          ),
        ),
      ),
    );
  }

  List<Widget> _document(AgencyReportDetail report) {
    final agencyDocument = report.agencyBriefDocument;
    final share = _share ?? report.share;

    return [
      const Text(
        'مستندان من نفس معلومات مشروعك، وكل واحد مكتوب للقارئ الذي سيستخدمه.',
        style: TextStyle(color: BrandColors.muted),
      ),
      const SizedBox(height: 12),
      Row(
        children: [
          Expanded(
            child: _showAgencyBrief
                ? OutlinedButton(
                    onPressed: () => setState(() => _showAgencyBrief = false),
                    child: const Text('تقريري'),
                  )
                : FilledButton(onPressed: null, child: const Text('تقريري')),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: _showAgencyBrief
                ? FilledButton(
                    onPressed: null,
                    child: const Text('موجز الوكالة'),
                  )
                : OutlinedButton(
                    onPressed: agencyDocument.isReady
                        ? () => setState(() => _showAgencyBrief = true)
                        : null,
                    child: const Text('موجز الوكالة'),
                  ),
          ),
        ],
      ),
      const SizedBox(height: 12),
      if (!agencyDocument.isReady)
        BrandCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Eyebrow('يحتاج إكمالًا'),
              const SizedBox(height: 6),
              Text(
                agencyDocument.message ??
                    'أكمل المعلومات المطلوبة قبل تسليم موجز للوكالة.',
              ),
            ],
          ),
        ),
      if (report.freshness.isStale) ...[
        const SizedBox(height: 12),
        BrandCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'لديك معلومات أحدث من هذا التقرير',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 6),
              for (final reason in report.freshness.reasons) Text('• $reason'),
              const SizedBox(height: 10),
              FilledButton.icon(
                onPressed: _regenerating ? null : () => _regenerate(report),
                icon: _regenerating
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.refresh),
                label: const Text('أنشئ تقريرًا محدثًا'),
              ),
            ],
          ),
        ),
      ],
      const SizedBox(height: 12),
      FilledButton.icon(
        onPressed: _downloading
            ? null
            : () => _download(agencyBrief: _showAgencyBrief),
        icon: _downloading
            ? const SizedBox(
                width: 18,
                height: 18,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : const Icon(Icons.picture_as_pdf_outlined),
        label: Text(
          _showAgencyBrief ? 'حمّل موجز الوكالة PDF' : 'حمّل تقريرك PDF',
        ),
      ),
      if (_showAgencyBrief) ...[
        const SizedBox(height: 12),
        _shareCard(share),
        ..._agencyBrief(report.agencyBrief),
      ] else
        ..._ownerReport(report.ownerReport),
    ];
  }

  List<Widget> _ownerReport(Map<String, dynamic> owner) {
    final overview = _map(owner['overview']);
    final numbers = _map(owner['numbers']);
    final journey = _map(owner['journey']);
    final readiness = _map(owner['readiness']);
    final beforeAgency = _map(owner['before_agency']);
    final details = _map(owner['private_details']);
    final project = _map(details['project']);
    final audiences = _maps(details['audiences']);
    final assets = _map(details['assets']);
    final tools = _maps(details['tools']);
    final competitors = _map(details['competitors']);
    final evidence = _map(details['evidence']);
    final kpis = _maps(details['kpis']);
    final consultation = _map(details['consultation']);
    final assumptions = _strings(details['assumptions']);
    final differentReadings = _maps(details['different_readings']);
    final plan = _map(details['plan']);
    final behaviour = _map(details['behaviour']);
    final tasks = _map(behaviour['tasks']);
    final highlightedTitles = _maps(
      owner['problems'],
    ).map((item) => item['title']?.toString()).toSet();

    return [
      const SizedBox(height: 20),
      _heading(overview['title']?.toString() ?? 'أين يقف مشروعك الآن؟'),
      BrandCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              overview['description']?.toString() ?? 'هذه صورة مشروعك الحالية.',
            ),
            const SizedBox(height: 8),
            Text(
              'أكبر نقطة تحتاج انتباهك الآن: ${overview['main_issue'] ?? 'تثبيت القياس أولًا'}',
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
          ],
        ),
      ),
      ..._simpleSection('صورة مشروعك الكاملة', [
        _line('المشروع', project['name']),
        _line('المجال', project['industry']),
        _line('مرحلتك الآن', project['stage']),
        _line('السوق الذي تعمل فيه', project['geography']),
        _line('طريقة تحقيق الدخل', project['business_model']),
        _line('الهدف الذي يقود عملك', project['primary_goal']),
        _line('لماذا يختارك العميل؟', project['value_proposition']),
        if (project['website'] != null) _line('الموقع', project['website']),
      ]),
      ..._simpleSection(
        'عملاؤك كما نفهمهم الآن',
        [
          for (final audience in audiences) ...[
            Text(
              audience['name']?.toString() ?? 'شريحة عميل',
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            _line('ما الذي يزعجهم؟', audience['pains']),
            _line('ما الذي يريدونه؟', audience['gains']),
            _line('كيف يتصرفون عادة؟', audience['behaviors']),
            const SizedBox(height: 8),
          ],
        ],
        empty: 'لم تحدد شرائح عملائك بعد. ابدأ بخمس محادثات قصيرة معهم.',
      ),
      ..._simpleSection(
        'أرقامك ببساطة',
        _maps(numbers['rows']).map((row) {
          final value = row['value'];
          return _line(
            row['label']?.toString() ?? 'رقم',
            value == null
                ? 'لا نعرفه حتى الآن'
                : '${row['value']} ${row['unit'] ?? ''}'.trim(),
          );
        }).toList(),
        empty: 'لا توجد أرقام كافية بعد. ابدأ بالقياس قبل زيادة الإنفاق.',
      ),
      ..._simpleSection(
        'ما لديك جاهز وما يحتاج تجهيزًا',
        [
          for (final row in _maps(assets['rows']))
            _line(
              row['label']?.toString() ?? 'حساب أو أصل',
              '${row['status_label'] ?? 'غير معروف'}${(row['detail']?.toString().isNotEmpty ?? false) ? ' — ${row['detail']}' : ''}',
            ),
        ],
        empty: 'لم توثق الأصول والحسابات بعد؛ راجعها قبل بدء أي إنفاق.',
      ),
      ..._simpleSection('أين يتوقف الناس؟', [
        Text(journey['description']?.toString() ?? 'نحتاج معرفة موضع التوقف.'),
        _line(
          'وضع القياس الآن',
          numbers['tracking_label']?.toString() ?? 'غير معروف بعد',
        ),
      ]),
      ..._simpleSection(
        'ماذا قالت كل التشخيصات؟',
        [
          for (final tool in tools) ...[
            Text(
              tool['title']?.toString() ?? 'نتيجة تشخيص',
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            Text(tool['summary']?.toString() ?? 'لا توجد خلاصة كافية بعد.'),
            if (tool['score'] != null)
              _line(
                'القراءة الحالية',
                '${tool['score']} من 100 — ${tool['score_band'] ?? ''}',
              ),
            for (final finding in _maps(tool['findings']))
              if (!highlightedTitles.contains(
                finding['title']?.toString(),
              )) ...[
                Text(
                  finding['title']?.toString() ?? '',
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
                Text(finding['description']?.toString() ?? ''),
                if (finding['evidence'] != null)
                  _line('ما الذي يدعمها؟', finding['evidence']),
                _line(
                  'مدى اعتمادنا عليها',
                  finding['is_assumption'] == true
                      ? 'تحتاج إلى تحقق إضافي'
                      : 'تستند إلى معلومة مسجلة',
                ),
              ],
            const SizedBox(height: 10),
          ],
        ],
        empty: 'لا توجد نتائج تشخيص محفوظة في هذا الإصدار.',
      ),
      ..._cardsSection(
        'أهم ثلاث مشكلات',
        _maps(owner['problems']),
        titleKey: 'title',
        bodyKey: 'description',
        empty: 'لا توجد ثلاث مشكلات مؤكدة بعد؛ نحتاج القياس أولًا.',
      ),
      ..._simpleSection(
        'المؤشرات التي تتابعها',
        [
          for (final kpi in kpis)
            Text(
              '${kpi['name']}: البداية ${kpi['baseline'] ?? 'غير مسجلة'} ${kpi['unit'] ?? ''}، الهدف ${kpi['target'] ?? 'غير مسجل'} ${kpi['unit'] ?? ''}، آخر قراءة ${kpi['latest'] ?? 'لا توجد بعد'}.',
            ),
        ],
        empty: 'لم تسجل مؤشرًا بعد. اختر مؤشرًا يرتبط بهدفك وثبت رقمه الحالي.',
      ),
      ..._simpleSection('المنافسون والمعلومات التي بُني عليها التقرير', [
        if (_maps(competitors['items']).isEmpty)
          const Text('لم تسجل منافسين مؤكدين بعد.')
        else
          for (final competitor in _maps(competitors['items']))
            Text(
              '• ${competitor['name']}${competitor['url'] == null ? '' : ' — ${competitor['url']}'}',
            ),
        const SizedBox(height: 8),
        Text(
          'حجم المعلومات الداعمة: ${evidence['count'] ?? 0} معلومة. تظهر كل معلومة بجوار النتيجة التي تدعمها حتى لا تتكرر.',
        ),
        if (assumptions.isNotEmpty) ...[
          const SizedBox(height: 8),
          const Text(
            'أمور تحتاج إلى تأكيد',
            style: TextStyle(fontWeight: FontWeight.w700),
          ),
          for (final item in assumptions) Text('• $item'),
        ],
      ]),
      if (consultation.isNotEmpty)
        ..._simpleSection('ما سجلته في التشخيص الذكي', [
          for (final answer in _maps(consultation['answers'])) ...[
            Text(
              answer['question']?.toString() ?? '',
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            Text(
              answer['is_unknown'] == true
                  ? 'أجبت بأنك لا تعرفها بعد.'
                  : answer['value']?.toString() ?? 'لم تسجل إجابة.',
            ),
          ],
          for (final inference in _maps(consultation['inferences']))
            _line('قراءة مستخلصة من إجاباتك', inference['statement']),
          for (final item in _maps(consultation['evidence'])) ...[
            Text(
              'معلومة داعمة: ${item['name'] ?? 'ملف مرفوع'}',
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            if (item['text'] != null) Text(item['text'].toString()),
          ],
        ]),
      if (differentReadings.isNotEmpty)
        ..._simpleSection('مقارنة نتائج التشخيصات', [
          for (final reading in differentReadings)
            _line(
              'اختلاف يحتاج حسمًا',
              reading['resolution'] ?? 'راجع السياق قبل اعتماد قراءة واحدة.',
            ),
        ]),
      ..._cardsSection(
        'أمور تحتاج أن تحسمها',
        _maps(owner['conflicts']),
        titleKey: 'question',
        bodyKey: 'why',
        empty: 'لا توجد إجابات متعارضة تحتاج تدخلك الآن.',
      ),
      ..._cardsSection(
        'ما الذي ما زلنا لا نعرفه؟',
        _maps(owner['unknowns']),
        titleKey: 'resolution',
        bodyKey: 'text',
        empty: 'المعلومات الأساسية متاحة حاليًا.',
      ),
      ..._cardsSection(
        'ما يمكنك فعله هذا الأسبوع',
        _maps(owner['this_week']),
        titleKey: 'title',
        bodyKey: 'description',
        trailingKey: 'estimated_time',
        empty: 'ابدأ بأول معلومة ناقصة وحدد لها موعدًا هذا الأسبوع.',
      ),
      ..._beforeAgency(beforeAgency),
      ..._simpleSection('هل أصبح موجز الوكالة جاهزًا؟', [
        Text(readiness['message']?.toString() ?? 'راجع البنود المطلوبة.'),
        for (final item in _maps(readiness['requirements']))
          Text('${item['complete'] == true ? '✓' : '•'} ${item['label']}'),
      ]),
      ..._simpleSection('تفاصيل تساعدك على فهم قدرتك على التنفيذ', [
        Text(
          'أنجزت ${tasks['done'] ?? 0} من ${tasks['total'] ?? 0} مهمة مسجلة. هذا الرقم يساعدك على اختيار خطة تستطيع متابعتها فعلًا.',
        ),
        const SizedBox(height: 8),
        for (final bucket in const {
          '30_days': 'أول 30 يومًا',
          '60_days': 'حتى 60 يومًا',
          '90_days': 'حتى 90 يومًا',
        }.entries) ...[
          Text(
            bucket.value,
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
          if (_maps(plan[bucket.key]).isEmpty)
            const Text('لا توجد خطوة إضافية في هذه الفترة الآن.')
          else
            for (final item in _maps(plan[bucket.key]))
              Text('• ${item['title']}'),
          const SizedBox(height: 6),
        ],
      ]),
    ];
  }

  List<Widget> _beforeAgency(Map<String, dynamic> guide) => [
    const SizedBox(height: 20),
    _heading('قبل أن تتحدث مع أي وكالة'),
    BrandCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'هذا الجزء لك وحدك: يساعدك على مقارنة العروض وحماية حساباتك وبياناتك.',
          ),
          const SizedBox(height: 10),
          const Text(
            'أسئلة المقارنة',
            style: TextStyle(fontWeight: FontWeight.w700),
          ),
          for (final item in _strings(guide['comparison_questions']))
            Text('• $item'),
          const SizedBox(height: 10),
          const Text(
            'علامات الإنذار',
            style: TextStyle(fontWeight: FontWeight.w700),
          ),
          for (final item in _strings(guide['red_flags'])) Text('• $item'),
          const SizedBox(height: 10),
          const Text(
            'ما لا تتنازل عنه',
            style: TextStyle(fontWeight: FontWeight.w700),
          ),
          for (final item in _strings(guide['non_negotiables']))
            Text('• $item'),
        ],
      ),
    ),
  ];

  List<Widget> _agencyBrief(Map<String, dynamic> brief) {
    final project = _map(brief['project']);
    final baseline = _map(brief['baseline']);
    final goal = _map(brief['goal']);
    final scope = _map(brief['scope']);
    final assets = _map(brief['assets']);
    final workflow = _map(brief['workflow']);
    final terms = _map(brief['terms']);
    final proposal = _map(brief['proposal']);
    final budget = _map(proposal['budget']);
    final submission = _map(brief['submission']);

    return [
      ..._simpleSection('المشروع في سطور واضحة', [
        _line('المشروع', project['name']),
        if (project['description'] != null)
          Text(project['description'].toString()),
        _line('المجال', project['industry']),
        _line('السوق', project['geography']),
        _line('المرحلة', project['stage']),
        _line('ما يميّز العرض', project['value_proposition']),
        if (_strings(project['audiences']).isNotEmpty)
          _line('الجمهور', _strings(project['audiences']).join('، ')),
        for (final audience in _maps(project['audience_details'])) ...[
          Text(
            audience['name']?.toString() ?? 'شريحة عميل',
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
          _line('الحاجة أو المشكلة', audience['needs']),
          _line('النتيجة المطلوبة', audience['desired_result']),
          _line('السلوك المعروف', audience['behaviour']),
        ],
        if (_maps(project['competitors']).isNotEmpty) ...[
          const Text(
            'المنافسون المعروفون',
            style: TextStyle(fontWeight: FontWeight.w700),
          ),
          for (final competitor in _maps(project['competitors']))
            Text(
              '• ${competitor['name']}${competitor['tier_label'] == null ? '' : ' — ${competitor['tier_label']}'}',
            ),
        ],
        if (_maps(project['known_context']).isNotEmpty) ...[
          const Text(
            'حقائق مسجلة عن المشروع',
            style: TextStyle(fontWeight: FontWeight.w700),
          ),
          for (final item in _maps(project['known_context']))
            _line(item['label']?.toString() ?? 'معلومة', item['value']),
        ],
      ]),
      ..._simpleSection('خط الأساس', [
        for (final row in _maps(baseline['rows']))
          _line(row['label']?.toString() ?? 'المعلومة', row['value']),
        _line('وضع القياس', baseline['tracking']),
        _line(
          'ما جُرّب سابقًا',
          baseline['previous_attempts'] ?? 'لا توجد تجربة سابقة موثقة',
        ),
        if (baseline['previous_provider'] != null)
          _line('التعامل السابق مع جهة منفذة', baseline['previous_provider']),
        _line(
          'مصدر العملاء الحالي',
          baseline['current_customer_source'] ?? 'غير معروف حتى الآن',
        ),
        for (final kpi in _maps(baseline['kpis']))
          Text(
            '${kpi['name']}: البداية ${kpi['baseline'] ?? 'غير معروفة'} ${kpi['unit'] ?? ''}، والهدف ${kpi['target'] ?? 'غير محدد'} ${kpi['unit'] ?? ''}.',
          ),
      ], empty: 'يبدأ العمل بتثبيت الأرقام الحالية.'),
      ..._simpleSection('الهدف الذي سنعمل عليه', [
        _line('الهدف الأساسي', goal['primary']),
        _line('تعريف النجاح', goal['success_metric']),
        if (goal['period'] != null) _line('خلال 90 يومًا', goal['period']),
      ]),
      ..._simpleSection('النطاق المطلوب', [
        _line('الخدمات', _strings(scope['services']).join('، ')),
        _line(
          'موعد البدء أو الموسم المهم',
          scope['start_window'] ?? 'يُحدد مع الجدول التنفيذي',
        ),
        _line(
          'القيود التي يجب احترامها',
          scope['constraints'] ?? 'لا توجد قيود إضافية موثقة',
        ),
        const Text(
          'خارج النطاق',
          style: TextStyle(fontWeight: FontWeight.w700),
        ),
        for (final item in _strings(scope['out_of_scope'])) Text('• $item'),
      ]),
      ..._simpleSection('الأصول والوصول', [
        for (final row in _maps(assets['rows']))
          _line(
            row['label']?.toString() ?? 'أصل',
            '${row['status_label'] ?? 'غير معروف'}${(row['detail']?.toString().isNotEmpty ?? false) ? ' — ${row['detail']}' : ''}',
          ),
      ], empty: 'تُراجع قائمة الأصول قبل تحديد يوم البدء.'),
      ..._simpleSection('آلية العمل', [
        _line('صاحب القرار', workflow['decision_maker']),
        _line('مدة الاعتماد', workflow['approval_time']),
        _line('من يرد على العملاء', workflow['lead_response_owner']),
        _line('فريق المشروع', workflow['internal_capacity']),
        _line(
          'قيود الدفع للمنصات',
          workflow['payment_constraints'] ?? 'لا توجد قيود موثقة',
        ),
        _line('المراجعة', workflow['review_cadence']),
      ]),
      ..._simpleSection('الملكية وشروط الانتهاء', [
        Text(
          terms['account_ownership']?.toString() ?? 'الحسابات باسم المشروع.',
        ),
        _line('الوضع الحالي', terms['declared_ownership']),
        _line('شكل التعاقد', terms['engagement_model']),
        _line('مدة التعاقد', terms['contract_duration']),
        _line('مرونة الميزانية', terms['budget_flexibility']),
        _line('عند الانتهاء', terms['exit_condition']),
      ]),
      ..._simpleSection('ما يجب أن يتضمنه عرضكم', [
        const Text(
          'الميزانية التي سيُبنى عليها العرض',
          style: TextStyle(fontWeight: FontWeight.w700),
        ),
        _line(
          'المبلغ الشهري المسجل',
          budget['stated_budget'] == null
              ? 'لم يُحدد'
              : '${budget['stated_budget']} ${budget['budget_currency'] ?? ''}',
        ),
        _line(
          'هل يشمل أتعاب الوكالة؟',
          budget['includes_agency_fee'] == true
              ? 'نعم، يشمل الأتعاب والإنفاق معًا'
              : budget['includes_agency_fee'] == false
              ? 'لا، الأتعاب تضاف فوقه'
              : 'لم تُحسم بعد',
        ),
        if (budget['effective_media'] != null)
          _line(
            'المتاح للإعلان بعد البنود المحسوبة',
            '${budget['effective_media']} ${budget['budget_currency'] ?? ''}',
          ),
        if (_map(budget['verdict'])['headline'] != null)
          _line('مدى ملاءمة المبلغ', _map(budget['verdict'])['headline']),
        const SizedBox(height: 8),
        for (final item in _strings(proposal['requirements'])) Text('• $item'),
        const SizedBox(height: 8),
        const Text(
          'جدول التسعير',
          style: TextStyle(fontWeight: FontWeight.w700),
        ),
        for (final row in _maps(proposal['pricing_rows']))
          Text('• ${row['label']}: المبلغ، وما يشمله، وما لا يشمله.'),
      ]),
      ..._simpleSection('موعد وطريقة تسليم العرض', [
        _line('آخر موعد', submission['deadline']),
        _line('طريقة التسليم', submission['method']),
      ]),
    ];
  }

  List<Widget> _simpleSection(
    String title,
    List<Widget> children, {
    String? empty,
  }) => [
    const SizedBox(height: 20),
    _heading(title),
    BrandCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: children.isEmpty
            ? [Text(empty ?? 'لا توجد معلومات بعد.')]
            : children,
      ),
    ),
  ];

  List<Widget> _cardsSection(
    String title,
    List<Map<String, dynamic>> items, {
    required String titleKey,
    required String bodyKey,
    String? trailingKey,
    required String empty,
  }) => [
    const SizedBox(height: 20),
    _heading(title),
    if (items.isEmpty)
      BrandCard(child: Text(empty))
    else
      for (final item in items) ...[
        BrandCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                item[titleKey]?.toString() ?? '',
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 5),
              Text(item[bodyKey]?.toString() ?? ''),
              if (trailingKey != null && item[trailingKey] != null) ...[
                const SizedBox(height: 5),
                Text(
                  'الوقت المتوقع: ${item[trailingKey]}',
                  style: const TextStyle(color: BrandColors.muted),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 8),
      ],
  ];

  Widget _line(String label, dynamic value) => Padding(
    padding: const EdgeInsets.only(bottom: 7),
    child: Text(
      '$label: ${value == null || value.toString().isEmpty ? 'غير محدد حتى الآن' : value}',
    ),
  );

  Widget _shareCard(AgencyShare share) => BrandCard(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'مشاركة موجز الوكالة',
          style: TextStyle(fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 6),
        const Text(
          'الرابط يعرض موجز التكليف فقط، ولا يعرض تقريرك الخاص.',
          style: TextStyle(color: BrandColors.muted),
        ),
        if (share.isLive && share.url != null) ...[
          const SizedBox(height: 8),
          SelectableText(share.url!),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () async {
                    await Clipboard.setData(ClipboardData(text: share.url!));
                    _notify('نُسخ رابط موجز الوكالة.');
                  },
                  icon: const Icon(Icons.copy),
                  label: const Text('انسخ الرابط'),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: OutlinedButton(
                  onPressed: _sharing ? null : _revokeShare,
                  child: const Text('ألغِ الرابط'),
                ),
              ),
            ],
          ),
        ] else ...[
          const SizedBox(height: 10),
          FilledButton.icon(
            onPressed: _sharing
                ? null
                : () => _createShare(share.expiryChoices),
            icon: const Icon(Icons.link),
            label: const Text('أنشئ رابط مشاركة'),
          ),
        ],
      ],
    ),
  );

  Widget _heading(String text) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Text(
      text,
      style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
    ),
  );

  Map<String, dynamic> _map(dynamic value) =>
      value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

  List<Map<String, dynamic>> _maps(dynamic value) =>
      (value as List? ?? const [])
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList();

  List<String> _strings(dynamic value) =>
      (value as List? ?? const []).map((item) => item.toString()).toList();
}

/// قاموس التعريب المركزي لمفاتيح JSON القادمة من الـ API.
///
/// أي شاشة تعرض مفاتيح تقنية (تقارير، دليل المشروع، نتائج الأدوات،
/// حزم التنفيذ، تحليل المدخلات) يجب أن تمر عناوينها من هنا حتى لا
/// تتسرب مسميات إنجليزية خام إلى الواجهة العربية.
class ArLabels {
  ArLabels._();

  static const Map<String, String> _labels = {
    // ── التقرير الشامل ──
    'project': 'المشروع',
    'client': 'العميل',
    'completion': 'نسبة الإكمال',
    'avg quality': 'متوسط اكتمال الأدوات',
    'content quality': 'جودة المحتوى',
    'tools completed': 'الأدوات المنجزة',
    'stages': 'المراحل',
    'stage': 'المرحلة',
    'stage label': 'المرحلة الحالية',
    'gaps': 'الفجوات',
    'audit': 'التدقيق الذكي',
    'diagnosis': 'التشخيص الاستراتيجي',
    'domain plans': 'خطط المجالات',
    'executive summary': 'الملخص التنفيذي',
    'priorities': 'الأولويات',
    'plan': 'الخطة',
    'quick wins 7': 'مكاسب سريعة خلال 7 أيام',
    'improvements 30': 'تحسينات خلال 30 يوماً',
    'strategic 90': 'خطوات استراتيجية خلال 90 يوماً',
    'executive score': 'الدرجة التنفيذية',
    'top problems': 'أهم المشاكل',
    'site unreachable': 'تعذر الوصول للموقع',
    'completed at': 'تاريخ الاكتمال',
    'problems': 'المشاكل',
    'problem': 'المشكلة',
    'cause': 'السبب',
    'solution': 'الحل',
    'covered': 'ما تمت تغطيته',
    'missing': 'الناقص',
    'items': 'العناصر',
    'label': 'العنوان',
    'value': 'القيمة',
    'heading': 'العنوان',
    'headline': 'الخلاصة',
    'points': 'أهم النقاط',
    'bullets': 'أهم النقاط',
    'score': 'الدرجة',
    'tool': 'الأداة',
    'tool name': 'اسم الأداة',
    'tools': 'الأدوات',
    'name': 'الاسم',
    'description': 'الوصف',
    'answers': 'الإجابات',
    'answered at': 'تاريخ الإجابة',
    'completeness': 'نسبة الاكتمال',
    'sector': 'القطاع',
    'market country': 'السوق أو الدولة',
    'primary domain': 'المجال الأساسي',
    'num': 'الرقم',
    'meta': 'بيانات عامة',
    'has answers': 'توجد إجابات',
    'markdown': 'النص الكامل',

    // ── خطط المجالات ──
    'content': 'المحتوى',
    'promotion': 'الترويج',
    'offer': 'العرض',
    'journey': 'رحلة العميل',
    'performance': 'الأداء',

    // ── مجالات التوصيات ──
    'website': 'الموقع الإلكتروني',
    'conversion': 'التحويل',
    'trust': 'الثقة',
    'seo': 'الظهور في البحث',
    'ai visibility': 'الظهور في محركات الذكاء',
    'social': 'السوشيال ميديا',
    'messaging': 'الرسائل التسويقية',
    'pricing': 'التسعير',
    'positioning': 'التمركز',

    // ── تحليل المدخلات ونتائج الأدوات ──
    'verdict': 'الحكم',
    'strategic note': 'ملاحظة استراتيجية',
    'summary': 'الملخص',
    'insight': 'الاستنتاج',
    'insights': 'الاستنتاجات',
    'key point': 'أهم نقطة',
    'recommendations': 'التوصيات',
    'recommendation': 'التوصية',
    'next steps': 'الخطوات التالية',
    'next step': 'الخطوة التالية',
    'strengths': 'نقاط القوة',
    'weaknesses': 'نقاط الضعف',
    'opportunities': 'الفرص',
    'threats': 'التهديدات',
    'risks': 'المخاطر',
    'actions': 'الإجراءات',
    'action': 'الإجراء',
    'evidence': 'الدليل',
    'rationale': 'المبرر',
    'impact': 'الأثر',
    'estimated impact': 'الأثر المتوقع',
    'severity': 'الأهمية',
    'priority': 'الأولوية',
    'title': 'العنوان',
    'details': 'التفاصيل',
    'notes': 'ملاحظات',
    'output': 'المخرج',
    'outputs': 'المخرجات',
    'inputs': 'المدخلات',
    'goal': 'الهدف',
    'goals': 'الأهداف',
    'audience': 'الجمهور',
    'channels': 'القنوات',
    'channel': 'القناة',
    'budget': 'الميزانية',
    'timeline': 'الجدول الزمني',
    'kpis': 'مؤشرات الأداء',
    'kpi': 'مؤشر الأداء',
    'metrics': 'المقاييس',
    'measurement plan': 'خطة القياس',
    'target': 'المستهدف',
    'segments': 'الشرائح',
    'segment': 'الشريحة',
    'persona': 'شخصية العميل',
    'pain points': 'نقاط الألم',
    'benefits': 'الفوائد',
    'features': 'المزايا',
    'differentiators': 'عوامل التميز',
    'competitors': 'المنافسون',
    'competitor': 'المنافس',
    'assumptions': 'الافتراضات',
    'questions': 'الأسئلة',
    'warnings': 'تحذيرات',
    'tasks': 'المهام',
    'task': 'المهمة',
    'assets': 'المخرجات الجاهزة',
    'asset': 'المخرج الجاهز',
    'status': 'الحالة',
    'owner': 'المسؤول',
    'decision': 'القرار',
    'guide markdown': 'دليل الإرشاد',

    // ── حالات وقيم شائعة ──
    'pending': 'معلّق',
    'approved': 'معتمد',
    'rejected': 'مرفوض',
    'completed': 'مكتمل',
    'failed': 'فشل',
    'queued': 'في الانتظار',
    'in progress': 'قيد التنفيذ',
    'in review': 'قيد المراجعة',
    'proposed': 'مقترح',
    'executing': 'قيد التنفيذ',
    'done': 'منجز',
    'archived': 'مؤرشف',
    'high': 'مرتفع',
    'critical': 'حرج',
    'medium': 'متوسط',
    'low': 'منخفض',
    'true': 'نعم',
    'false': 'لا',
    'local': 'محلي',
    'llm': 'ذكاء اصطناعي',
    'quick': 'سريع',
    'advanced': 'متقدم',
    'guided': 'موجّه',
    'structured': 'منظم',
    'expert': 'خبير',

    // ── أنواع عناصر الموافقات ──
    'tool run': 'نتيجة أداة',
    'ai generation': 'مخرج استوديو',
    'execution package': 'حزمة تنفيذ',
  };

  /// أسماء الأدوات الرسمية (مطابقة لقاعدة البيانات) — تُستخدم عندما
  /// يصل كود الأداة الإنجليزي بلا اسم عربي مرافق.
  static const Map<String, String> toolNames = {
    'diagnosis': 'التشخيص',
    'idea-clarity': 'وضوح الفكرة',
    'swot-analysis': 'تحليل SWOT',
    'goal-definition': 'تحديد الهدف',
    'problem-definition': 'تحديد المشكلة',
    'ideal-customer': 'العميل المثالي',
    'market-analysis': 'تحليل السوق',
    'competitor-analysis': 'تحليل المنافسين',
    'positioning': 'التمركز',
    'tagline-builder': 'الجملة التعريفية',
    'offer-builder': 'بناء العرض',
    'pricing-strategy': 'التسعير',
    'value-ladder': 'سلم القيمة',
    'package-builder': 'الحزم',
    'promise-builder': 'الوعد التسويقي',
    'customer-journey': 'رحلة العميل',
    'funnel-builder': 'القمع التسويقي',
    'marketing-plan': 'الخطة التسويقية',
    'content-plan': 'خطة المحتوى',
    'campaign-builder': 'الحملات',
    'follow-up-sequence': 'المتابعة',
    'kpi-tracker': 'KPIs',
    'execution-plan': 'الخطة التنفيذية',
    'performance-review': 'قراءة الأداء',
    'agency-audit': 'تقييم عمل الوكالة',
    'smart-recommendations': 'التوصيات الذكية',
    'growth-priorities': 'أولويات التوسع',
  };

  /// مفاتيح تقنية لا يجب عرضها للمستخدم إطلاقاً.
  static const Set<String> hiddenKeys = {
    'public id',
    'id',
    'code',
    'synthesis source',
    'tool code',
    'workspace id',
    'project id',
    'created by',
    'updated at',
    'created at',
  };

  static String _normalize(String key) => key
      .trim()
      .toLowerCase()
      .replaceAll(RegExp(r'[_\-]+'), ' ')
      .replaceAll(RegExp(r'\s+'), ' ');

  /// هل هذا المفتاح تقني ويجب إخفاؤه؟
  static bool isHidden(String key) => hiddenKeys.contains(_normalize(key));

  /// عرّب المفتاح: قاموس المفاتيح ثم أسماء الأدوات ثم النص نفسه إن كان
  /// عربياً أصلاً، وأخيراً تنظيف الشرطات كخيار أخير.
  static String of(String key) {
    final trimmed = key.trim();
    if (trimmed.isEmpty) return trimmed;
    // نص عربي أصلاً؟ أعده كما هو.
    if (RegExp(r'[؀-ۿ]').hasMatch(trimmed)) return trimmed;

    final normalized = _normalize(trimmed);
    return _labels[normalized] ??
        toolNames[trimmed] ??
        toolNames[normalized.replaceAll(' ', '-')] ??
        trimmed.replaceAll('_', ' ');
  }

  /// عرّب قيمة نصية قادمة من الـ API (حالة، درجة خطورة، مجال...).
  static String value(String raw) => of(raw);
}

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:khaledsaad_app/core/api/api_client.dart';
import 'package:khaledsaad_app/core/api/platform_repository.dart';
import 'package:khaledsaad_app/features/reports/models.dart';
import 'package:khaledsaad_app/features/reports/report_gaps_screen.dart';

/// سدّ الفجوات على الجوال — الشاشة التي تحوّل الإعلان إلى باب.
///
/// حمولات هذه الاختبارات منسوخة حرفيًّا من مخرَج
/// `App\Http\Controllers\Api\V1\ReportGapController`. إن تغيّر شكل المتحكّم
/// ولم تُحدَّث النماذج، تفشل هذه الاختبارات — وهذا هو الغرض: منع انحراف
/// التطبيق عن الويب بصمت.
void main() {
  test('نموذج الفجوة يقرأ حمولة ReportGapController::index', () {
    final gap = ReportGap.fromJson(const {
      'key': 'value_proposition',
      'label': 'لماذا يشتري منك العميل بدل غيرك؟',
      'help': null,
      'source': 'profile',
      'why': 'بدونه لا يمكن الحكم على تمايزك.',
      'type': 'textarea',
      'options': <Map<String, dynamic>>[],
      'surface': 'profile',
    });

    expect(gap.key, 'value_proposition');
    expect(gap.why, 'بدونه لا يمكن الحكم على تمايزك.');
    // السطح يقرّر أين يُطلب العون: سؤال الملف يُعان بلا تشغيل.
    expect(gap.surface, 'profile');
    expect(gap.type, 'textarea');
  });

  test('فجوة الاختيار تصل بخياراتها موسَّعة لا بمفتاح معجم', () {
    final gap = ReportGap.fromJson(const {
      'key': 'stage',
      'label': 'أين نشاطك الآن؟',
      'type': 'select',
      'options': [
        {'value': 'growth', 'label': 'يحقق مبيعات حاليًا'},
        {'value': 'scale', 'label': 'يحقق مبيعات ويستعد للتوسع'},
      ],
      'surface': 'profile',
    });

    expect(gap.options, hasLength(2));
    expect(gap.options.first.value, 'growth');
    expect(gap.options.first.label, 'يحقق مبيعات حاليًا');
  });

  testWidgets('الشاشة تعرض كل فجوة بسؤالها وسبب أهميتها', (tester) async {
    final repository = _GapRepository();

    await _pump(tester, repository);

    expect(find.text('لماذا يشتري منك العميل بدل غيرك؟'), findsOneWidget);
    expect(
      find.text('لماذا يهم: بدونه لا يمكن الحكم على تمايزك.'),
      findsOneWidget,
    );
    expect(find.text('أين نشاطك الآن؟'), findsOneWidget);
    // سؤال الاختيار يصل قائمةً منسدلة لا مربع نصّ.
    expect(find.byType(DropdownButtonFormField<String>), findsOneWidget);
  });

  testWidgets('ما يكتبه صاحب النشاط يصل الخادم بمفاتيح التقرير نفسها', (
    tester,
  ) async {
    final repository = _GapRepository();

    await _pump(tester, repository);

    await tester.enterText(
      find.byType(TextField).first,
      'نوصّل الطلب في نفس اليوم داخل الرياض',
    );
    await tester.tap(find.text('احفظ ما كتبته'));
    await tester.pumpAndSettle();

    expect(repository.sent, [
      {'value_proposition': 'نوصّل الطلب في نفس اليوم داخل الرياض'},
    ]);

    /*
     * ما تبقّى يأتي من ردّ الحفظ نفسه، فتُحدَّث الشاشة بلا طلب ثانٍ. وبعد
     * الحفظ يبقى سؤال واحد فقط — لو بقي الاثنان لظنّ المستخدم أن حفظه ضاع.
     */
    expect(find.text('لماذا يشتري منك العميل بدل غيرك؟'), findsNothing);
    expect(find.text('أين نشاطك الآن؟'), findsOneWidget);
  });

  testWidgets('الحفظ بلا إجابة لا يصل الخادم أصلًا', (tester) async {
    final repository = _GapRepository();

    await _pump(tester, repository);

    await tester.tap(find.text('احفظ ما كتبته'));
    await tester.pumpAndSettle();

    // الخادم يرفض الفراغ بـ422، لكن طلبًا يُرسَل ليُرفض هدرٌ وحصّة.
    expect(repository.sent, isEmpty);
    expect(find.text('اكتب إجابة واحدة على الأقل قبل الحفظ.'), findsOneWidget);
  });

  testWidgets('تعذّر الجلب يُقال صراحةً ولا يُترك دوّارًا لا ينتهي', (
    tester,
  ) async {
    final repository = _GapRepository()..failOnRead = true;

    await _pump(tester, repository);

    expect(find.byType(CircularProgressIndicator), findsNothing);
    expect(find.text('تعذّر جلب المعلومات الناقصة الآن.'), findsOneWidget);
  });

  testWidgets('تقرير بلا نقص يقول ذلك بدل صفحة فارغة', (tester) async {
    final repository = _GapRepository()..open = const [];

    await _pump(tester, repository);

    expect(find.text('لا توجد معلومات ناقصة في هذا التقرير.'), findsOneWidget);
  });
}

/// نافذة ضيّقة وطويلة: الضيق يبقي تخطيط الجوال، والطول يُظهر النموذج كاملًا
/// حتى زر الحفظ. النافذة الافتراضية (800×600) تقصّ الزر خارج `ListView` فلا
/// يُبنى أصلًا، فيفشل الاختبار على القصّ لا على السلوك.
Future<void> _pump(WidgetTester tester, PlatformRepository repository) async {
  tester.view.physicalSize = const Size(420, 2400);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);

  await tester.pumpWidget(
    MaterialApp(
      locale: const Locale('ar'),
      supportedLocales: const [Locale('ar'), Locale('en'), Locale('fr')],
      localizationsDelegates: GlobalMaterialLocalizations.delegates,
      home: ReportGapsScreen(
        repository: repository,
        reportId: 7,
        projectName: 'متجر أفق',
      ),
    ),
  );
  await tester.pumpAndSettle();
}

class _GapRepository extends PlatformRepository {
  _GapRepository() : super(ApiClient());

  bool failOnRead = false;
  final sent = <Map<String, String>>[];

  List<ReportGap> open = const [
    ReportGap(
      key: 'value_proposition',
      label: 'لماذا يشتري منك العميل بدل غيرك؟',
      why: 'بدونه لا يمكن الحكم على تمايزك.',
      surface: 'profile',
    ),
    ReportGap(
      key: 'stage',
      label: 'أين نشاطك الآن؟',
      type: 'select',
      options: [
        ReportGapOption(value: 'growth', label: 'يحقق مبيعات حاليًا'),
        ReportGapOption(value: 'scale', label: 'يحقق مبيعات ويستعد للتوسع'),
      ],
      surface: 'profile',
    ),
  ];

  @override
  Future<List<ReportGap>> reportGaps(int id) async {
    if (failOnRead) throw Exception('offline');

    return open;
  }

  @override
  Future<List<ReportGap>> saveReportGaps(
    int id,
    Map<String, String> answers,
  ) async {
    sent.add(answers);

    // الخادم يعيد ما تبقّى مفتوحًا — كما يفعل `update` فعلًا.
    return open
        .where((gap) => !answers.containsKey(gap.key))
        .toList(growable: false);
  }
}

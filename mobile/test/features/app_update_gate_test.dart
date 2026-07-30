import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:khaledsaad_app/core/api/api_client.dart';
import 'package:khaledsaad_app/core/api/api_exception.dart';
import 'package:khaledsaad_app/core/api/app_update_gate.dart';
import 'package:khaledsaad_app/core/widgets/app_update_screen.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// بوابة الحد الأدنى لإصدار التطبيق — الطرف المستقبِل.
///
/// الحمولة أدناه منسوخة من `EnsureSupportedAppVersion`. العطل الذي يحرسه هذا
/// الملف: الخادم يحرس عقد `api/v1` ويردّ ٤٢٦ برسالة عربية ورابط تنزيل،
/// والتطبيق لم يكن يعرف الرمز فيعرضه «تعذر إكمال الطلب». والحدّ مرفوع فعلًا
/// على الإنتاج — فأول رفع تالٍ يصيب مستخدمين حقيقيين.
void main() {
  const payload = {
    'message': 'هذا الإصدار من التطبيق لم يعد مدعومًا. حدّثه للمتابعة.',
    'error': 'app_update_required',
    'meta': {
      'min_supported_build': 6,
      'your_build': 5,
      'download_url': 'https://khaledsaad.net/download/android',
    },
  };

  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    // TokenStore يقرأ من SharedPreferences؛ بلا تهيئتها يفشل كل نداء بخطأ
    // شبكة زائف قبل أن يبلغ منطق البوابة أصلًا.
    SharedPreferences.setMockInitialValues({});
    AppUpdateGate.instance.reset();
  });
  tearDown(() => AppUpdateGate.instance.reset());

  ApiClient clientReturning(int status, Object? body) => ApiClient(
    client: MockClient(
      (_) async => http.Response(
        body == null ? '' : jsonEncode(body),
        status,
        headers: {'content-type': 'application/json'},
      ),
    ),
  );

  test('الرمز ٤٢٦ يرفع البوابة بنصّ الخادم لا بنصّ التطبيق', () async {
    final api = clientReturning(426, payload);

    await expectLater(
      api.get('/public/mobile-app'),
      throwsA(isA<ApiException>()),
    );

    final requirement = AppUpdateGate.instance.requirement.value;

    expect(requirement, isNotNull);
    expect(requirement!.message, payload['message']);
    expect(requirement.downloadUrl, 'https://khaledsaad.net/download/android');

    // الرقم مع أساسه (§١٣): «حدّث» وحدها لا تقول للمستخدم أين هو.
    expect(requirement.basis, 'نسختك رقم 5، وأقل نسخة مدعومة 6.');
  });

  test('الاستثناء يُرمى كما هو بعد رفع البوابة', () async {
    final api = clientReturning(426, payload);

    // الشاشة المستدعية تتوقف كما تتوقف عند أي خطأ، والبوابة تغطّيها فوقها.
    // استبدال الاستثناء كان سيوجب على كل مستدعٍ معرفة الحالة الجديدة.
    try {
      await api.get('/public/mobile-app');
      fail('كان يجب أن يُرمى استثناء.');
    } on ApiException catch (error) {
      expect(error.statusCode, 426);
      expect(error.message, payload['message']);
    }
  });

  test('أخطاء أخرى لا ترفع البوابة', () async {
    final api = clientReturning(422, {'message': 'راجع البيانات المدخلة.'});

    await expectLater(api.post('/projects'), throwsA(isA<ApiException>()));

    expect(AppUpdateGate.instance.isBlocked, isFalse);
  });

  test('حمولة بلا meta تمنع الوصول ولا تسقط', () async {
    // المنع واقع حتى لو نقصت التفاصيل؛ أسوأ ما يحدث عرضٌ بلا رقم بناء.
    final api = clientReturning(426, {'message': 'غير مدعوم.'});

    await expectLater(api.get('/me'), throwsA(isA<ApiException>()));

    expect(AppUpdateGate.instance.isBlocked, isTrue);
    expect(AppUpdateGate.instance.requirement.value!.basis, isNull);
    expect(AppUpdateGate.instance.requirement.value!.downloadUrl, isNull);
  });

  test('البوابة ترتفع ولا تنخفض داخل الجلسة', () async {
    final api = clientReturning(426, payload);
    await expectLater(api.get('/me'), throwsA(isA<ApiException>()));

    // نداء ناجح لاحق لا يفتح تطبيقًا رفض الخادمُ عقدَه: الحدّ لا يُخفَّض في
    // منتصف جلسة، وتطبيق نصفه معطّل أسوأ من شاشة صريحة.
    final second = clientReturning(200, {'data': {}});
    await second.get('/me');

    expect(AppUpdateGate.instance.isBlocked, isTrue);
  });

  testWidgets('الشاشة الحاجبة تعرض السبب والأساس وزر التنزيل', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: AppUpdateScreen(
          requirement: AppUpdateRequirement.fromJson(payload),
        ),
      ),
    );

    expect(find.text('يلزم تحديث التطبيق'), findsOneWidget);
    expect(find.text(payload['message'] as String), findsOneWidget);
    expect(find.text('نسختك رقم 5، وأقل نسخة مدعومة 6.'), findsOneWidget);
    expect(find.text('نزّل النسخة الجديدة'), findsOneWidget);

    // لا زرّ «لاحقًا»: لا شيء يعمل بعده.
    expect(find.text('لاحقًا'), findsNothing);
  });

  testWidgets('غياب الرابط يقول من أين تأخذها ولا يخترعه', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: AppUpdateScreen(
          requirement: AppUpdateRequirement.fromJson(const {
            'message': 'غير مدعوم.',
          }),
        ),
      ),
    );

    expect(find.text('نزّل النسخة الجديدة'), findsNothing);
    expect(
      find.textContaining('نزّل النسخة الجديدة من الموقع'),
      findsOneWidget,
    );
  });
}

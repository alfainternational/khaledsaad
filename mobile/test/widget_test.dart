import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:khaledsaad_app/core/api/api_client.dart';
import 'package:khaledsaad_app/core/api/platform_repository.dart';
import 'package:khaledsaad_app/main.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  setUp(() => SharedPreferences.setMockInitialValues({}));

  testWidgets('التطبيق يبدأ من شاشة الحساب عندما لا توجد جلسة', (tester) async {
    await tester.pumpWidget(KhaledSaadApp(repository: PlatformRepository(ApiClient())));
    await tester.pumpAndSettle();

    expect(find.text('ابدأ تشخيص مشروعك'), findsOneWidget);
    expect(find.text('البريد الإلكتروني'), findsOneWidget);
  });

  testWidgets('واجهة التطبيق تعمل باتجاه RTL مثل الويب', (tester) async {
    await tester.pumpWidget(KhaledSaadApp(repository: PlatformRepository(ApiClient())));
    await tester.pumpAndSettle();

    // أقرب Directionality فوق محتوى الشاشة هو الذي يحكم الاتجاه فعليًا،
    // لا أول واحد في الشجرة الذي يضيفه MaterialApp نفسه.
    final directionality = tester.widget<Directionality>(
      find
          .ancestor(
            of: find.text('ابدأ تشخيص مشروعك'),
            matching: find.byType(Directionality),
          )
          .first,
    );

    expect(directionality.textDirection, TextDirection.rtl);
  });

  testWidgets('يمكن التبديل بين إنشاء حساب وتسجيل الدخول', (tester) async {
    await tester.pumpWidget(KhaledSaadApp(repository: PlatformRepository(ApiClient())));
    await tester.pumpAndSettle();

    await tester.tap(find.text('لديك حساب؟ سجّل الدخول'));
    await tester.pumpAndSettle();

    expect(find.text('أهلًا بعودتك'), findsOneWidget);
  });

  testWidgets('لا يُرسل النموذج ببيانات ناقصة', (tester) async {
    await tester.pumpWidget(KhaledSaadApp(repository: PlatformRepository(ApiClient())));
    await tester.pumpAndSettle();

    await tester.tap(find.widgetWithText(FilledButton, 'إنشاء الحساب'));
    await tester.pumpAndSettle();

    expect(find.text('الاسم مطلوب.'), findsOneWidget);
  });
}

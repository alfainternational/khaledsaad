import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:khaledsaad_app/core/api/api_client.dart';
import 'package:khaledsaad_app/core/api/platform_repository.dart';
import 'package:khaledsaad_app/main.dart';
import 'package:khaledsaad_app/features/admin/admin_hub_screen.dart';
import 'package:khaledsaad_app/features/auth/password_reset_screen.dart';
import 'package:khaledsaad_app/features/growth/growth_hub_screen.dart';
import 'package:khaledsaad_app/features/public/shared_report_screen.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  setUp(() => SharedPreferences.setMockInitialValues({}));

  testWidgets('التطبيق يبدأ من واجهة الزائر عندما لا توجد جلسة', (
    tester,
  ) async {
    await tester.pumpWidget(
      KhaledSaadApp(repository: PlatformRepository(ApiClient())),
    );
    await tester.pumpAndSettle();

    expect(find.text('ابدأ من واقع مشروعك'), findsOneWidget);
    expect(find.text('ابدأ تشخيص مشروعك'), findsOneWidget);
    expect(find.text('أنشئ حسابك واحفظ تقدمك'), findsOneWidget);
  });

  testWidgets('واجهة التطبيق تعمل باتجاه RTL مثل الويب', (tester) async {
    await tester.pumpWidget(
      KhaledSaadApp(repository: PlatformRepository(ApiClient())),
    );
    await tester.pumpAndSettle();

    // أقرب Directionality فوق محتوى الشاشة هو الذي يحكم الاتجاه فعليًا،
    // لا أول واحد في الشجرة الذي يضيفه MaterialApp نفسه.
    final directionality = tester.widget<Directionality>(
      find
          .ancestor(
            of: find.text('ابدأ من واقع مشروعك'),
            matching: find.byType(Directionality),
          )
          .first,
    );

    expect(directionality.textDirection, TextDirection.rtl);
  });

  testWidgets('يمكن فتح تسجيل الدخول من واجهة الزائر', (tester) async {
    await tester.pumpWidget(
      KhaledSaadApp(repository: PlatformRepository(ApiClient())),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('تسجيل الدخول'));
    await tester.pumpAndSettle();

    expect(find.text('أهلًا بعودتك'), findsOneWidget);
  });

  testWidgets('لا يُرسل النموذج ببيانات ناقصة', (tester) async {
    await tester.pumpWidget(
      KhaledSaadApp(repository: PlatformRepository(ApiClient())),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('أنشئ حسابك واحفظ تقدمك'));
    await tester.pumpAndSettle();

    await tester.tap(find.widgetWithText(FilledButton, 'أنشئ حسابك وتابع'));
    await tester.pumpAndSettle();

    expect(find.text('الاسم مطلوب.'), findsOneWidget);
  });

  testWidgets('رابط استعادة كلمة المرور يفتح نموذج التعيين الكامل', (
    tester,
  ) async {
    final repository = PlatformRepository(ApiClient());
    await tester.pumpWidget(
      MaterialApp(
        home: PasswordResetScreen(
          repository: repository,
          token: 'secure-token',
          email: 'owner@example.com',
          onComplete: () {},
        ),
      ),
    );

    expect(find.text('تعيين كلمة مرور جديدة'), findsOneWidget);
    expect(find.text('owner@example.com'), findsOneWidget);
    expect(find.text('حفظ كلمة المرور'), findsOneWidget);
  });

  testWidgets('رابط التقرير المشترك يفتح شاشة التقرير العامة', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: SharedReportScreen(
          repository: PlatformRepository(ApiClient()),
          token: 'shared-token',
        ),
      ),
    );

    expect(find.text('تقرير مشترك'), findsOneWidget);
  });

  testWidgets('مركز النمو يعرض كل أدوات التحسين للمشروع', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: GrowthHubScreen(
          repository: PlatformRepository(ApiClient()),
          projectSlug: 'project',
          projectName: 'مشروعي',
        ),
      ),
    );

    expect(find.text('متابعة التحسين'), findsOneWidget);
    expect(find.text('مؤشرات القياس'), findsOneWidget);
    expect(find.text('الظهور في محركات الإجابة'), findsOneWidget);
    expect(find.text('مختبر الجمهور'), findsOneWidget);
  });

  testWidgets('لوحة الإدارة تعرض بوابة عمليات الإدارة الكاملة', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: AdminHubScreen(repository: PlatformRepository(ApiClient())),
      ),
    );

    expect(find.text('لوحة الإدارة'), findsOneWidget);
    expect(find.byTooltip('إضافة أداة'), findsOneWidget);
  });
}

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:ksgrowth_mobile/features/shared/widgets/animated_app_background.dart';
import 'package:ksgrowth_mobile/features/welcome/welcome_page.dart';

void main() {
  testWidgets('WelcomePage تعرض وعد المنتج وزر البدء', (tester) async {
    await tester.pumpWidget(
      const GetMaterialApp(
        home: Directionality(
          textDirection: TextDirection.rtl,
          child: WelcomePage(),
        ),
      ),
    );

    expect(find.text('منصة تزيد وضوح التسويق وتنقله للتنفيذ'), findsOneWidget);
    expect(find.text('ابدأ الآن'), findsOneWidget);
    expect(find.text('تسجيل الدخول'), findsOneWidget);
    expect(find.byType(AnimatedAppBackground), findsOneWidget);
  });
}

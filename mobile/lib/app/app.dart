import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:get/get.dart';

import '../features/shared/widgets/global_assistant_button.dart';
import 'bindings/initial_binding.dart';
import 'routes/app_pages.dart';
import 'routes/app_routes.dart';
import 'theme/app_theme.dart';

/// جذر التطبيق — عربي RTL افتراضاً، مع سمتي فاتح/داكن.
class KsGrowthApp extends StatelessWidget {
  const KsGrowthApp({super.key});

  @override
  Widget build(BuildContext context) {
    return GetMaterialApp(
      title: 'KS Growth',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      darkTheme: AppTheme.dark,
      themeMode: ThemeMode.system,
      initialBinding: InitialBinding(),
      initialRoute: Routes.splash,
      getPages: AppPages.routes,
      locale: const Locale('ar'),
      fallbackLocale: const Locale('ar'),
      supportedLocales: const [Locale('ar'), Locale('en')],
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      // تتبّع المسار الحالي ليقرّر زر المساعد العائم ظهوره.
      routingCallback: (routing) {
        final current = routing?.current;
        if (current != null && current.isNotEmpty) {
          GlobalAssistantButton.currentRoute.value = current;
        }
      },
      // فرض الاتجاه RTL على كامل الشجرة + زر المساعد الذكي العائم
      // فوق كل الشاشات بعد تسجيل الدخول.
      builder: (context, child) => Directionality(
        textDirection: TextDirection.rtl,
        child: Stack(
          children: [
            child ?? const SizedBox.shrink(),
            const GlobalAssistantButton(),
          ],
        ),
      ),
    );
  }
}

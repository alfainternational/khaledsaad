import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:get/get.dart';

import '../features/shared/widgets/app_lock_gate.dart';
import '../features/shared/widgets/connectivity_banner.dart';
import '../features/shared/widgets/global_assistant_button.dart';
import 'bindings/initial_binding.dart';
import 'routes/app_pages.dart';
import 'routes/app_routes.dart';
import 'theme/app_theme.dart';
import 'theme/theme_controller.dart';

/// جذر التطبيق — عربي RTL افتراضاً، مع سمتي فاتح/داكن.
class KsGrowthApp extends StatelessWidget {
  const KsGrowthApp({super.key});

  @override
  Widget build(BuildContext context) {
    final themeCtrl = Get.find<ThemeController>();
    return GetMaterialApp(
      title: 'KS Growth',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      darkTheme: AppTheme.dark,
      themeMode: themeCtrl.themeMode.value,
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
      builder: (context, child) {
        // سقف تكبير الخط لمنع تراكب العناوين (clamp)، مع احترام تفضيل المستخدم.
        final media = MediaQuery.of(context);
        return Obx(
          () => MediaQuery(
            data: media.copyWith(
              textScaler: themeCtrl.cappedScaler(media.textScaler),
            ),
            child: Directionality(
              textDirection: TextDirection.rtl,
              child: Stack(
                children: [
                  child ?? const SizedBox.shrink(),
                  const GlobalAssistantButton(),
                  const ConnectivityBanner(),
                  const AppLockGate(),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}

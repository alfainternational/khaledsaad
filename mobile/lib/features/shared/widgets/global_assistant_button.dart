import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../data/services/session_service.dart';
import '../../tool_runner/widgets/ai_chat_sheet.dart';

/// زر المساعد الذكي العائم — يظهر دائماً أسفل يسار التطبيق بعد تسجيل
/// الدخول، فوق كل الشاشات، دون أن يغطي المحتوى (هوامش ثابتة + حجم صغير).
class GlobalAssistantButton extends StatelessWidget {
  const GlobalAssistantButton({super.key});

  /// المسار الحالي — يُحدَّث من routingCallback في GetMaterialApp.
  static final currentRoute = Routes.splash.obs;

  /// شاشات ما قبل تسجيل الدخول والإعداد — لا يظهر الزر فيها.
  static const _hiddenRoutes = {
    Routes.splash,
    Routes.welcome,
    Routes.explore,
    Routes.login,
    Routes.register,
    Routes.forgotPassword,
    Routes.onboarding,
  };

  @override
  Widget build(BuildContext context) {
    final session = Get.find<SessionService>();
    return Obx(() {
      final route = currentRoute.value;
      // لا يظهر فوق شاشات ما قبل الدخول، ولا فوق ورقة/حوار مفتوح كي لا
      // يطفو فوق المحتوى المنبثق. (routingCallback يحدّث المسار عند فتح
      // الأوراق أيضاً، فنتحقق من حالة الـ overlay مباشرةً.)
      final overlayOpen =
          (Get.isBottomSheetOpen ?? false) || (Get.isDialogOpen ?? false);
      final visible = session.isAuthenticated.value &&
          !_hiddenRoutes.contains(route) &&
          !overlayOpen;
      if (!visible) return const SizedBox.shrink();

      // على شاشة الهيكل يوجد شريط تنقّل سفلي — نرفع الزر فوقه كي لا يتراكب.
      final overNavBar = route == Routes.home;
      // في واجهة RTL: end = يسار الشاشة (كما طلب التصميم).
      return PositionedDirectional(
        bottom: overNavBar ? 24 + kBottomNavigationBarHeight : 24,
        end: 16,
        child: SafeArea(
          child: Semantics(
            button: true,
            label: 'المستشار الذكي',
            child: FloatingActionButton(
              heroTag: 'global_ai_assistant',
              tooltip: 'المستشار الذكي',
              onPressed: _openChat,
              child: const Icon(Icons.support_agent),
            ),
          ),
        ),
      );
    });
  }

  void _openChat() {
    Get.bottomSheet(
      const AiChatSheet(),
      isScrollControlled: true,
      backgroundColor: Theme.of(Get.context!).colorScheme.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
    );
  }
}

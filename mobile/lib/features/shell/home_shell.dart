import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';

import '../account/account_page.dart';
import '../dashboard/dashboard_page.dart';
import '../projects/projects_page.dart';
import '../studio/studio_page.dart';

/// مُتحكّم الهيكل الرئيسي — يحفظ التبويب النشط ويتيح التبديل برمجياً
/// (مثلاً من اختصارات اللوحة) مع الحفاظ على حالة كل تبويب.
class HomeShellController extends GetxController {
  final index = 0.obs;

  void go(int i) {
    if (i == index.value) return;
    HapticFeedback.selectionClick();
    index.value = i;
  }
}

/// الهيكل الجذري بعد الدخول: شريط تنقّل سفلي ثابت بين الأقسام الرئيسية
/// (اللوحة/المشاريع/الاستوديو/الحساب) مع IndexedStack لحفظ حالة كل قسم.
class HomeShell extends StatelessWidget {
  const HomeShell({super.key});

  @override
  Widget build(BuildContext context) {
    // متين ضد إعادة البناء: لا نُعيد إنشاء المتحكّم (كي لا يُصفَّر التبويب).
    final c = Get.isRegistered<HomeShellController>()
        ? Get.find<HomeShellController>()
        : Get.put(HomeShellController(), permanent: true);
    const pages = [
      DashboardPage(),
      ProjectsPage(),
      StudioPage(),
      AccountPage(),
    ];

    return Obx(
      () => Scaffold(
        body: IndexedStack(index: c.index.value, children: pages),
        bottomNavigationBar: NavigationBar(
          selectedIndex: c.index.value,
          onDestinationSelected: c.go,
          destinations: const [
            NavigationDestination(
              icon: Icon(Icons.dashboard_outlined),
              selectedIcon: Icon(Icons.dashboard),
              label: 'اللوحة',
            ),
            NavigationDestination(
              icon: Icon(Icons.folder_outlined),
              selectedIcon: Icon(Icons.folder),
              label: 'مشاريعي',
            ),
            NavigationDestination(
              icon: Icon(Icons.auto_awesome_outlined),
              selectedIcon: Icon(Icons.auto_awesome),
              label: 'الاستوديو',
            ),
            NavigationDestination(
              icon: Icon(Icons.person_outline),
              selectedIcon: Icon(Icons.person),
              label: 'حسابي',
            ),
          ],
        ),
      ),
    );
  }
}

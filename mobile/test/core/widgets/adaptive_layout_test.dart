import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:khaledsaad_app/core/widgets/adaptive_layout.dart';

void main() {
  void setScreenSize(WidgetTester tester, Size size) {
    tester.view.physicalSize = size;
    tester.view.devicePixelRatio = 1;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });
  }

  testWidgets('AdaptiveSplit stacks the aside after content on phones', (
    tester,
  ) async {
    setScreenSize(tester, const Size(390, 844));
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: AdaptiveSplit(main: Text('main'), aside: Text('aside')),
        ),
      ),
    );

    final main = tester.getTopLeft(find.text('main'));
    final aside = tester.getTopLeft(find.text('aside'));

    expect(aside.dy, greaterThan(main.dy));
  });

  testWidgets('AdaptiveMetricGrid uses four columns on expanded widths', (
    tester,
  ) async {
    setScreenSize(tester, const Size(1280, 900));
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: AdaptiveMetricGrid(
            children: List.generate(4, (index) => Text('$index')),
          ),
        ),
      ),
    );

    expect(
      tester.getTopLeft(find.text('0')).dy,
      tester.getTopLeft(find.text('3')).dy,
    );
  });

  testWidgets('AdaptivePage constrains reading content on expanded widths', (
    tester,
  ) async {
    setScreenSize(tester, const Size(1280, 900));
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: AdaptivePage(
            family: AdaptivePageFamily.reading,
            child: Text('content'),
          ),
        ),
      ),
    );

    expect(
      tester.getSize(find.byKey(const ValueKey('adaptive-page-content'))).width,
      lessThanOrEqualTo(736),
    );
  });

  testWidgets('AdaptiveActionBar stacks actions on compact widths', (
    tester,
  ) async {
    setScreenSize(tester, const Size(390, 844));
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: AdaptiveActionBar(
            children: [Text('primary'), Text('secondary')],
          ),
        ),
      ),
    );

    expect(
      tester.getTopLeft(find.text('secondary')).dy,
      greaterThan(tester.getTopLeft(find.text('primary')).dy),
    );
  });

  testWidgets('AdaptiveScaffold supplies the shared outer page padding', (
    tester,
  ) async {
    setScreenSize(tester, const Size(390, 844));
    await tester.pumpWidget(
      const MaterialApp(
        home: AdaptiveScaffold(
          family: AdaptivePageFamily.operational,
          body: Align(alignment: Alignment.topLeft, child: Text('content')),
        ),
      ),
    );

    expect(
      tester.getTopLeft(find.text('content')).dx,
      greaterThanOrEqualTo(16),
    );
    expect(
      tester.getTopLeft(find.text('content')).dy,
      greaterThanOrEqualTo(24),
    );
  });

  test('every feature screen uses the shared adaptive layout contract', () {
    const expectedFamilies = {
      'account/billing_screen.dart': AdaptivePageFamily.operational,
      'account/notifications_screen.dart': AdaptivePageFamily.operational,
      'admin/admin_hub_screen.dart': AdaptivePageFamily.operational,
      'agency_reports/agency_report_screen.dart': AdaptivePageFamily.reading,
      'agency_reports/agency_reports_screen.dart':
          AdaptivePageFamily.operational,
      'auth/auth_screen.dart': AdaptivePageFamily.form,
      'auth/password_reset_request_screen.dart': AdaptivePageFamily.form,
      'auth/password_reset_screen.dart': AdaptivePageFamily.form,
      'consultations/consultation_screen.dart': AdaptivePageFamily.form,
      'growth/growth_hub_screen.dart': AdaptivePageFamily.operational,
      'projects/dashboard_screen.dart': AdaptivePageFamily.operational,
      'projects/project_form_screen.dart': AdaptivePageFamily.form,
      'projects/project_screen.dart': AdaptivePageFamily.operational,
      'projects/tasks_screen.dart': AdaptivePageFamily.operational,
      'public/legal_screen.dart': AdaptivePageFamily.reading,
      // شاشة تشغيلية: درجة ومحاور وقائمة إصلاح، لا نصّ يُقرأ ولا نموذج يُملأ.
      'readiness/readiness_screen.dart': AdaptivePageFamily.operational,
      'public/public_home_screen.dart': AdaptivePageFamily.operational,
      'public/public_tool_screen.dart': AdaptivePageFamily.operational,
      'public/shared_report_screen.dart': AdaptivePageFamily.reading,
      'reports/report_screen.dart': AdaptivePageFamily.reading,
      'tools/run_status_screen.dart': AdaptivePageFamily.form,
      'tools/run_wizard_screen.dart': AdaptivePageFamily.form,
      'tools/tool_catalog_screen.dart': AdaptivePageFamily.operational,
    };
    final screens =
        Directory('lib/features')
            .listSync(recursive: true)
            .whereType<File>()
            .where((file) => file.path.endsWith('_screen.dart'))
            .toList()
          ..sort((left, right) => left.path.compareTo(right.path));

    // العدد يُشتقّ من السجل لا يُكتب مرتين: رقم ثابت هنا يعني أن كل شاشة
    // جديدة تُسقط الاختبار برسالة عن طول قائمة، لا عن الشاشة التي لم تُسجَّل.
    expect(screens, hasLength(expectedFamilies.length));

    for (final screen in screens) {
      final relativePath = screen.path
          .replaceAll('\\', '/')
          .split('lib/features/')
          .last;
      final family = expectedFamilies[relativePath];

      expect(family, isNotNull, reason: relativePath);

      expect(
        screen.readAsStringSync(),
        contains('core/widgets/adaptive_layout.dart'),
        reason: screen.path,
      );
      expect(
        screen.readAsStringSync(),
        contains('AdaptivePageFamily.${family!.name}'),
        reason: screen.path,
      );
    }
  });

  test('detail and report screens use main and contextual regions', () {
    final project = File(
      'lib/features/projects/project_screen.dart',
    ).readAsStringSync();
    final report = File(
      'lib/features/reports/report_screen.dart',
    ).readAsStringSync();

    expect(project, contains('AdaptiveSplit('));
    expect(report, contains('AdaptiveSplit('));
    expect(report, contains('AdaptiveActionBar('));
  });
}

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
}

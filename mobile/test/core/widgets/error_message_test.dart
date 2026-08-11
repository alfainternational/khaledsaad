import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:khaledsaad_app/core/api/api_exception.dart';
import 'package:khaledsaad_app/core/widgets/common.dart';

void main() {
  test('keeps actionable server messages and hides internal exceptions', () {
    expect(
      userErrorMessage(const ApiException('راجع البيانات المدخلة.')),
      'راجع البيانات المدخلة.',
    );
    expect(
      userErrorMessage(StateError('type cast failed')),
      'حدث خلل غير متوقع. أعد المحاولة، وإن استمر حدّث التطبيق.',
    );
  });

  testWidgets('AsyncView never exposes an internal exception to the user', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: AsyncView<String>(
          snapshot: AsyncSnapshot.withError(
            ConnectionState.done,
            StateError('type cast failed'),
          ),
          onRetry: () {},
          builder: Text.new,
        ),
      ),
    );

    expect(find.textContaining('type cast failed'), findsNothing);
    expect(
      find.text('حدث خلل غير متوقع. أعد المحاولة، وإن استمر حدّث التطبيق.'),
      findsOneWidget,
    );
  });
}

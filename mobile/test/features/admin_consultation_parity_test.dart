import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:khaledsaad_app/core/api/api_client.dart';
import 'package:khaledsaad_app/core/api/platform_repository.dart';
import 'package:khaledsaad_app/core/theme/app_theme.dart';
import 'package:khaledsaad_app/features/admin/admin_consultations_screen.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  setUp(() => SharedPreferences.setMockInitialValues({'api_token': 'token'}));

  testWidgets('admin consultation catalog mirrors published and draft states', (
    tester,
  ) async {
    final client = MockClient(
      (_) async => http.Response(
        jsonEncode({
          'data': [
            {
              'id': 1,
              'name': 'الاستشارة التسويقية الذكية',
              'current_version_id': 10,
              'versions': [
                {
                  'id': 11,
                  'version': 2,
                  'status': 'draft',
                  'is_current': false,
                },
                {
                  'id': 10,
                  'version': 1,
                  'status': 'published',
                  'is_current': true,
                },
              ],
            },
          ],
        }),
        200,
        headers: {'content-type': 'application/json'},
      ),
    );

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.build(),
        home: AdminConsultationsScreen(
          repository: PlatformRepository(ApiClient(client: client)),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('الاستشارة التسويقية الذكية'), findsOneWidget);
    expect(find.text('الإصدار 2'), findsOneWidget);
    expect(find.text('مسودة قابلة للتحرير'), findsOneWidget);
    expect(find.text('الإصدار 1'), findsOneWidget);
    expect(find.text('منشور مقفل'), findsOneWidget);
  });
}

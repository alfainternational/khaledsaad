import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:khaledsaad_app/core/api/api_client.dart';
import 'package:khaledsaad_app/core/api/platform_repository.dart';
import 'package:khaledsaad_app/core/theme/app_theme.dart';
import 'package:khaledsaad_app/features/portfolio/portfolio_screen.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  setUp(() => SharedPreferences.setMockInitialValues({'api_token': 'token'}));

  testWidgets(
    'portfolio renders the same measured and unmeasured states as web',
    (tester) async {
      final client = MockClient(
        (_) async => http.Response(
          jsonEncode({
            'data': {
              'workspace': {'id': 1, 'name': 'وكالتي'},
              'summary': {
                'total': 2,
                'measured': 1,
                'unmeasured': 1,
                'average_score': 64,
                'declining': 0,
              },
              'projects': [
                {
                  'project': {'id': 2, 'slug': 'new', 'name': 'عميل بلا قياس'},
                  'industry': 'تعليم',
                  'measured': false,
                  'maturity_score': null,
                  'score_coverage': 0,
                  'trend': {
                    'direction': 'unknown',
                    'delta': null,
                    'reason': 'قياس واحد فقط.',
                  },
                },
                {
                  'project': {'id': 3, 'slug': 'measured', 'name': 'عميل مقيس'},
                  'industry': 'تجارة',
                  'measured': true,
                  'maturity_score': 64,
                  'score_coverage': 0.5,
                  'trend': {'direction': 'flat', 'delta': 0, 'reason': null},
                },
              ],
            },
          }),
          200,
          headers: {'content-type': 'application/json'},
        ),
      );

      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.build(),
          home: PortfolioScreen(
            repository: PlatformRepository(ApiClient(client: client)),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('وكالتي · محفظة'), findsOneWidget);
      expect(find.text('عميل بلا قياس'), findsOneWidget);
      expect(find.text('لم يُقَس بعد'), findsOneWidget);
      expect(find.text('عميل مقيس'), findsOneWidget);
      expect(find.text('64'), findsNWidgets(2));
    },
  );
}

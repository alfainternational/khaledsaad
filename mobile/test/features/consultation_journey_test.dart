import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:khaledsaad_app/core/api/api_client.dart';
import 'package:khaledsaad_app/core/api/platform_repository.dart';
import 'package:khaledsaad_app/features/consultations/consultation_screen.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  testWidgets('supports multiple, numeric, text, review and confirm states', (
    tester,
  ) async {
    SharedPreferences.setMockInitialValues({});
    var answer = 0;
    final client = MockClient((request) async {
      Map<String, dynamic> data;
      if (request.method == 'POST' &&
          request.url.path.endsWith('/projects/store/consultations')) {
        data = session(
          question: question('START-11', 'ما القيود؟', 'multiselect', [
            'وقت محدود',
            'فريق صغير',
          ]),
        );
      } else if (request.method == 'PUT') {
        answer++;
        data = switch (answer) {
          1 => session(
            question: question('BUDGET', 'ما الميزانية؟', 'number', []),
          ),
          2 => session(question: question('DETAIL', 'اشرح التحدي', 'text', [])),
          _ => session(
            status: 'review',
            question: null,
            canConfirm: true,
            facts: [
              {'label': 'التحدي', 'value': 'ضعف التحويل'},
            ],
          ),
        };
      } else if (request.method == 'POST' &&
          request.url.path.endsWith('/confirm')) {
        data = session(
          status: 'analysis_queued',
          question: null,
          message: 'نبني التقرير الآن',
        );
      } else {
        data = session(
          question: question('START-11', 'ما القيود؟', 'multiselect', [
            'وقت محدود',
          ]),
        );
      }
      return http.Response(
        jsonEncode({'data': data}),
        200,
        headers: {'content-type': 'application/json; charset=utf-8'},
      );
    });
    final repository = PlatformRepository(ApiClient(client: client));

    await tester.pumpWidget(
      MaterialApp(
        home: ConsultationScreen(repository: repository, projectSlug: 'store'),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('ما القيود؟'), findsOneWidget);
    await tester.tap(find.text('وقت محدود'));
    await tester.tap(find.text('احفظ وتابع'));
    await tester.pumpAndSettle();

    expect(find.text('ما الميزانية؟'), findsOneWidget);
    await tester.enterText(find.byType(TextField), '12000');
    await tester.tap(find.text('احفظ وتابع'));
    await tester.pumpAndSettle();

    expect(find.text('اشرح التحدي'), findsOneWidget);
    await tester.enterText(
      find.byType(TextField),
      'ضعف التحويل في صفحة الهبوط',
    );
    // السؤال المفتوح صار أطول (صوت + قياس حيّ + اقتراح تحته)، فالزر قد يكون
    // خارج نافذة العرض في الاختبار — نمرّر إليه كما يفعل المستخدم قبل النقر.
    await tester.ensureVisible(find.text('احفظ وتابع'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('احفظ وتابع'));
    await tester.pumpAndSettle();

    expect(find.text('راجع ما فهمناه'), findsOneWidget);
    expect(find.textContaining('ضعف التحويل'), findsOneWidget);
    await tester.drag(find.byType(ListView), const Offset(0, -700));
    await tester.pumpAndSettle();
    await tester.tap(
      find.widgetWithText(FilledButton, 'أكد وابدأ التحليل الشامل'),
    );
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));
    expect(find.text('نبني التقرير الآن'), findsOneWidget);
  });
}

Map<String, dynamic> question(
  String key,
  String text,
  String type,
  List<String> options,
) => {
  'key': key,
  'text': text,
  'type': type,
  'options': options.map((value) => {'value': value, 'label': value}).toList(),
  'required': true,
  'allow_unknown': true,
  'allow_skip': false,
  'sensitive': false,
};

Map<String, dynamic> session({
  String status = 'active',
  Map<String, dynamic>? question,
  bool canConfirm = false,
  String message = 'نفهم مشروعك',
  List<Map<String, dynamic>> facts = const [],
}) => {
  'uuid': 'session-1',
  'status': status,
  'depth': 'standard',
  'project': {'slug': 'store', 'name': 'متجر'},
  'progress': {
    'answered': 3,
    'limit': 35,
    'percent': 10,
    'label': 'نفهم مشروعك',
  },
  'question': question,
  'scope': [],
  'conflicts': [],
  'review': {
    'facts': facts,
    'estimates': [],
    'unknowns': [],
    'assumptions': [],
    'conflicts': [],
  },
  'status_message': message,
  'can_confirm': canConfirm,
  'report_uuid': null,
};

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:khaledsaad_app/core/api/api_client.dart';
import 'package:khaledsaad_app/core/api/platform_repository.dart';
import 'package:khaledsaad_app/features/experience/learning_application_screen.dart';
import 'package:khaledsaad_app/features/experience/learning_dashboard_screen.dart';

void main() {
  testWidgets('the learning dashboard opens its one recommended application', (
    tester,
  ) async {
    final repository = _LearningRepository();

    await tester.pumpWidget(
      MaterialApp(
        locale: const Locale('en'),
        supportedLocales: const [Locale('ar'), Locale('en'), Locale('fr')],
        localizationsDelegates: GlobalMaterialLocalizations.delegates,
        home: LearningDashboardScreen(
          repository: repository,
          account: const {
            'active_experience': 'learning',
            'enabled_experiences': ['learning'],
          },
          onChanged: () {},
          onLogout: () {},
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Start application'), findsOneWidget);
    await tester.tap(find.text('Start application'));
    await tester.pumpAndSettle();

    expect(find.byType(LearningApplicationScreen), findsOneWidget);
    expect(find.text('Test your current marketing approach'), findsOneWidget);
    expect(repository.openedKeys, ['marketing-reality-check']);
  });

  testWidgets('a deep-linked application saves each answer and resumes it', (
    tester,
  ) async {
    final repository = _LearningRepository();

    await tester.pumpWidget(
      MaterialApp(
        locale: const Locale('en'),
        supportedLocales: const [Locale('ar'), Locale('en'), Locale('fr')],
        localizationsDelegates: GlobalMaterialLocalizations.delegates,
        home: LearningApplicationScreen(
          repository: repository,
          exerciseKey: 'marketing-reality-check',
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Question 1 of 2'), findsOneWidget);
    await tester.enterText(
      find.byType(TextFormField),
      'I publish two practical posts every week and track qualified leads.',
    );
    tester.testTextInput.hide();
    await tester.pumpAndSettle();
    await tester.drag(find.byType(ListView), const Offset(0, -240));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save and continue'));
    await tester.pumpAndSettle();

    expect(repository.savedQuestion, 'current_actions');
    expect(find.text('Question 2 of 2'), findsOneWidget);
  });
}

class _LearningRepository extends PlatformRepository {
  _LearningRepository() : super(ApiClient());

  final openedKeys = <String>[];
  String? savedQuestion;
  final answers = <String, dynamic>{};

  @override
  Future<Map<String, dynamic>> marketingLearningOverview() async => {
    'progress': {'completed': 0, 'total': 42},
    'next': {
      'key': 'marketing-reality-check',
      'title': 'Test your current marketing approach',
      'reason': 'Start here',
      'duration_minutes': 8,
      'deliverable': 'A useful result',
    },
  };

  @override
  Future<Map<String, dynamic>> marketingLearningApplication(String key) async {
    openedKeys.add(key);
    return {
      'exercise': {
        'key': key,
        'title': 'Test your current marketing approach',
        'purpose': 'Separate useful marketing from busywork.',
        'deliverable': 'A clear current-state assessment.',
        'duration_minutes': 8,
        'questions': [
          {
            'key': 'current_actions',
            'label': 'What do you do each week?',
            'help': 'Describe real actions.',
            'example': 'Two posts and one follow-up.',
            'type': 'textarea',
            'min': 20,
          },
          {
            'key': 'business_result',
            'label': 'What result do you expect?',
            'help': 'Name one observable result.',
            'example': 'Five qualified conversations.',
            'type': 'textarea',
            'min': 20,
          },
        ],
      },
      'attempt': {'status': 'draft', 'answers': answers},
    };
  }

  @override
  Future<Map<String, dynamic>> saveMarketingLearningAnswer(
    String exerciseKey,
    String questionKey,
    dynamic answer,
  ) async {
    savedQuestion = questionKey;
    answers[questionKey] = answer;
    return {
      'attempt': {'status': 'draft', 'answers': answers},
      'next_question_key': 'business_result',
    };
  }
}

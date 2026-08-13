import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:khaledsaad_app/core/api/api_client.dart';
import 'package:khaledsaad_app/core/api/api_exception.dart';
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

  testWidgets(
    'an application can optionally select and retain an owned project',
    (tester) async {
      final repository = _LearningRepository(withProjects: true);

      await tester.pumpWidget(
        MaterialApp(
          locale: const Locale('ar'),
          supportedLocales: const [Locale('ar'), Locale('en'), Locale('fr')],
          localizationsDelegates: GlobalMaterialLocalizations.delegates,
          home: LearningApplicationScreen(
            repository: repository,
            exerciseKey: 'marketing-reality-check',
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('بدون مشروع'), findsOneWidget);
      await tester.tap(find.byKey(const Key('learning-project-selector')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('متجر المستخدم').last);
      await tester.pumpAndSettle();

      expect(repository.openedProjectIds, [null, 7]);

      await tester.enterText(
        find.byType(TextFormField).last,
        'أراجع رحلة الشراء في متجر المستخدم وأربط النتيجة بهذا المشروع.',
      );
      tester.testTextInput.hide();
      await tester.pumpAndSettle();
      final saveButton = find.widgetWithText(
        FilledButton,
        'احفظ وانتقل للتالي',
      );
      await tester.ensureVisible(saveButton);
      await tester.pumpAndSettle();
      await tester.tap(saveButton);
      await tester.pumpAndSettle();

      expect(repository.savedProjectId, 7);
    },
  );

  testWidgets('an older project response cannot overwrite the latest load', (
    tester,
  ) async {
    final repository = _DelayedLearningRepository();

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

    final selector = tester.widget<DropdownButtonFormField<int>>(
      find.byKey(const Key('learning-project-selector')),
    );
    selector.onChanged!(7);
    selector.onChanged!(8);
    await tester.pump();

    repository.complete(8, 'Latest project answer');
    await tester.pumpAndSettle();
    expect(
      tester.widget<TextFormField>(find.byType(TextFormField)).controller!.text,
      'Latest project answer',
    );

    repository.complete(7, 'Stale project answer');
    await tester.pump();
    expect(
      tester.widget<TextFormField>(find.byType(TextFormField)).controller!.text,
      'Latest project answer',
    );
  });

  testWidgets('a completed load does not update a disposed application', (
    tester,
  ) async {
    final repository = _DisposedLoadRepository();

    await tester.pumpWidget(
      MaterialApp(
        home: LearningApplicationScreen(
          repository: repository,
          exerciseKey: 'marketing-reality-check',
        ),
      ),
    );
    await tester.pump();
    await tester.pumpWidget(const SizedBox());

    repository.complete();
    await tester.pump();

    expect(tester.takeException(), isNull);
  });

  testWidgets('a completed application is read-only', (tester) async {
    final repository = _LearningRepository(status: 'completed');

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

    expect(
      find.text('This application is complete and its answers are read-only.'),
      findsOneWidget,
    );
    expect(find.byType(TextFormField), findsNothing);
    expect(find.text('Save and continue'), findsNothing);
    expect(find.text('Submit for review'), findsNothing);
  });

  testWidgets('a review dispatch error is surfaced to the learner', (
    tester,
  ) async {
    const message =
        'تعذر بدء المراجعة الآن. إجاباتك محفوظة ويمكنك إعادة المحاولة.';
    final repository = _LearningRepository(reviewFailure: message);
    repository.answers.addAll({
      'current_actions': 'أنشر محتوى عمليًا كل أسبوع وأتابع أثره.',
      'business_result': 'أريد محادثات بيع مؤهلة قابلة للقياس.',
    });

    await tester.pumpWidget(
      MaterialApp(
        locale: const Locale('ar'),
        supportedLocales: const [Locale('ar'), Locale('en'), Locale('fr')],
        localizationsDelegates: GlobalMaterialLocalizations.delegates,
        home: LearningApplicationScreen(
          repository: repository,
          exerciseKey: 'marketing-reality-check',
        ),
      ),
    );
    await tester.pumpAndSettle();

    final reviewButton = find.widgetWithText(FilledButton, 'أرسل للمراجعة');
    await tester.drag(find.byType(ListView), const Offset(0, -320));
    await tester.pumpAndSettle();
    await tester.tap(reviewButton);
    await tester.pumpAndSettle();

    expect(find.text(message), findsOneWidget);
  });
}

class _LearningRepository extends PlatformRepository {
  _LearningRepository({
    this.withProjects = false,
    this.status = 'draft',
    this.reviewFailure,
  }) : super(ApiClient());

  final bool withProjects;
  final String status;
  final String? reviewFailure;
  final openedKeys = <String>[];
  final openedProjectIds = <int?>[];
  String? savedQuestion;
  int? savedProjectId;
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
  Future<Map<String, dynamic>> marketingLearningApplication(
    String key, {
    int? projectId,
  }) async {
    openedKeys.add(key);
    openedProjectIds.add(projectId);
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
      'attempt': {'status': status, 'answers': answers},
      'project': projectId == null
          ? null
          : {'id': projectId, 'name': 'متجر المستخدم', 'slug': 'user-store'},
      'project_choices': withProjects
          ? [
              {'id': 7, 'name': 'متجر المستخدم', 'slug': 'user-store'},
            ]
          : <Map<String, dynamic>>[],
    };
  }

  @override
  Future<Map<String, dynamic>> saveMarketingLearningAnswer(
    String exerciseKey,
    String questionKey,
    dynamic answer, {
    int? projectId,
  }) async {
    savedQuestion = questionKey;
    savedProjectId = projectId;
    answers[questionKey] = answer;
    return {
      'attempt': {'status': 'draft', 'answers': answers},
      'next_question_key': 'business_result',
    };
  }

  @override
  Future<Map<String, dynamic>> reviewMarketingLearningApplication(
    String exerciseKey, {
    int? projectId,
  }) async {
    if (reviewFailure != null) {
      throw ApiException(reviewFailure!);
    }

    return {
      'attempt': {'status': 'queued', 'answers': answers},
    };
  }
}

class _DelayedLearningRepository extends PlatformRepository {
  _DelayedLearningRepository() : super(ApiClient());

  final _pending = <int, Completer<Map<String, dynamic>>>{};

  @override
  Future<Map<String, dynamic>> marketingLearningApplication(
    String key, {
    int? projectId,
  }) {
    if (projectId == null) {
      return Future.value(_applicationData(projectChoices: const [7, 8]));
    }

    return _pending
        .putIfAbsent(projectId, Completer<Map<String, dynamic>>.new)
        .future;
  }

  void complete(int projectId, String answer) {
    _pending[projectId]!.complete(
      _applicationData(
        projectId: projectId,
        projectChoices: const [7, 8],
        answers: {
          'current_actions': 'Saved first answer',
          'business_result': answer,
        },
      ),
    );
  }
}

class _DisposedLoadRepository extends PlatformRepository {
  _DisposedLoadRepository() : super(ApiClient());

  final _pending = Completer<Map<String, dynamic>>();

  @override
  Future<Map<String, dynamic>> marketingLearningApplication(
    String key, {
    int? projectId,
  }) => _pending.future;

  void complete() => _pending.complete(
    _applicationData(answers: const {'current_actions': 'Late answer'}),
  );
}

Map<String, dynamic> _applicationData({
  int? projectId,
  List<int> projectChoices = const [],
  Map<String, dynamic> answers = const {},
  String status = 'draft',
}) => {
  'exercise': {
    'key': 'marketing-reality-check',
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
        'min': 1,
      },
      {
        'key': 'business_result',
        'label': 'What result do you expect?',
        'help': 'Name one observable result.',
        'example': 'Five qualified conversations.',
        'type': 'textarea',
        'min': 1,
      },
    ],
  },
  'attempt': {'status': status, 'answers': answers},
  'project': projectId == null
      ? null
      : {'id': projectId, 'name': 'Project $projectId'},
  'project_choices': projectChoices
      .map((id) => {'id': id, 'name': 'Project $id'})
      .toList(),
};

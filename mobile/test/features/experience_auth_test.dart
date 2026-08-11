import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:khaledsaad_app/core/api/api_client.dart';
import 'package:khaledsaad_app/core/api/api_exception.dart';
import 'package:khaledsaad_app/core/api/platform_repository.dart';
import 'package:khaledsaad_app/features/auth/auth_screen.dart';
import 'package:khaledsaad_app/features/experience/experience_shell.dart';
import 'package:khaledsaad_app/features/experience/experience_selection_screen.dart';
import 'package:khaledsaad_app/features/experience/learning_dashboard_screen.dart';
import 'package:khaledsaad_app/features/projects/dashboard_screen.dart';
import 'package:khaledsaad_app/features/projects/models.dart';
import 'package:khaledsaad_app/features/tools/engagement.dart';
import 'package:khaledsaad_app/features/tools/models.dart';

void main() {
  testWidgets('registration offers semantic business and learning choices', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        locale: const Locale('ar'),
        supportedLocales: const [Locale('ar'), Locale('en'), Locale('fr')],
        localizationsDelegates: GlobalMaterialLocalizations.delegates,
        home: AuthScreen(
          repository: PlatformRepository(ApiClient()),
          onAuthenticated: () {},
        ),
      ),
    );

    expect(find.text('أريد تحسين تسويق مشروعي'), findsOneWidget);
    expect(find.text('أريد تعلّم التسويق بالتطبيق'), findsOneWidget);
    expect(find.byType(RadioGroup<String>), findsOneWidget);
  });

  testWidgets(
    'experience copy follows English locale through one translation layer',
    (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          locale: const Locale('en'),
          supportedLocales: const [Locale('ar'), Locale('en'), Locale('fr')],
          localizationsDelegates: GlobalMaterialLocalizations.delegates,
          home: AuthScreen(
            repository: PlatformRepository(ApiClient()),
            onAuthenticated: () {},
          ),
        ),
      );

      expect(find.text('What do you want to do now?'), findsOneWidget);
      expect(
        find.text('I want to improve my project marketing'),
        findsOneWidget,
      );
    },
  );

  testWidgets('French experience copy is LTR and comes from the same layer', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        locale: const Locale('fr'),
        supportedLocales: const [Locale('ar'), Locale('en'), Locale('fr')],
        localizationsDelegates: GlobalMaterialLocalizations.delegates,
        home: AuthScreen(
          repository: PlatformRepository(ApiClient()),
          onAuthenticated: () {},
        ),
      ),
    );

    expect(find.text('Que souhaitez-vous faire maintenant ?'), findsOneWidget);
    expect(
      Directionality.of(tester.element(find.byType(AuthScreen))),
      TextDirection.ltr,
    );
  });

  testWidgets('learning deep link preselects learning during registration', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        locale: const Locale('ar'),
        supportedLocales: const [Locale('ar'), Locale('en'), Locale('fr')],
        localizationsDelegates: GlobalMaterialLocalizations.delegates,
        home: AuthScreen(
          repository: PlatformRepository(ApiClient()),
          initialExperience: 'learning',
          onAuthenticated: () {},
        ),
      ),
    );

    expect(
      tester
          .widget<RadioGroup<String>>(find.byType(RadioGroup<String>))
          .groupValue,
      'learning',
    );
  });

  test('activation and plan errors remain distinct client states', () {
    const activation = ApiException(
      'Activate first',
      code: 'experience_not_enabled',
      action: 'activate_experience',
    );
    const upgrade = ApiException(
      'Upgrade first',
      code: 'feature_not_available',
      action: 'upgrade_plan',
    );

    expect(activation.needsExperienceActivation, isTrue);
    expect(activation.needsPlanUpgrade, isFalse);
    expect(upgrade.needsExperienceActivation, isFalse);
    expect(upgrade.needsPlanUpgrade, isTrue);
  });

  testWidgets('shell resolves different navigation from active experience', (
    tester,
  ) async {
    final learningRepository = _ExperienceRepository('learning');
    await tester.pumpWidget(
      MaterialApp(
        locale: const Locale('en'),
        supportedLocales: const [Locale('ar'), Locale('en'), Locale('fr')],
        localizationsDelegates: GlobalMaterialLocalizations.delegates,
        home: ExperienceShell(
          key: const ValueKey('learning-shell'),
          repository: learningRepository,
          onLogout: () {},
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.byType(LearningDashboardScreen), findsOneWidget);
    expect(find.byType(DashboardScreen), findsNothing);

    await tester.pumpWidget(
      MaterialApp(
        home: ExperienceShell(
          key: const ValueKey('business-shell'),
          repository: _ExperienceRepository('business'),
          onLogout: () {},
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.byType(DashboardScreen), findsOneWidget);
    expect(find.byType(LearningDashboardScreen), findsNothing);
  });

  testWidgets(
    'learning deep link asks a business account to activate learning',
    (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          locale: const Locale('en'),
          supportedLocales: const [Locale('ar'), Locale('en'), Locale('fr')],
          localizationsDelegates: GlobalMaterialLocalizations.delegates,
          home: ExperienceShell(
            repository: _ExperienceRepository('business'),
            requestedExperience: 'learning',
            onLogout: () {},
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.byType(ExperienceSelectionScreen), findsOneWidget);
      expect(find.text('I want to learn marketing by doing'), findsOneWidget);
      expect(find.text('I want to improve my project marketing'), findsNothing);
      expect(find.text('Activate'), findsOneWidget);
      expect(find.byType(DashboardScreen), findsNothing);
    },
  );
}

class _ExperienceRepository extends PlatformRepository {
  _ExperienceRepository(this.active) : super(ApiClient());

  final String active;

  @override
  Future<Map<String, dynamic>> me() async => {
    'active_experience': active,
    'enabled_experiences': [active],
    'is_admin': false,
  };

  @override
  Future<Map<String, dynamic>> marketingLearningOverview() async => {
    'progress': {'completed': 0, 'total': 42},
    'next': {
      'title': 'Next application',
      'reason': 'Start here',
      'duration_minutes': 10,
      'deliverable': 'A useful result',
    },
  };

  @override
  Future<List<ProjectCard>> projects() async => [];

  @override
  Future<List<ToolCard>> tools() async => [];

  @override
  Future<List<ResumeCard>> unfinished() async => [];
}

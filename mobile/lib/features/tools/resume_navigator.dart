import 'package:flutter/material.dart';

import '../../core/api/platform_repository.dart';
import '../reports/report_screen.dart';
import 'engagement.dart';
import 'run_status_screen.dart';
import 'run_wizard_screen.dart';

/// وجهة واحدة للاستئناف.
///
/// الخادم يقرر الحالة (`target`)، والتطبيق يترجمها إلى شاشة. بهذا يذهب
/// المستخدم إلى نفس المكان الذي يذهب إليه في الويب بالضبط.
abstract final class ResumeNavigator {
  static Future<void> open(
    BuildContext context,
    PlatformRepository repository, {
    required String target,
    String? runUuid,
    int? reportId,
  }) async {
    try {
      final screen = await _resolve(repository, target, runUuid, reportId);

      if (screen == null || !context.mounted) return;

      await Navigator.of(context).push(MaterialPageRoute(builder: (_) => screen));
    } catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error.toString())),
        );
      }
    }
  }

  static Future<Widget?> _resolve(
    PlatformRepository repository,
    String target,
    String? runUuid,
    int? reportId,
  ) async {
    switch (target) {
      case 'report':
        if (reportId == null) return null;
        return ReportScreen(repository: repository, reportId: reportId);

      case 'status':
        if (runUuid == null) return null;
        return RunStatusScreen(repository: repository, run: await repository.progress(runUuid));

      case 'wizard':
        if (runUuid == null) return null;
        return RunWizardScreen(repository: repository, run: await repository.run(runUuid));

      default:
        return null;
    }
  }

  static Future<void> openCard(
    BuildContext context,
    PlatformRepository repository,
    ResumeCard card,
  ) =>
      open(
        context,
        repository,
        target: card.isDraft ? 'wizard' : 'status',
        runUuid: card.runUuid,
      );
}

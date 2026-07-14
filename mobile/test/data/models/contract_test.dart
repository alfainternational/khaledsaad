import 'package:flutter_test/flutter_test.dart';
import 'package:ksgrowth_mobile/data/models/collab_models.dart';
import 'package:ksgrowth_mobile/data/models/project_model.dart';
import 'package:ksgrowth_mobile/data/models/studio_models.dart';
import 'package:ksgrowth_mobile/data/models/workspace_model.dart';

/// اختبارات عقد: تتأكّد أن النماذج تطابق أشكال JSON التي يُصدرها الباك اند،
/// فتمسك أي انحراف بين Laravel Resources وطبقة الموبايل قبل الإصدار.
void main() {
  group('WorkspaceModel', () {
    test('يطابق WorkspaceSummaryResource مع الدور والصلاحيات', () {
      final w = WorkspaceModel.fromJson({
        'public_id': 'ws_123',
        'name': 'مساحتي',
        'type': 'agency',
        'status': 'active',
        'role': 'owner',
        'entitlements': {
          'outputs.can_export': true,
          'white_label': false,
          'ai_studio.monthly_credits': 100,
        },
      });

      expect(w.publicId, 'ws_123');
      expect(w.isAgency, isTrue);
      expect(w.role, 'owner');
      expect(w.hasEntitlement('outputs.can_export'), isTrue);
      expect(w.hasEntitlement('white_label'), isFalse);
      expect(w.hasEntitlement('ai_studio.monthly_credits'), isTrue); // 100 > 0
    });
  });

  group('ProjectModel', () {
    test('يطابق ProjectResource مع عميل الوكالة', () {
      final p = ProjectModel.fromJson({
        'public_id': 'prj_9',
        'name': 'إطلاق المنتج',
        'stage': 3,
        'status': 'active',
        'sector': 'saas',
        'client': {'public_id': 'cli_1', 'name': 'عميل'},
      });

      expect(p.publicId, 'prj_9');
      expect(p.stage, 3);
      expect(p.client?.name, 'عميل');
    });
  });

  group('StudioGeneration', () {
    test('حالة قيد التوليد: ليست جاهزة ولا فاشلة', () {
      final g = StudioGeneration.fromJson({
        'public_id': 'gen_1',
        'status': 'processing',
        'template': {'name': 'إعلان'},
        'output': null,
      });
      expect(g.isProcessing, isTrue);
      expect(g.isReady, isFalse);
      expect(g.isFailed, isFalse);
      expect(g.templateName, 'إعلان');
    });

    test('حالة جاهزة عند وجود مخرج', () {
      final g = StudioGeneration.fromJson({
        'public_id': 'gen_2',
        'status': 'completed',
        'output': 'نص المخرج',
      });
      expect(g.isReady, isTrue);
      expect(g.isProcessing, isFalse);
    });
  });

  group('ApprovalModel', () {
    test('نوع حزمة التنفيذ له تسمية عربية', () {
      // execution_package يُصدره الباك اند فعلاً ويجب أن تكون له تسمية.
      expect(ApprovalModel.itemTypeLabels['execution_package'], 'حزمة تنفيذ');
      expect(ApprovalModel.itemTypeLabels.containsKey('tool_run'), isTrue);
      expect(ApprovalModel.itemTypeLabels.containsKey('ai_generation'), isTrue);

      final a = ApprovalModel.fromJson({
        'id': 5,
        'item_type': 'execution_package',
        'status': 'pending',
        'project': {'name': 'مشروع'},
      });
      expect(a.itemType, 'execution_package');
      expect(a.projectName, 'مشروع');
    });
  });
}

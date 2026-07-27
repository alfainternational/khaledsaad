import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:khaledsaad_app/core/api/api_client.dart';
import 'package:khaledsaad_app/core/api/platform_repository.dart';
import 'package:khaledsaad_app/features/agency_reports/agency_report_screen.dart';
import 'package:khaledsaad_app/features/agency_reports/models.dart';

void main() {
  testWidgets('يعرض تقرير المالك ثم موجز الوكالة كمستند مستقل', (tester) async {
    final report = AgencyReportDetail.fromJson({
      'uuid': 'report-1',
      'project_slug': 'store',
      'title': 'أين يقف مشروعك — المتجر',
      'version': 1,
      'visibility': <String, String>{},
      'share': <String, dynamic>{},
      'documents': {
        'owner': {'label': 'تقريرك الكامل', 'pdf_url': '/owner.pdf'},
        'agency_brief': {
          'label': 'موجز التكليف للوكالة',
          'is_ready': true,
          'missing_count': 0,
          'message': 'مكتمل',
          'pdf_url': '/brief.pdf',
        },
      },
      'snapshot': {
        'project': {'name': 'المتجر'},
        'owner_report': {
          'overview': {
            'title': 'أين يقف مشروعك الآن؟',
            'description': 'لديك عرض واضح، لكن القياس يحتاج ترتيبًا.',
            'main_issue': 'لا يوجد قياس مكتمل.',
          },
          'numbers': {'rows': [], 'tracking_label': 'غير مركب'},
          'journey': {'description': 'نحتاج معرفة موضع التوقف.'},
          'problems': [],
          'conflicts': [],
          'unknowns': [],
          'this_week': [],
          'before_agency': <String, dynamic>{},
          'readiness': {
            'is_ready': true,
            'message': 'موجزك مكتمل.',
            'requirements': [],
          },
          'private_details': {
            'behaviour': {
              'tasks': {'done': 0, 'total': 1},
            },
            'plan': <String, dynamic>{},
          },
        },
        'agency_brief': {
          'project': {'name': 'المتجر', 'description': 'متجر محلي'},
          'baseline': {'rows': [], 'tracking': 'غير مركب'},
          'goal': {'primary': 'زيادة المبيعات', 'success_metric': '20 طلبًا'},
          'scope': {
            'services': ['إدارة الإعلانات'],
            'out_of_scope': [],
          },
          'assets': {'rows': []},
          'workflow': <String, dynamic>{},
          'terms': <String, dynamic>{},
          'proposal': {'requirements': [], 'pricing_rows': []},
          'submission': {'deadline': '15 أغسطس', 'method': 'PDF'},
          'readiness': {'is_ready': true},
        },
      },
    });

    await tester.pumpWidget(
      MaterialApp(
        home: AgencyReportScreen(
          repository: PlatformRepository(ApiClient()),
          uuid: report.uuid,
          initial: report,
        ),
      ),
    );
    await tester.pump();

    expect(find.text('أين يقف مشروعك الآن؟'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('أرقامك ببساطة'),
      500,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('أرقامك ببساطة'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('ما يمكنك فعله هذا الأسبوع'),
      500,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('ما يمكنك فعله هذا الأسبوع'), findsOneWidget);
    expect(find.text('المشروع في سطور واضحة'), findsNothing);

    await tester.scrollUntilVisible(
      find.text('موجز الوكالة'),
      -500,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('موجز الوكالة'));
    await tester.pumpAndSettle();

    expect(find.text('المشروع في سطور واضحة'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('ما يجب أن يتضمنه عرضكم'),
      500,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('ما يجب أن يتضمنه عرضكم'), findsOneWidget);
    expect(find.text('سجل التنفيذ'), findsNothing);
  });
}

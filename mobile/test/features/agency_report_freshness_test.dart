import 'package:flutter_test/flutter_test.dart';
import 'package:khaledsaad_app/features/agency_reports/models.dart';

void main() {
  test('parses freshness consultation evidence and cross-tool synthesis', () {
    final report = AgencyReportDetail.fromJson({
      'uuid': 'report-1',
      'project_slug': 'store',
      'title': 'التقرير الموحد',
      'version': 2,
      'generated_at': '2026-07-27T10:00:00Z',
      'visibility': {'evidence': 'full'},
      'freshness': {
        'is_stale': true,
        'state': 'stale',
        'label': 'يحتاج تحديثًا',
        'reasons': ['تغيرت معلومات المشروع بعد إنشاء هذا الإصدار.'],
      },
      'share': {},
      'snapshot': {
        'project': {'name': 'المتجر'},
        'readiness': {'score': 60, 'band': 'مستقر'},
        'consultation': {
          'uuid': 'session-1',
          'depth': 'standard',
          'inferences': [
            {
              'statement': 'افتراض يحتاج تحققًا',
              'confidence': 40,
              'status': 'provisional',
            },
          ],
          'conflicts': [
            {
              'message': 'فترتان مختلفتان',
              'status': 'resolved',
              'resolution': {'statement': 'اعتمدت آخر ثلاثين يومًا'},
            },
          ],
          'evidence': [
            {
              'name': 'sales.txt',
              'extraction_status': 'completed',
              'sha256': 'abc',
              'text': 'دليل المبيعات',
            },
          ],
        },
        'cross_tool_synthesis': {
          'findings': [
            {
              'source_report_id': 7,
              'source_tool_key': 'marketing-score',
              'source_tool_title': 'الجاهزية',
              'title': 'ضعف القياس',
              'claim_type': 'evidence',
            },
          ],
          'agreements': [],
          'divergences': [
            {
              'category': 'القياس',
              'findings': ['ضعف القياس'],
              'source_tools': ['marketing-score', 'brand-clarity'],
            },
          ],
        },
      },
    });

    expect(report.freshness.isStale, isTrue);
    expect(report.visibility['evidence'], 'full');
    expect(report.consultation?.evidence.single.text, 'دليل المبيعات');
    expect(
      report.consultation?.conflicts.single.resolution,
      'اعتمدت آخر ثلاثين يومًا',
    );
    expect(report.crossTool.findings.single.sourceReportId, 7);
    expect(report.crossTool.divergences.single.category, 'القياس');
  });
}

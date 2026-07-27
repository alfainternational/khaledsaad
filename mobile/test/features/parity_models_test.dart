import 'package:flutter_test/flutter_test.dart';
import 'package:khaledsaad_app/features/account/models.dart';
import 'package:khaledsaad_app/features/agency_reports/models.dart';
import 'package:khaledsaad_app/features/projects/models.dart';
import 'package:khaledsaad_app/features/reports/models.dart';
import 'package:khaledsaad_app/features/tools/models.dart';

/// حمولات هذه الاختبارات منسوخة من مخرجات العارضين في Laravel.
/// إن تغيّر شكل العارض ولم تُحدَّث النماذج، تفشل هذه الاختبارات —
/// وهذا هو الغرض: منع انحراف التطبيق عن الويب بصمت.
void main() {
  _accountModels();
  test('بطاقة الأداة تقرأ حمولة ToolPresenter::card', () {
    final tool = ToolCard.fromJson(const {
      'key': 'marketing-score',
      'name': 'Marketing Score',
      'title': 'درجة الجاهزية التسويقية',
      'description': 'قياس شامل من 100.',
      'category': 'التشخيص',
      'status': 'published',
      'is_runnable': true,
      'status_label': 'متاحة الآن',
      'credit_cost': 5,
    });

    expect(tool.key, 'marketing-score');
    expect(tool.isRunnable, isTrue);
    expect(tool.statusLabel, 'متاحة الآن');
  });

  test('التشغيل يقرأ حمولة RunPresenter::wizard', () {
    final run = ToolRunModel.fromJson(const {
      'uuid': 'abc-123',
      'status': 'draft',
      'status_label': 'مسودة',
      'current_step': 2,
      'base_score': null,
      'confidence': null,
      'failure_reason': null,
      'is_terminal': false,
      'progress_percent': 0,
      'completeness_percent': 40,
      'insights': {
        'summary': {
          'completeness_percent': 40,
          'missing_count': 3,
          'missing': ['الهدف'],
          'agency_readiness_percent': 64,
          'agency_readiness_label': 'قريبة من الجاهزية',
          'agency_missing': ['website'],
        },
        'signals': [
          {
            'type': 'risk',
            'title': 'الهدف بلا مورد محدد',
            'description': 'حدد قناة أو ميزانية.',
            'basis': 'الهدف مقابل الميزانية.',
          },
        ],
        'preliminary': {
          'status': 'not_requested',
          'label': 'مؤشر أولي',
          'meaning': '',
          'risk_or_opportunity': '',
          'recommendation': '',
          'deepen_question': '',
        },
      },
      'tool': {'title': 'درجة الجاهزية التسويقية'},
      'project': {'name': 'مشروعي'},
      'report_id': null,
      'steps': [
        {
          'step': 1,
          'title': 'أساسيات المشروع',
          'fields': [
            {
              'key': 'business_model',
              'label': 'نموذج العمل',
              'help': null,
              'type': 'select',
              'required': true,
              'options': [
                {'value': 'b2c', 'label': 'بيع مباشر'},
              ],
              'value': 'b2c',
            },
          ],
        },
      ],
      'answers': {'business_model': 'b2c'},
    });

    expect(run.uuid, 'abc-123');
    expect(run.steps.single.fields.single.options.single.label, 'بيع مباشر');
    expect(run.completenessPercent, 40);
    expect(run.isTerminal, isFalse);
    expect(run.insights!.summary.agencyReadinessPercent, 64);
    expect(run.insights!.signals.single.type, 'risk');
  });

  test('حقل متعدد الاختيار يقرأ القيم كقائمة', () {
    final field = ToolFieldModel.fromJson(const {
      'key': 'active_channels',
      'label': 'القنوات',
      'type': 'multiselect',
      'required': true,
      'options': [],
      'value': ['seo', 'paid'],
    });

    expect(field.selectedValues, ['seo', 'paid']);
  });

  test('التقرير يحافظ على الفصل بين الدليل والافتراض', () {
    final report = ReportDetail.fromJson(const {
      'id': 7,
      'title': 'تقرير',
      'score': 62,
      'score_band': 'مستقر',
      'summary': 'ملخص',
      'is_manually_reviewed': true,
      'reviewed_at': '24 يوليو 2026',
      'assumptions': ['بيانات ناقصة'],
      'next_step': {'title': 'ابدأ هنا', 'description': 'وصف'},
      'project': {'name': 'مشروعي'},
      'tool': {'title': 'الأداة'},
      'provenance': {'tool_version': 1},
      'comparison': {
        'delta': 8,
        'direction': 'up',
        'label': 'تحسّن بمقدار 8 نقاط',
      },
      'watcher': {'status': 'active', 'changes': []},
      'my_verdict': 'up',
      'suggestion': {
        'tool': {'key': 'brand-clarity', 'title': 'وضوح العلامة'},
        'reason': 'الخطوة التالية في الرحلة.',
      },
      'sections': [],
      'counts': {'findings': 2, 'evidence_backed': 1, 'assumptions': 1},
      'findings': [
        {
          'id': 1,
          'title': 'نتيجة مدعومة',
          'description': 'وصف',
          'category': 'القياس',
          'severity': 'high',
          'severity_label': 'عالية',
          'evidence': 'إجابة المستخدم',
          'confidence': 90,
          'is_assumption': false,
          'basis_label': 'مدعوم بدليل',
          'recommendations': [],
        },
        {
          'id': 2,
          'title': 'ترجيح',
          'description': 'وصف',
          'category': 'الجمهور',
          'severity': 'low',
          'severity_label': 'منخفضة',
          'evidence': null,
          'confidence': 40,
          'is_assumption': true,
          'basis_label': 'افتراض',
          'recommendations': [],
        },
      ],
    });

    expect(report.evidenceBacked, 1);
    expect(report.assumptionCount, 1);
    expect(report.findings.first.basisLabel, 'مدعوم بدليل');
    expect(report.findings.last.basisLabel, 'افتراض');
    expect(report.isManuallyReviewed, isTrue);
    expect(report.reviewedAt, '24 يوليو 2026');
    expect(report.comparison!.delta, 8);
    expect(report.watcher!.isActive, isTrue);
    expect(report.myVerdict, 'up');
    expect(report.suggestion!.toolKey, 'brand-clarity');
  });

  test('نظرة المشروع تقرأ المقارنة الزمنية', () {
    final project = ProjectOverview.fromJson(const {
      'slug': 'my-project',
      'name': 'مشروعي',
      'industry': 'تعليم',
      'stage': 'growth',
      'latest_score': 62,
      'score_band': 'مستقر',
      'profile': {},
      'latest_report': {
        'id': 7,
        'title': 'تقرير',
        'score': 62,
        'score_band': 'مستقر',
      },
      'comparison': {
        'delta': 8,
        'direction': 'up',
        'label': 'تحسّن بمقدار 8 نقاط',
      },
      'reports': [],
      'tasks': {'open': 3, 'overdue': 1, 'done': 2},
      'kpis': [],
    });

    expect(project.card.latestScore, 62);
    expect(project.comparison!.direction, 'up');
    expect(project.overdueTasks, 1);
  });

  test('موجز الوكالة يقرأ الجاهزية واللقطة الثابتة', () {
    final index = AgencyReportIndex.fromJson(const {
      'readiness': {
        'can_generate': true,
        'required_count': 3,
        'completed_count': 3,
        'included_count': 4,
        'missing_core': [],
        'included_tools': [],
      },
      'reports': [
        {
          'uuid': 'agency-1',
          'title': 'موجز الوكالة',
          'version': 1,
          'generated_at': '2026-07-24T00:00:00Z',
        },
      ],
    });
    final detail = AgencyReportDetail.fromJson(const {
      'uuid': 'agency-1',
      'project_slug': 'my-project',
      'title': 'موجز الوكالة',
      'version': 1,
      'status': 'published',
      'generated_at': '2026-07-24T00:00:00Z',
      'visibility': {},
      'source_report_ids': [1, 2, 3],
      'snapshot': {
        'project': {'name': 'مشروعي'},
        'readiness': {'score': 68, 'band': 'مستقر'},
        'tools': [],
        'priorities': [
          {
            'title': 'فعّل القياس',
            'description': 'اربط التحويل.',
            'source_tool': 'الجاهزية',
          },
        ],
        'plan': {'30_days': [], '60_days': [], '90_days': []},
        'scope': {
          'out_of_scope': [],
          'account_ownership': 'للمشروع',
          'review_cadence': 'أسبوعي',
        },
        'agency_questions': ['ما النتيجة؟'],
        'assumptions': [],
        'data_gaps': [],
      },
    });

    expect(index.readiness.canGenerate, isTrue);
    expect(index.reports.single.version, 1);
    expect(detail.readinessScore, 68);
    expect(detail.priorities.single.title, 'فعّل القياس');
  });

  test('الفحص المسبق يمنع التشغيل عند وجود نواقص', () {
    final blocked = Preflight.fromJson(const {
      'missing': ['وضوح العرض'],
      'percent': 80,
      'assumptions': [],
    });

    final ready = Preflight.fromJson(const {
      'missing': [],
      'percent': 100,
      'assumptions': [],
    });

    expect(blocked.isReady, isFalse);
    expect(ready.isReady, isTrue);
  });
}

// --- نماذج الحساب ---

void _accountModels() {
  test('ملخص الفوترة يقرأ حمولة AccountController::billing', () {
    final billing = BillingSummary.fromJson(const {
      'balance': 40,
      'current_plan': 'individual',
      'project_count': 2,
      'project_limit': 3,
      'plans': [
        {
          'key': 'free',
          'name': 'مجانية',
          'price': 0,
          'monthly_credits': 5,
          'project_limit': 1,
          'features': ['أداة واحدة'],
          'is_current': false,
        },
      ],
    });

    expect(billing.balance, 40);
    expect(billing.currentPlan, 'individual');
    expect(billing.plans.single.name, 'مجانية');
  });

  test('الإشعار يقرأ حالة القراءة والرابط', () {
    final notification = AppNotification.fromJson(const {
      'id': 'abc',
      'title': 'تقريرك جاهز',
      'body': 'انتهى التحليل.',
      'url': '/app/reports/7',
      'read': false,
    });

    expect(notification.title, 'تقريرك جاهز');
    expect(notification.read, isFalse);
    expect(notification.url, '/app/reports/7');
  });
}

import 'package:flutter_test/flutter_test.dart';
import 'package:ksgrowth_mobile/data/models/lifecycle_models.dart';

void main() {
  test('ExecutionPackageModel يبني موجز Studio من قرار التنفيذ', () {
    final package = ExecutionPackageModel(
      publicId: 'pkg_123',
      title: 'تحسين صفحة الهبوط',
      status: 'approved',
      problem: 'معدل التحويل منخفض.',
      evidence: 'الزيارات كثيرة لكن الطلبات قليلة.',
      decision: 'إعادة كتابة العرض الأول.',
      measurementPlan: 'قياس التحويل خلال 14 يوم.',
      assets: const [
        {
          'type': 'landing_page',
          'title': 'Hero section',
          'body': 'عنوان مباشر مع إثبات ثقة.',
        },
      ],
    );

    final brief = package.studioBrief;

    expect(brief, contains('حزمة التنفيذ: تحسين صفحة الهبوط'));
    expect(brief, contains('المشكلة: معدل التحويل منخفض.'));
    expect(brief, contains('الدليل: الزيارات كثيرة لكن الطلبات قليلة.'));
    expect(brief, contains('القرار: إعادة كتابة العرض الأول.'));
    expect(brief, contains('الأصل المطلوب: Hero section'));
    expect(brief, contains('نوع الأصل: landing_page'));
    expect(brief, contains('المحتوى الأولي: عنوان مباشر مع إثبات ثقة.'));
    expect(brief, contains('خطة القياس: قياس التحويل خلال 14 يوم.'));
  });
}

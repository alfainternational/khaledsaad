import 'package:flutter_test/flutter_test.dart';

import 'package:ksgrowth_mobile/data/models/tool_run_model.dart';

void main() {
  test('ToolBriefing يقرأ زر الانتقال من رد الأداة', () {
    final briefing = ToolBriefing.fromJson({
      'next_action': {
        'action_type': 'tool',
        'recommended_tool_code': 'offer-builder',
        'recommended_tool_label': 'بناء العرض',
        'cta_url': '/tools/offer-builder',
        'cta_label': 'افتح بناء العرض',
      },
    });

    expect(briefing.hasAction, isTrue);
    expect(briefing.nextAction?.actionType, 'tool');
    expect(briefing.nextAction?.recommendedToolCode, 'offer-builder');
    expect(briefing.nextAction?.displayLabel, 'افتح بناء العرض');
  });
}

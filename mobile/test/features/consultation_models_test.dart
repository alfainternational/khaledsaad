import 'package:flutter_test/flutter_test.dart';
import 'package:khaledsaad_app/features/consultations/models.dart';

void main() {
  test('accepts the empty validation list emitted by older API releases', () {
    final question = ConsultationQuestion.fromJson({
      'key': 'START-01',
      'text': 'ما نوع مشروعك؟',
      'type': 'select',
      'options': [
        {'value': 'existing', 'label': 'مشروع قائم'},
      ],
      'validation': [],
      'required': true,
      'allow_unknown': true,
      'allow_skip': false,
      'sensitive': false,
    });

    expect(question.validation, isEmpty);

    final reviewItem = ConsultationReviewItem.fromJson({
      'question_key': 'START-01',
      'label': 'ما نوع مشروعك؟',
      'value': ['existing'],
      'validation': [],
    });

    expect(reviewItem.validation, isEmpty);
  });

  test('parses the server-owned consultation question and progress', () {
    final session = ConsultationSessionModel.fromJson({
      'uuid': 'session-1',
      'status': 'active',
      'depth': 'standard',
      'project': {'slug': 'store', 'name': 'متجر'},
      'progress': {
        'answered': 1,
        'limit': 35,
        'percent': 3,
        'label': 'نفهم مشروعك',
      },
      'question': {
        'key': 'START-02',
        'text': 'ما طبيعة ما تقدمه؟',
        'type': 'select',
        'options': [
          {'value': 'خدمة', 'label': 'خدمة'},
        ],
        'required': true,
        'allow_unknown': true,
        'allow_skip': false,
        'sensitive': false,
      },
      'scope': [],
      'conflicts': [],
      'review': {
        'facts': [
          {'label': 'نوع المشروع', 'value': 'متجر'},
        ],
        'estimates': [],
        'unknowns': [],
        'assumptions': [],
        'conflicts': [],
      },
      'status_message': 'الاستشارة قيد الاستكمال.',
      'can_confirm': false,
    });

    expect(session.question?.key, 'START-02');
    expect(session.progress.label, 'نفهم مشروعك');
    expect(session.question?.options.single.value, 'خدمة');
    expect(session.review.facts.single.displayValue, 'متجر');
  });
}

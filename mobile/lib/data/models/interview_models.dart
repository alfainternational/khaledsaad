/// سؤال في مقابلة المؤسِّس (مرتبط بمفتاح canonical على الخادم).
class InterviewQuestion {
  const InterviewQuestion({
    required this.key,
    required this.label,
    this.hint = '',
    this.placeholder = '',
  });

  final String key;
  final String label;
  final String hint;
  final String placeholder;

  factory InterviewQuestion.fromJson(Map<String, dynamic> json) =>
      InterviewQuestion(
        key: json['key']?.toString() ?? '',
        label: json['label']?.toString() ?? '',
        hint: json['hint']?.toString() ?? '',
        placeholder: json['placeholder']?.toString() ?? '',
      );
}

/// نتيجة تحميل المقابلة: الأسئلة + الإجابات المحفوظة + توفّر الصوت.
class InterviewData {
  const InterviewData({
    required this.questions,
    required this.answers,
    this.voiceEnabled = false,
  });

  final List<InterviewQuestion> questions;
  final Map<String, String> answers;
  final bool voiceEnabled;

  factory InterviewData.fromJson(Map<String, dynamic> json) {
    final questions = (json['questions'] as List?)
            ?.map((e) =>
                InterviewQuestion.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList() ??
        const <InterviewQuestion>[];

    final rawAnswers = json['answers'];
    final answers = <String, String>{};
    if (rawAnswers is Map) {
      rawAnswers.forEach((k, v) => answers[k.toString()] = v?.toString() ?? '');
    }

    return InterviewData(
      questions: questions,
      answers: answers,
      voiceEnabled: json['voice_enabled'] == true,
    );
  }
}

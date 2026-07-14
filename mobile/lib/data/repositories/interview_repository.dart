import '../../core/config/api_endpoints.dart';
import '../../core/network/api_client.dart';
import '../models/interview_models.dart';

/// مقابلة المؤسِّس: تحميل الأسئلة/الإجابات وحفظها. الحفظ يخزّن قيماً canonical
/// على الخادم فتُلقّم أدوات المشروع تلقائياً (نفس حلقة الويب — المرحلة 4).
class InterviewRepository {
  InterviewRepository(this._api);

  final ApiClient _api;

  Future<InterviewData> load(String ws, String project) async {
    final res = await _api.get(ApiEndpoints.interview(ws, project));
    final data = res['data'];
    return InterviewData.fromJson(
      data is Map ? Map<String, dynamic>.from(data) : const {},
    );
  }

  /// يحفظ الإجابات ويعيد عدد الحقول المحفوظة.
  Future<int> save(String ws, String project, Map<String, String> answers) async {
    final res = await _api.post(
      ApiEndpoints.interview(ws, project),
      body: {'answers': answers},
    );
    final data = res['data'];
    if (data is Map && data['count'] is num) {
      return (data['count'] as num).toInt();
    }
    return 0;
  }
}

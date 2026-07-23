import '../../features/projects/models.dart';
import '../../features/reports/models.dart';
import '../../features/account/models.dart';
import '../../features/tools/attachments.dart';
import '../../features/tools/engagement.dart';
import '../../features/tools/models.dart';
import '../config/app_environment.dart';
import 'api_client.dart';

/// كل نداء يقابل مسارًا في routes/api.php، وكل مسار يستدعي نفس الخدمة
/// التي يستدعيها الويب. لا منطق أعمال هنا — فقط نقل.
class PlatformRepository {
  PlatformRepository(this._api);

  final ApiClient _api;

  ApiClient get client => _api;

  // --- الحساب ---

  Future<String> register({
    required String name,
    required String email,
    required String password,
  }) async {
    final response = await _api.post('/auth/register', {
      'name': name,
      'email': email,
      'password': password,
      'device_name': AppEnvironment.deviceName,
    });

    return _storeToken(response);
  }

  Future<String> login({required String email, required String password}) async {
    final response = await _api.post('/auth/login', {
      'email': email,
      'password': password,
      'device_name': AppEnvironment.deviceName,
    });

    return _storeToken(response);
  }

  Future<Map<String, dynamic>> me() async {
    final response = await _api.get('/auth/me');
    return Map<String, dynamic>.from(response['data'] as Map);
  }

  Future<void> logout() async {
    try {
      await _api.post('/auth/logout');
    } finally {
      await _api.tokens.clear();
    }
  }

  Future<String> _storeToken(dynamic response) async {
    final token = (response['data'] as Map)['token'] as String;
    await _api.tokens.write(token);

    return token;
  }

  // --- المشاريع ---

  Future<List<ProjectCard>> projects() async {
    final response = await _api.get('/projects');

    return (response['data'] as List)
        .map((e) => ProjectCard.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }

  Future<ProjectOverview> project(String slug) async {
    final response = await _api.get('/projects/$slug');

    return ProjectOverview.fromJson(Map<String, dynamic>.from(response['data'] as Map));
  }

  Future<ProjectOverview> createProject(Map<String, dynamic> data) async {
    final response = await _api.post('/projects', data);

    return ProjectOverview.fromJson(Map<String, dynamic>.from(response['data'] as Map));
  }

  Future<ProjectOverview> updateProject(String slug, Map<String, dynamic> data) async {
    final response = await _api.put('/projects/$slug', data);

    return ProjectOverview.fromJson(Map<String, dynamic>.from(response['data'] as Map));
  }

  // --- الأدوات ---

  Future<List<ToolCard>> tools() async {
    final response = await _api.get('/tools');

    return (response['data'] as List)
        .map((e) => ToolCard.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }

  Future<ToolDetail> tool(String key) async {
    final response = await _api.get('/tools/$key');

    return ToolDetail.fromJson(Map<String, dynamic>.from(response['data'] as Map));
  }

  // --- الأرصدة والخطط ---

  Future<BillingSummary> billing() async {
    final response = await _api.get('/billing');

    return BillingSummary.fromJson(Map<String, dynamic>.from(response['data'] as Map));
  }

  /// يبدأ شراء خطة. يعيد رابط الدفع إن كانت مدفوعة، أو null إن اكتملت مباشرة.
  Future<String?> checkoutPlan(String planKey) async {
    final response = await _api.post('/checkout/plan/$planKey');
    final data = Map<String, dynamic>.from(response['data'] as Map);

    return (data['completed'] as bool? ?? false) ? null : data['redirect_url'] as String?;
  }

  Future<String?> checkoutPack(int packId) async {
    final response = await _api.post('/checkout/pack/$packId');
    final data = Map<String, dynamic>.from(response['data'] as Map);

    return (data['completed'] as bool? ?? false) ? null : data['redirect_url'] as String?;
  }

  // --- الإشعارات ---

  Future<NotificationList> notifications() async {
    final response = await _api.get('/notifications');

    return NotificationList(
      items: (response['data'] as List)
          .map((e) => AppNotification.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(),
      unread: response['unread'] as int? ?? 0,
    );
  }

  Future<void> markNotificationRead(String id) => _api.post('/notifications/$id/read');

  // --- ما بدأه المستخدم ولم يكمله ---

  Future<List<ResumeCard>> unfinished() async {
    final response = await _api.get('/engagements/unfinished');

    return (response['data'] as List)
        .map((e) => ResumeCard.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }

  /// أدوات المشروع مع حالة كل واحدة — نظير صفحة المشروع في الويب.
  Future<List<(ToolCard, Engagement)>> projectTools(String projectSlug) async {
    final response = await _api.get('/projects/$projectSlug/tools');

    return (response['data'] as List).map((raw) {
      final map = Map<String, dynamic>.from(raw as Map);

      return (
        ToolCard.fromJson(map),
        map['engagement'] == null
            ? Engagement.fresh
            : Engagement.fromJson(Map<String, dynamic>.from(map['engagement'] as Map)),
      );
    }).toList();
  }

  // --- التشغيل ---

  Future<ToolRunModel> startRun(String projectSlug, String toolKey) async {
    final response = await _api.post('/projects/$projectSlug/tools/$toolKey/runs');

    return ToolRunModel.fromJson(Map<String, dynamic>.from(response['data'] as Map));
  }

  Future<ToolRunModel> run(String uuid) async {
    final response = await _api.get('/runs/$uuid');

    return ToolRunModel.fromJson(Map<String, dynamic>.from(response['data'] as Map));
  }

  Future<ToolRunModel> saveStep(String uuid, int step, Map<String, dynamic> answers) async {
    final response = await _api.put('/runs/$uuid/steps/$step', {'answers': answers});

    return ToolRunModel.fromJson(Map<String, dynamic>.from(response['data'] as Map));
  }

  /// رفع دليل إلى تشغيل. يعيد قائمة الملفات المحدَّثة.
  Future<List<RunFile>> uploadFile(String uuid, String filePath) async {
    final response = await _api.upload('/runs/$uuid/files', filePath);

    return (response['data'] as List)
        .map((e) => RunFile.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }

  Future<List<RunFile>> deleteFile(String uuid, int fileId) async {
    final response = await _api.delete('/runs/$uuid/files/$fileId');

    return (response['data'] as List)
        .map((e) => RunFile.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }

  // --- المنافسون ---

  Future<CompetitorView> competitors(String projectSlug) async {
    final response = await _api.get('/projects/$projectSlug/competitors');

    return CompetitorView.fromJson(Map<String, dynamic>.from(response['data'] as Map));
  }

  Future<CompetitorView> addCompetitors(String projectSlug, String names) async {
    final response = await _api.post('/projects/$projectSlug/competitors', {'names': names});

    return CompetitorView.fromJson(Map<String, dynamic>.from(response['data'] as Map));
  }

  Future<CompetitorView> confirmCompetitor(int id) async {
    final response = await _api.post('/competitors/$id/confirm');

    return CompetitorView.fromJson(Map<String, dynamic>.from(response['data'] as Map));
  }

  Future<CompetitorView> dismissCompetitor(int id) async {
    final response = await _api.post('/competitors/$id/dismiss');

    return CompetitorView.fromJson(Map<String, dynamic>.from(response['data'] as Map));
  }

  Future<Preflight> preflight(String uuid) async {
    final response = await _api.get('/runs/$uuid/preflight');

    return Preflight.fromJson(Map<String, dynamic>.from(response['data'] as Map));
  }

  Future<ToolRunModel> queueRun(String uuid) async {
    final response = await _api.post('/runs/$uuid/queue');

    return ToolRunModel.fromJson(Map<String, dynamic>.from(response['data'] as Map));
  }

  Future<ToolRunModel> progress(String uuid) async {
    final response = await _api.get('/runs/$uuid/progress');

    return ToolRunModel.fromJson(Map<String, dynamic>.from(response['data'] as Map));
  }

  Future<ToolRunModel> retryRun(String uuid) async {
    final response = await _api.post('/runs/$uuid/retry');

    return ToolRunModel.fromJson(Map<String, dynamic>.from(response['data'] as Map));
  }

  // --- التقارير ---

  Future<ReportDetail> report(int id) async {
    final response = await _api.get('/reports/$id');

    return ReportDetail.fromJson(Map<String, dynamic>.from(response['data'] as Map));
  }

  /// بايتات PDF التقرير، مُصدَّقة بالرمز.
  Future<List<int>> reportPdf(int id) => _api.downloadBytes('/reports/$id/pdf');

  Future<ScoreComparison?> reportComparison(int id) async {
    final response = await _api.get('/reports/$id');
    final comparison = response['comparison'];

    return comparison == null
        ? null
        : ScoreComparison.fromJson(Map<String, dynamic>.from(comparison as Map));
  }

  Future<List<TaskModel>> convertRecommendations(int reportId, {int? recommendationId}) async {
    final response = await _api.post(
      '/reports/$reportId/tasks',
      recommendationId == null ? const {} : {'recommendation_id': recommendationId},
    );

    return (response['data'] as List)
        .map((e) => TaskModel.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }

  // --- المهام والمؤشرات ---

  Future<Map<String, List<TaskModel>>> tasks(String projectSlug) async {
    final response = await _api.get('/projects/$projectSlug/tasks');
    final data = Map<String, dynamic>.from(response['data'] as Map);

    return data.map(
      (key, value) => MapEntry(
        key,
        (value as List).map((e) => TaskModel.fromJson(Map<String, dynamic>.from(e as Map))).toList(),
      ),
    );
  }

  Future<TaskModel> updateTask(int id, String status) async {
    final response = await _api.patch('/tasks/$id', {'status': status});

    return TaskModel.fromJson(Map<String, dynamic>.from(response['data'] as Map));
  }

  Future<void> createKpi(String projectSlug, Map<String, dynamic> data) =>
      _api.post('/projects/$projectSlug/kpis', data);

  Future<void> recordKpi(int kpiId, double value) =>
      _api.post('/kpis/$kpiId/entries', {'value': value});
}

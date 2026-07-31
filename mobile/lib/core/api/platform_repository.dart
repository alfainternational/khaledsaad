import '../../features/projects/models.dart';
import '../../features/agency_reports/models.dart';
import '../../features/reports/models.dart';
import '../../features/account/models.dart';
import '../../features/tools/attachments.dart';
import '../../features/tools/engagement.dart';
import '../../features/tools/models.dart';
import '../../features/consultations/models.dart';
import '../config/app_environment.dart';
import 'api_client.dart';
import 'guest_session_store.dart';

/// كل نداء يقابل مسارًا في routes/api.php، وكل مسار يستدعي نفس الخدمة
/// التي يستدعيها الويب. لا منطق أعمال هنا — فقط نقل.
class PlatformRepository {
  PlatformRepository(this._api, {GuestSessionStore? guestSessions})
    : _guestSessions = guestSessions ?? GuestSessionStore();

  final ApiClient _api;
  final GuestSessionStore _guestSessions;

  ApiClient get client => _api;

  Future<ConsultationSessionModel> startConsultation(
    String projectSlug, {
    String depth = 'standard',
  }) async {
    final response = await _api.post('/projects/$projectSlug/consultations', {
      'depth': depth,
    });
    return ConsultationSessionModel.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<ConsultationSessionModel> answerConsultation(
    String uuid,
    String questionKey, {
    dynamic value,
    bool unknown = false,
    bool skipped = false,
  }) async {
    final response = await _api.put(
      '/consultations/$uuid/answers/$questionKey',
      {'value': value, 'unknown': unknown, 'skipped': skipped},
    );
    return ConsultationSessionModel.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<ConsultationSessionModel> consultation(String uuid) async {
    final response = await _api.get('/consultations/$uuid');
    return ConsultationSessionModel.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<ConsultationSessionModel> reviewConsultation(String uuid) async {
    final response = await _api.post('/consultations/$uuid/review');
    return ConsultationSessionModel.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<ConsultationSessionModel> confirmConsultation(String uuid) async {
    final response = await _api.post('/consultations/$uuid/confirm');
    return ConsultationSessionModel.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<ConsultationSessionModel> retryConsultation(String uuid) async {
    final response = await _api.post('/consultations/$uuid/retry');
    return ConsultationSessionModel.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<ConsultationSessionModel> consultationStatus(String uuid) async {
    final response = await _api.get('/consultations/$uuid/status');
    return ConsultationSessionModel.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<ConsultationSessionModel> resolveConsultationConflict(
    String uuid,
    int conflictId,
    String resolution,
  ) async {
    final response = await _api.post(
      '/consultations/$uuid/conflicts/$conflictId/resolve',
      {'resolution': resolution},
    );
    return ConsultationSessionModel.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<void> deleteConsultation(String uuid) =>
      _api.delete('/consultations/$uuid');

  Future<ConsultationSessionModel> uploadConsultationEvidence(
    String uuid,
    String filePath,
  ) async {
    final response = await _api.upload(
      '/consultations/$uuid/evidence',
      filePath,
    );
    return ConsultationSessionModel.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<ConsultationSessionModel> deleteConsultationEvidence(
    String uuid,
    int evidenceId,
  ) async {
    final response = await _api.delete(
      '/consultations/$uuid/evidence/$evidenceId',
    );
    return ConsultationSessionModel.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  // --- الحساب ---

  Future<Map<String, dynamic>> publicBootstrap() async {
    final response = await _api.get('/public/bootstrap');

    return Map<String, dynamic>.from(response['data'] as Map);
  }

  Future<Map<String, dynamic>> legalPage(String page) async {
    final response = await _api.get('/public/legal/$page');

    return Map<String, dynamic>.from(response['data'] as Map);
  }

  Future<String> register({
    required String name,
    required String email,
    required String password,
  }) async {
    final guestToken = await _guestSessions.read();
    final payload = <String, dynamic>{
      'name': name,
      'email': email,
      'password': password,
      'device_name': AppEnvironment.deviceName,
    };
    if (guestToken != null) payload['guest_token'] = guestToken;
    final response = await _api.post('/auth/register', payload);

    final token = await _storeToken(response);
    await _guestSessions.clear();

    return token;
  }

  Future<String> login({
    required String email,
    required String password,
  }) async {
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

  Future<String> requestPasswordReset(String email) async {
    final response = await _api.post('/auth/forgot-password', {'email': email});

    return ((response['data'] as Map?)?['message'] ?? response['message'])
        .toString();
  }

  Future<String> resetPassword({
    required String token,
    required String email,
    required String password,
  }) async {
    final response = await _api.post('/auth/reset-password', {
      'token': token,
      'email': email,
      'password': password,
      'password_confirmation': password,
    });

    return ((response['data'] as Map?)?['message'] ?? response['message'])
        .toString();
  }

  Future<Map<String, dynamic>> publicSharedReport(String token) async {
    final response = await _api.get('/public/shared-reports/$token');
    return Map<String, dynamic>.from(response['data'] as Map);
  }

  Future<List<int>> publicSharedReportPdf(String token) =>
      _api.downloadBytes('/public/shared-reports/$token/pdf');

  Future<ToolRunModel> startGuestRun(
    String toolKey, {
    String projectName = 'مشروعي',
  }) async {
    final guestToken = await _guestSessions.read();
    final headers = <String, String>{};
    if (guestToken != null) headers['X-Guest-Token'] = guestToken;
    final response = await _api.postWithHeaders(
      '/public/tools/$toolKey/runs',
      headers,
      {'project_name': projectName},
    );
    final data = Map<String, dynamic>.from(response['data'] as Map);
    final createdToken = data['guest_token']?.toString();
    if (createdToken != null && createdToken.isNotEmpty) {
      await _guestSessions.write(createdToken);
    }

    return ToolRunModel.fromJson(Map<String, dynamic>.from(data['run'] as Map));
  }

  Future<ToolRunModel> saveGuestStep(
    String uuid,
    int step,
    Map<String, dynamic> answers,
  ) async {
    final response = await _api.putWithHeaders(
      '/public/runs/$uuid/steps/$step',
      await _guestHeaders(),
      answers,
    );

    return ToolRunModel.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<Preflight> guestPreflight(String uuid) async {
    final response = await _api.getWithHeaders(
      '/public/runs/$uuid/preflight',
      await _guestHeaders(),
    );
    final data = Map<String, dynamic>.from(response['data'] as Map);

    return Preflight.fromJson(
      Map<String, dynamic>.from(data['preflight'] as Map),
    );
  }

  Future<Map<String, String>> _guestHeaders() async {
    final token = await _guestSessions.read();
    final headers = <String, String>{};
    if (token != null) headers['X-Guest-Token'] = token;
    return headers;
  }

  Future<void> registerDevice({
    required String token,
    required String platform,
    String? deviceName,
  }) => _api.post('/devices', {
    'token': token,
    'platform': platform,
    'device_name': deviceName,
  });

  Future<void> removeDevice(String token) =>
      _api.delete('/devices', {'token': token});

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

    return ProjectOverview.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<ProjectOverview> createProject(Map<String, dynamic> data) async {
    final response = await _api.post('/projects', data);

    return ProjectOverview.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<ProjectOverview> updateProject(
    String slug,
    Map<String, dynamic> data,
  ) async {
    final response = await _api.put('/projects/$slug', data);

    return ProjectOverview.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<AgencyReportIndex> agencyReports(String projectSlug) async {
    final response = await _api.get('/projects/$projectSlug/agency-reports');

    return AgencyReportIndex.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<AgencyReportDetail> generateAgencyReport(
    String projectSlug,
    Map<String, String> visibility,
  ) async {
    final response = await _api.post('/projects/$projectSlug/agency-reports', {
      'visibility': visibility,
    });

    return AgencyReportDetail.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<AgencyReportDetail> agencyReport(String uuid) async {
    final response = await _api.get('/agency-reports/$uuid');

    return AgencyReportDetail.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<List<int>> agencyReportPdf(String uuid) =>
      _api.downloadBytes('/agency-reports/$uuid/pdf');

  Future<List<int>> agencyBriefPdf(String uuid) =>
      _api.downloadBytes('/agency-reports/$uuid/brief/pdf');

  Future<AgencyShare> shareAgencyReport(String uuid, int days) async {
    final response = await _api.post('/agency-reports/$uuid/share', {
      'days': days,
    });

    return AgencyShare.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<AgencyShare> revokeAgencyReportShare(String uuid) async {
    final response = await _api.delete('/agency-reports/$uuid/share');

    return AgencyShare.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  // --- محفظة الوكالة (نطاقها مساحة العمل كلها) ---

  Future<Map<String, dynamic>> portfolio() async {
    final response = await _api.get('/portfolio');

    return Map<String, dynamic>.from(response['data'] as Map);
  }

  // --- موجز التكليف: قراءةً وحفظًا ---

  Future<Map<String, dynamic>> agencyBrief(String projectSlug) async {
    final response = await _api.get('/projects/$projectSlug/agency-brief');

    return Map<String, dynamic>.from(response['data'] as Map);
  }

  Future<Map<String, dynamic>> saveAgencyBrief(
    String projectSlug,
    Map<String, dynamic> brief,
  ) async {
    final response = await _api.post(
      '/projects/$projectSlug/agency-brief',
      {'brief': brief},
    );

    return Map<String, dynamic>.from(response['data'] as Map);
  }

  // --- محرّر مخططات الاستشارة (آدمن) ---

  Future<List<dynamic>> adminConsultations() async {
    final response = await _api.get('/admin/consultations');

    return List<dynamic>.from(response['data'] as List);
  }

  Future<Map<String, dynamic>> adminConsultationVersion(int versionId) async {
    final response =
        await _api.get('/admin/consultations/versions/$versionId');

    return Map<String, dynamic>.from(response['data'] as Map);
  }

  Future<Map<String, dynamic>> adminCreateConsultationDraft(
    int blueprintId,
  ) async {
    final response = await _api
        .post('/admin/consultations/blueprints/$blueprintId/draft');

    return Map<String, dynamic>.from(response['data'] as Map);
  }

  Future<Map<String, dynamic>> adminUpdateConsultationQuestion(
    int versionId,
    int questionId,
    Map<String, dynamic> data,
  ) async {
    final response = await _api.put(
      '/admin/consultations/versions/$versionId/questions/$questionId',
      data,
    );

    return Map<String, dynamic>.from(response['data'] as Map);
  }

  Future<Map<String, dynamic>> adminPublishConsultation(int versionId) async {
    final response = await _api
        .post('/admin/consultations/versions/$versionId/publish');

    return Map<String, dynamic>.from(response['data'] as Map);
  }

  Future<Map<String, dynamic>> adminSimulateConsultation(
    int versionId,
    String projectSlug,
  ) async {
    final response = await _api.post(
      '/admin/consultations/versions/$versionId/simulate',
      {'project': projectSlug},
    );

    return Map<String, dynamic>.from(response['data'] as Map);
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

    return ToolDetail.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  // --- الأرصدة والخطط ---

  Future<BillingSummary> billing() async {
    final response = await _api.get('/billing');

    return BillingSummary.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  /// يبدأ شراء خطة: تحويل لبوابة، أو اكتمال فوري، أو انتظار اعتماد تحويل.
  Future<CheckoutOutcome> checkoutPlan(String planKey, {int? gatewayId}) async {
    final payload = <String, dynamic>{};
    if (gatewayId != null) payload['gateway_id'] = gatewayId;
    final response = await _api.post('/checkout/plan/$planKey', payload);

    return CheckoutOutcome.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<CheckoutOutcome> checkoutPack(int packId, {int? gatewayId}) async {
    final payload = <String, dynamic>{};
    if (gatewayId != null) payload['gateway_id'] = gatewayId;
    final response = await _api.post('/checkout/pack/$packId', payload);

    return CheckoutOutcome.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  // --- الإشعارات ---

  Future<NotificationList> notifications() async {
    final response = await _api.get('/notifications');

    return NotificationList(
      items: (response['data'] as List)
          .map(
            (e) =>
                AppNotification.fromJson(Map<String, dynamic>.from(e as Map)),
          )
          .toList(),
      unread: response['unread'] as int? ?? 0,
    );
  }

  Future<void> markNotificationRead(String id) =>
      _api.post('/notifications/$id/read');

  Future<void> markAllNotificationsRead() =>
      _api.post('/notifications/read-all');

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
            : Engagement.fromJson(
                Map<String, dynamic>.from(map['engagement'] as Map),
              ),
      );
    }).toList();
  }

  // --- التشغيل ---

  Future<ToolRunModel> startRun(String projectSlug, String toolKey) async {
    final response = await _api.post(
      '/projects/$projectSlug/tools/$toolKey/runs',
    );

    return ToolRunModel.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<ToolRunModel> run(String uuid) async {
    final response = await _api.get('/runs/$uuid');

    return ToolRunModel.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<ToolRunModel> saveStep(
    String uuid,
    int step,
    Map<String, dynamic> answers,
  ) async {
    final response = await _api.put('/runs/$uuid/steps/$step', {
      'answers': answers,
    });

    return ToolRunModel.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<HybridInsights> runInsights(
    String uuid,
    Map<String, dynamic> answers, {
    bool includeAi = false,
    int? step,
  }) async {
    final response = await _api.post('/runs/$uuid/insights', {
      'answers': answers,
      'include_ai': includeAi,
      'step': ?step,
    });

    return HybridInsights.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  /// كفاية إجابة مفتوحة كما هي الآن — حتميّة، بلا حفظ وبلا استهلاك سقف.
  ///
  /// يعيد null لما لا يُقاس (اختيار/فارغ) أو حين يتعذّر الجلب: القياس معونة على
  /// السؤال لا شرط للإجابة عنه، فلا يفشل ولا يمنع الكتابة.
  Future<AnswerFitnessResult?> answerFitness(
    String projectSlug, {
    required String fieldKey,
    required String type,
    required String value,
  }) async {
    final response = await _api.post('/projects/$projectSlug/answer-fitness', {
      'field_key': fieldKey,
      'type': type,
      'value': value,
    });

    final data = response['data'];
    if (data is! Map) return null;

    return AnswerFitnessResult.fromJson(Map<String, dynamic>.from(data));
  }

  /// دليل ومقترحات لسؤال — يولّدها نموذج، فتُحجز من سقف المساحة قبل الطلب.
  ///
  /// يعيد null حين لا تتوفر مساعدة (سقف مُستنفد أو لا مقترح): المساعدة معونة على
  /// السؤال لا شرط للإجابة عنه، فلا تفشل ولا تمنع الكتابة.
  Future<AssistDraftModel?> assist(
    String projectSlug, {
    required String surface,
    required String questionKey,
    String? runUuid,
    String? sessionUuid,
  }) async {
    final response = await _api.post('/projects/$projectSlug/assist', {
      'surface': surface,
      'question_key': questionKey,
      'run_uuid': ?runUuid,
      'session_uuid': ?sessionUuid,
    });

    final data = response['data'];
    if (data is! Map) return null;

    final draft = AssistDraftModel.fromJson(Map<String, dynamic>.from(data));

    return draft.isEmpty ? null : draft;
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

    return CompetitorView.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<CompetitorView> addCompetitors(
    String projectSlug,
    String names,
  ) async {
    final response = await _api.post('/projects/$projectSlug/competitors', {
      'names': names,
    });

    return CompetitorView.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<CompetitorView> confirmCompetitor(int id) async {
    final response = await _api.post('/competitors/$id/confirm');

    return CompetitorView.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<CompetitorView> dismissCompetitor(int id) async {
    final response = await _api.post('/competitors/$id/dismiss');

    return CompetitorView.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<Preflight> preflight(String uuid) async {
    final response = await _api.get('/runs/$uuid/preflight');

    return Preflight.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<ToolRunModel> queueRun(String uuid) async {
    final response = await _api.post('/runs/$uuid/queue');

    return ToolRunModel.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<ToolRunModel> requestManualReview(String uuid) async {
    final response = await _api.post('/runs/$uuid/manual');

    return ToolRunModel.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<ToolRunModel> progress(String uuid) async {
    final response = await _api.get('/runs/$uuid/progress');

    return ToolRunModel.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<ToolRunModel> retryRun(String uuid) async {
    final response = await _api.post('/runs/$uuid/retry');

    return ToolRunModel.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  // --- التقارير ---

  Future<ReportDetail> report(int id) async {
    final response = await _api.get('/reports/$id');
    final data = Map<String, dynamic>.from(response['data'] as Map);

    data.addAll({
      'comparison': response['comparison'],
      'watcher': response['watcher'],
      'my_verdict': response['my_verdict'],
      'suggestion': response['suggestion'],
    });

    return ReportDetail.fromJson(data);
  }

  /// بايتات PDF التقرير، مُصدَّقة بالرمز.
  Future<List<int>> reportPdf(int id) => _api.downloadBytes('/reports/$id/pdf');

  Future<ScoreComparison?> reportComparison(int id) async {
    final response = await _api.get('/reports/$id');
    final comparison = response['comparison'];

    return comparison == null
        ? null
        : ScoreComparison.fromJson(
            Map<String, dynamic>.from(comparison as Map),
          );
  }

  Future<ReportWatcherModel> watchReport(int id) async {
    final response = await _api.post('/reports/$id/watch');

    return ReportWatcherModel.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<ReportWatcherModel> unwatchReport(int id) async {
    final response = await _api.post('/reports/$id/unwatch');

    return ReportWatcherModel.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<String> submitReportFeedback(int id, String verdict) async {
    final response = await _api.post('/reports/$id/feedback', {
      'verdict': verdict,
    });

    return (response['data'] as Map)['verdict'] as String;
  }

  Future<List<TaskModel>> convertRecommendations(
    int reportId, {
    int? recommendationId,
  }) async {
    final response = await _api.post(
      '/reports/$reportId/tasks',
      recommendationId == null
          ? const {}
          : {'recommendation_id': recommendationId},
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
        (value as List)
            .map((e) => TaskModel.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList(),
      ),
    );
  }

  Future<TaskModel> updateTask(int id, String status) async {
    final response = await _api.patch('/tasks/$id', {'status': status});

    return TaskModel.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
  }

  Future<void> createKpi(String projectSlug, Map<String, dynamic> data) =>
      _api.post('/projects/$projectSlug/kpis', data);

  Future<void> recordKpi(int kpiId, double value) =>
      _api.post('/kpis/$kpiId/entries', {'value': value});

  // --- محرك النمو ---

  Future<List<Map<String, dynamic>>> pulse() async {
    final response = await _api.get('/pulse');

    return (response['data'] as List)
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
  }

  /// نسخ تسجيل صوتي إلى نص لسؤال مفتوح.
  ///
  /// يعيد النص فقط ولا يحفظ إجابة: المراجعة شرط لا تحسين — النسخ العربي يخطئ
  /// في الأسماء والأرقام، وما يدخل الدماغ بلا مراجعة يصير حقيقةً مصدرها خطأ.
  /// نفس قاعدة الويب حرفيًّا.
  Future<String> transcribeVoice(
    String projectSlug,
    String filePath,
    int seconds,
  ) async {
    final response = await _api.uploadAudio(
      '/projects/$projectSlug/voice',
      filePath,
      seconds,
    );

    return Map<String, dynamic>.from(response['data'] as Map)['text'] as String;
  }

  /// التشخيص الكامل: درجة النضج والمحاور الثمانية وقائمة الإصلاح.
  ///
  /// نفس عقد الويب حرفيًّا — الخادم يحسب والتطبيق يعرض. أي اشتقاق هنا يجعل
  /// التطبيق يقول رقمًا يخالف الموقع بلا سبب ظاهر.
  Future<Map<String, dynamic>> readiness(String projectSlug) async {
    final response = await _api.get('/projects/$projectSlug/readiness');

    return Map<String, dynamic>.from(response['data'] as Map);
  }

  /// تشغيل التدقيق التقني للموقع.
  Future<Map<String, dynamic>> runReadinessAudit(String projectSlug) async {
    final response = await _api.post('/projects/$projectSlug/readiness/audit');

    return Map<String, dynamic>.from(response['data'] as Map);
  }

  /// بطاقة الجاهزية PDF — نظير تنزيل الويب، محروسة بـ`diagnosis.full` في الخادم.
  Future<List<int>> readinessCardPdf(String projectSlug) =>
      _api.downloadBytes('/projects/$projectSlug/readiness/pdf');

  /// تقرير الحضور في إجابات النماذج وخريطة المصادر.
  ///
  /// `metrics` تعود null حين لا دورة استطلاع بعد — لا أصفار: «لم يُقَس»
  /// و«قِيس فكان صفرًا» حالتان مختلفتان، وعرض الثانية مكان الأولى حكمٌ على
  /// نشاط لم يُفحص.
  Future<Map<String, dynamic>> presence(String projectSlug) async {
    final response = await _api.get('/projects/$projectSlug/presence');

    return Map<String, dynamic>.from(response['data'] as Map);
  }

  /// بدء دورة استطلاع. ترفع ApiException برسالة السقف حين تنفد الميزانية.
  Future<void> probePresence(String projectSlug) async {
    await _api.post('/projects/$projectSlug/presence/probe');
  }

  Future<Map<String, dynamic>> geo(String projectSlug) async {
    final response = await _api.get('/projects/$projectSlug/geo');

    return Map<String, dynamic>.from(response['data'] as Map);
  }

  Future<Map<String, dynamic>> generateGeo(String projectSlug) async {
    final response = await _api.post('/projects/$projectSlug/geo');

    return Map<String, dynamic>.from(response['data'] as Map);
  }

  Future<List<int>> geoLlms(String projectSlug) =>
      _api.downloadBytes('/projects/$projectSlug/geo/llms.txt');

  Future<Map<String, dynamic>> personas(String projectSlug) async {
    final response = await _api.get('/projects/$projectSlug/personas');

    return Map<String, dynamic>.from(response['data'] as Map);
  }

  Future<Map<String, dynamic>> buildPersonaPanel(String projectSlug) async {
    final response = await _api.post('/projects/$projectSlug/personas');

    return Map<String, dynamic>.from(response['data'] as Map);
  }

  Future<Map<String, dynamic>> testPersonaPanel(
    String projectSlug,
    String message,
  ) async {
    final response = await _api.post('/projects/$projectSlug/personas/tests', {
      'message': message,
    });

    return Map<String, dynamic>.from(response['data'] as Map);
  }

  Future<Map<String, dynamic>> runFullDiagnosis(
    String projectSlug, {
    String mode = 'auto',
  }) async {
    final response = await _api.post('/projects/$projectSlug/full-diagnosis', {
      'mode': mode,
    });

    return Map<String, dynamic>.from(response['data'] as Map);
  }

  // --- الإدارة ---

  Future<Map<String, dynamic>> adminDashboard() =>
      _adminMap('/admin/dashboard');

  Future<Map<String, dynamic>> adminUsage({int days = 30}) =>
      _adminMap('/admin/usage', {'days': '$days'});

  Future<List<Map<String, dynamic>>> adminTools() => _adminList('/admin/tools');

  Future<Map<String, dynamic>> adminTool(String key) =>
      _adminMap('/admin/tools/$key');

  Future<Map<String, dynamic>> adminCatalog() => _adminMap('/admin/catalog');

  Future<List<Map<String, dynamic>>> adminUsers({String query = ''}) async {
    final response = await _api.get('/admin/users', {'q': query});

    return (response['data'] as List)
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
  }

  Future<List<Map<String, dynamic>>> adminPayments() =>
      _adminList('/admin/payments');

  Future<Map<String, dynamic>> adminManualReports() =>
      _adminMap('/admin/manual-reports');

  Future<List<Map<String, dynamic>>> adminSettings() =>
      _adminList('/admin/settings');

  Future<void> adminUpdateUser(int userId, Map<String, dynamic> data) =>
      _api.put('/admin/users/$userId', data);

  Future<void> adminCreateTool(Map<String, dynamic> data) =>
      _api.post('/admin/tools', data);

  Future<void> adminUpdateTool(String key, Map<String, dynamic> data) =>
      _api.put('/admin/tools/$key', data);

  Future<void> adminSetToolStatus(String key, String status) => _api.patch(
    '/admin/tools/$key/status',
    {'status': status, 'confirmation': true},
  );

  Future<void> adminDeleteTool(String key) =>
      _api.delete('/admin/tools/$key', {'confirmation': true});

  Future<void> adminCreateFeature(Map<String, dynamic> data) =>
      _api.post('/admin/features', data);

  Future<void> adminUpdateFeature(int id, Map<String, dynamic> data) =>
      _api.put('/admin/features/$id', data);

  Future<void> adminDeleteFeature(int id) =>
      _api.delete('/admin/features/$id', {'confirmation': true});

  Future<void> adminCreatePlan(Map<String, dynamic> data) =>
      _api.post('/admin/plans', data);

  Future<void> adminUpdatePlan(int id, Map<String, dynamic> data) =>
      _api.put('/admin/plans/$id', data);

  Future<void> adminDeletePlan(int id) =>
      _api.delete('/admin/plans/$id', {'confirmation': true});

  Future<void> adminCreatePack(Map<String, dynamic> data) =>
      _api.post('/admin/packs', data);

  Future<void> adminUpdatePack(int id, Map<String, dynamic> data) =>
      _api.put('/admin/packs/$id', data);

  Future<void> adminDeletePack(int id) =>
      _api.delete('/admin/packs/$id', {'confirmation': true});

  Future<void> adminCreateGateway(Map<String, dynamic> data) =>
      _api.post('/admin/gateways', data);

  Future<void> adminUpdateGateway(int id, Map<String, dynamic> data) =>
      _api.put('/admin/gateways/$id', data);

  Future<void> adminDeleteGateway(int id) =>
      _api.delete('/admin/gateways/$id', {'confirmation': true});

  Future<Map<String, dynamic>> adminManualReport(String uuid) =>
      _adminMap('/admin/manual-reports/$uuid');

  Future<void> adminSubmitManualReport(
    String uuid,
    Map<String, dynamic> payload,
  ) => _api.post('/admin/manual-reports/$uuid', {'payload': payload});

  Future<void> adminGrantCredits(int userId, int credits) => _api.post(
    '/admin/users/$userId/credits',
    {'credits': credits, 'confirmation': true},
  );

  Future<void> adminAssignPlan({
    required int workspaceId,
    required int planId,
    String creditPolicy = 'keep',
    String effective = 'now',
  }) => _api.post('/admin/users/plans/assign', {
    'workspace_ids': [workspaceId],
    'plan_id': planId,
    'credit_policy': creditPolicy,
    'effective': effective,
    'confirmation': true,
  });

  Future<Map<String, dynamic>> adminToggleUser(int userId) async {
    final response = await _api.patch('/admin/users/$userId/admin', {
      'confirmation': true,
    });

    return Map<String, dynamic>.from(response['data'] as Map);
  }

  Future<void> adminApprovePayment(int paymentId) =>
      _api.post('/admin/payments/$paymentId/approve', {'confirmation': true});

  Future<void> adminRejectPayment(int paymentId) =>
      _api.post('/admin/payments/$paymentId/reject', {'confirmation': true});

  Future<Map<String, dynamic>> adminToggleGateway(int gatewayId) async {
    final response = await _api.patch('/admin/gateways/$gatewayId/toggle', {
      'confirmation': true,
    });

    return Map<String, dynamic>.from(response['data'] as Map);
  }

  Future<void> adminTestGateway(int gatewayId) =>
      _api.post('/admin/gateways/$gatewayId/test', {'confirmation': true});

  Future<void> adminDefaultGateway(int gatewayId) =>
      _api.patch('/admin/gateways/$gatewayId/default', {'confirmation': true});

  Future<void> adminUpdateSettings(Map<String, dynamic> values) =>
      _api.put('/admin/settings', values);

  Future<void> adminUpdatePrompt(
    String toolKey,
    int promptId, {
    required String content,
    required String tier,
  }) => _api.put('/admin/tools/$toolKey/prompts/$promptId', {
    'content': content,
    'tier': tier,
  });

  Future<Map<String, dynamic>> _adminMap(
    String path, [
    Map<String, String>? query,
  ]) async {
    final response = await _api.get(path, query);

    return Map<String, dynamic>.from(response['data'] as Map);
  }

  Future<List<Map<String, dynamic>>> _adminList(String path) async {
    final response = await _api.get(path);

    return (response['data'] as List)
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
  }
}

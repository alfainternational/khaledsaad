/// مسارات الـ API الموحّدة (نسبية إلى Env.apiBaseUrl).
/// المعرّفات في المسار = public_id.
class ApiEndpoints {
  const ApiEndpoints._();

  // الصحة والمصادقة
  static const String ping = '/ping';

  // المحتوى العام (تجربة الضيف — بلا مصادقة)
  static const String publicOverview = '/public/overview';
  static const String tokens = '/tokens';
  static const String register = '/register';
  static const String logout = '/logout';
  static const String passwordForgot = '/password/forgot';
  static const String passwordReset = '/password/reset';

  // المستخدم ومساحات العمل
  static const String me = '/me';
  static const String workspaces = '/workspaces';

  static String workspace(String ws) => '/workspaces/$ws';
  static String onboarding(String ws) => '/workspaces/$ws/onboarding';
  static String dashboard(String ws) => '/workspaces/$ws/dashboard';

  // الحساب ومفتاح الذكاء الخاص (BYOK)
  static String account(String ws) => '/workspaces/$ws/account';
  static String accountAiKey(String ws) => '/workspaces/$ws/account/ai-key';

  // الفريق
  static String team(String ws) => '/workspaces/$ws/team';
  static String teamInvitations(String ws) =>
      '/workspaces/$ws/team/invitations';

  // المشاريع
  static String projects(String ws) => '/workspaces/$ws/projects';
  static String project(String ws, String p) => '/workspaces/$ws/projects/$p';
  static String projectBrief(String ws, String p) =>
      '/workspaces/$ws/projects/$p/brief';
  static String projectAudit(String ws, String p) =>
      '/workspaces/$ws/projects/$p/audit';
  static String projectAuditStatus(String ws, String p) =>
      '/workspaces/$ws/projects/$p/audit/status';
  static String projectRecommendations(String ws, String p) =>
      '/workspaces/$ws/projects/$p/recommendations';
  static String projectReport(String ws, String p) =>
      '/workspaces/$ws/projects/$p/report';
  static String projectReportPdf(String ws, String p) =>
      '/workspaces/$ws/projects/$p/report/pdf';
  static String projectDossier(String ws, String p) =>
      '/workspaces/$ws/projects/$p/dossier';
  static String projectDossierPdf(String ws, String p) =>
      '/workspaces/$ws/projects/$p/dossier/pdf';

  // الأدوات
  static String tools(String ws) => '/workspaces/$ws/tools';
  static String toolLoad(String ws, String p, String tcode) =>
      '/workspaces/$ws/projects/$p/tools/$tcode';
  static String toolRun(String ws, String p, String tcode) =>
      '/workspaces/$ws/projects/$p/tools/$tcode/run';

  // الاستوديو الذكي
  static String studioTemplates(String ws) => '/workspaces/$ws/templates';
  static String studioGenerations(String ws) =>
      '/workspaces/$ws/studio/generations';
  static String studioGeneration(String ws, String gen) =>
      '/workspaces/$ws/studio/generations/$gen';
  static String studioGenerationExport(String ws, String gen, String format) =>
      '/workspaces/$ws/studio/generations/$gen/export/$format';

  // مساعد الذكاء
  static String aiChat(String ws) => '/workspaces/$ws/ai/chat';
  static String aiConversations(String ws) =>
      '/workspaces/$ws/ai/conversations';
  static String aiConversation(String ws, String conversation) =>
      '/workspaces/$ws/ai/conversations/$conversation';
  static String aiConversationMessages(String ws, String conversation) =>
      '/workspaces/$ws/ai/conversations/$conversation/messages';
  static String aiConversationMessage(
    String ws,
    String conversation,
    String message,
  ) => '/workspaces/$ws/ai/conversations/$conversation/messages/$message';
  static String aiAnalyze(String ws) => '/workspaces/$ws/ai/analyze';
  static String aiSuggest(String ws) => '/workspaces/$ws/ai/suggest';
  static String aiResearch(String ws) => '/workspaces/$ws/ai/research';

  // حزم التنفيذ (ضمن نطاق المساحة)
  static String executionPackage(String ws, String pkg) =>
      '/workspaces/$ws/execution-packages/$pkg';
  static String executionPackageStatus(String ws, String pkg) =>
      '/workspaces/$ws/execution-packages/$pkg/status';
  static String recommendationPackage(String ws, String p, String rec) =>
      '/workspaces/$ws/projects/$p/recommendations/$rec/package';

  // الموافقات (ضمن نطاق المساحة — لا يوجد مسار عام /approvals).
  static String approvals(String ws) => '/workspaces/$ws/approvals';
  static String approval(String ws, int id) => '/workspaces/$ws/approvals/$id';
  static String projectApprovals(String ws, String p) =>
      '/workspaces/$ws/projects/$p/approvals';

  // الفوترة
  static String billing(String ws) => '/workspaces/$ws/billing';
  static String billingSubscribe(String ws) =>
      '/workspaces/$ws/billing/subscribe';
  static String billingCancel(String ws) => '/workspaces/$ws/billing/cancel';
}

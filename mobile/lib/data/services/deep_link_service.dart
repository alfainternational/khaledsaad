import 'dart:async';

import 'package:app_links/app_links.dart';
import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../core/error/api_exception.dart';
import '../repositories/billing_repository.dart';
import '../repositories/collab_repository.dart';
import 'session_service.dart';
import 'workspace_service.dart';

/// خدمة روابط عميقة عامة تعيش طوال عمر التطبيق.
///
/// تعالج فجوة عودة الدفع (PayPal): سابقاً كان الاستماع محصوراً في شاشة الفوترة،
/// فيضيع تأكيد الاشتراك إذا أُغلق التطبيق أثناء الدفع (cold start) أو غادر
/// المستخدم الشاشة. هنا نلتقط الرابط الأولي (getInitialLink) والبثّ المستمر معاً،
/// ونؤكّد الاشتراك تلقائياً أياً كانت الشاشة الحالية.
class DeepLinkService extends GetxService {
  final _appLinks = AppLinks();
  StreamSubscription<Uri>? _sub;

  /// يزداد بعد كل تأكيد دفع ناجح — تستمع له شاشة الفوترة لتحديث نفسها إن كانت مفتوحة.
  final billingRefreshTick = 0.obs;

  @override
  void onInit() {
    super.onInit();
    _init();
  }

  Future<void> _init() async {
    // عودة الدفع بعد إعادة تشغيل التطبيق (cold start) — الرابط الذي أطلق التطبيق.
    try {
      final initial = await _appLinks.getInitialLink();
      if (initial != null) await _handle(initial);
    } catch (_) {
      // لا رابط أولي أو غير مدعوم — نتجاهل.
    }
    _sub = _appLinks.uriLinkStream.listen(_handle);
  }

  Future<void> _handle(Uri uri) async {
    switch (uri.host) {
      case 'billing':
        await _handleBilling(uri);
        break;
      case 'auth':
        if (uri.path.contains('reset')) {
          _handlePasswordReset(uri);
        } else {
          await _handleSocialAuth(uri);
        }
        break;
      case 'team':
        await _handleTeamInvite(uri);
        break;
    }
  }

  /// قبول دعوة فريق: ksgrowth://team/invite?token=...
  Future<void> _handleTeamInvite(Uri uri) async {
    final token = uri.queryParameters['token'] ?? '';
    if (token.isEmpty) return;

    final authed = Get.isRegistered<SessionService>() &&
        Get.find<SessionService>().isAuthenticated.value;
    if (!authed) {
      Get.snackbar('دعوة فريق', 'سجّل الدخول أولاً ثم افتح رابط الدعوة.',
          snackPosition: SnackPosition.BOTTOM);
      Get.offAllNamed(Routes.login);
      return;
    }

    try {
      await Get.find<CollabRepository>().acceptInvitation(token);
      if (Get.isRegistered<WorkspaceService>()) {
        await Get.find<WorkspaceService>().loadWorkspaces();
      }
      Get.snackbar('دعوة فريق', 'تم قبول الدعوة والانضمام لمساحة العمل.',
          snackPosition: SnackPosition.BOTTOM);
      Get.offAllNamed(Routes.home);
    } on ApiException catch (e) {
      Get.snackbar('دعوة فريق', e.message,
          snackPosition: SnackPosition.BOTTOM);
    }
  }

  /// عودة تسجيل الدخول الاجتماعي: ksgrowth://auth/social?token=...&workspace=...
  Future<void> _handleSocialAuth(Uri uri) async {
    final error = uri.queryParameters['error'];
    if (error != null && error.isNotEmpty) {
      Get.snackbar('تسجيل الدخول', _socialError(error),
          snackPosition: SnackPosition.BOTTOM);
      return;
    }

    final token = uri.queryParameters['token'] ?? '';
    if (token.isEmpty || !Get.isRegistered<SessionService>()) return;

    final session = Get.find<SessionService>();
    await session.setToken(token);

    final ws = uri.queryParameters['workspace'];
    if (ws != null && ws.isNotEmpty) {
      await session.setActiveWorkspace(ws);
    }

    Get.offAllNamed(Routes.home);
  }

  /// عودة رابط إعادة تعيين كلمة المرور: ksgrowth://auth/reset?token=&email=
  void _handlePasswordReset(Uri uri) {
    Get.toNamed(Routes.resetPassword, arguments: {
      'token': uri.queryParameters['token'] ?? '',
      'email': uri.queryParameters['email'] ?? '',
    });
  }

  String _socialError(String code) {
    switch (code) {
      case 'account_frozen':
        return 'الحساب غير نشط. تواصل مع الدعم.';
      case 'social_auth_failed':
        return 'تعذّر تسجيل الدخول عبر المزوّد. حاول مجدداً.';
      default:
        return 'تعذّر إكمال تسجيل الدخول.';
    }
  }

  Future<void> _handleBilling(Uri uri) async {
    if (uri.path.contains('cancelled')) {
      Get.snackbar('الفوترة', 'أُلغيت عملية الدفع.',
          snackPosition: SnackPosition.BOTTOM);
      return;
    }

    final subId = uri.queryParameters['subscription_id'] ??
        uri.queryParameters['token'] ??
        '';
    if (subId.isEmpty) return;

    // معرّف المساحة المحفوظ متاح حتى قبل تحميل قائمة المساحات (cold start).
    final ws = Get.isRegistered<SessionService>()
        ? Get.find<SessionService>().activeWorkspaceId.value
        : null;
    if (ws == null || ws.isEmpty) return;

    try {
      final result = await Get.find<BillingRepository>().confirmCallback(ws, subId);
      Get.snackbar(
        'الفوترة',
        result['message']?.toString() ??
            (result['activated'] == true ? 'تم تفعيل خطتك.' : 'لم يكتمل الدفع.'),
        snackPosition: SnackPosition.BOTTOM,
      );
      billingRefreshTick.value++;
    } on ApiException catch (e) {
      Get.snackbar('الفوترة', e.message, snackPosition: SnackPosition.BOTTOM);
    }
  }

  @override
  void onClose() {
    _sub?.cancel();
    super.onClose();
  }
}

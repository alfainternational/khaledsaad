import '../../core/config/api_endpoints.dart';
import '../../core/network/api_client.dart';

/// الفوترة: نظرة الاشتراك، بدء اشتراك PayPal، تأكيد العودة، الإلغاء، وأجهزة الإشعارات.
class BillingRepository {
  BillingRepository(this._api);

  final ApiClient _api;

  Future<Map<String, dynamic>> overview(String ws) async {
    final res = await _api.get(ApiEndpoints.billing(ws));
    return _asMap(res['data']);
  }

  /// يبدأ الاشتراك ويعيد approval_url ليُفتح في المتصفح.
  Future<({String approvalUrl, String paypalSubscriptionId})> subscribe(
    String ws, {
    required String planCode,
    required String billingCycle, // monthly | annual
  }) async {
    final res = await _api.post(ApiEndpoints.billingSubscribe(ws), body: {
      'plan_code': planCode,
      'billing_cycle': billingCycle,
    });
    final data = _asMap(res['data']);
    return (
      approvalUrl: data['approval_url']?.toString() ?? '',
      paypalSubscriptionId: data['paypal_subscription_id']?.toString() ?? '',
    );
  }

  /// تأكيد العودة من PayPal (بعد الـ deep link).
  Future<Map<String, dynamic>> confirmCallback(
      String ws, String paypalSubscriptionId) async {
    final res = await _api.post('/workspaces/$ws/billing/paypal/callback', body: {
      'subscription_id': paypalSubscriptionId,
    });
    return _asMap(res['data']);
  }

  Future<Map<String, dynamic>> cancel(String ws, {String? reason}) async {
    final res = await _api.post(ApiEndpoints.billingCancel(ws), body: {
      'reason': ?reason,
    });
    return _asMap(res['data']);
  }

  // ---- أجهزة الإشعارات ----

  Future<void> registerDevice({
    required String token,
    String platform = 'android',
    String? deviceName,
  }) =>
      _api.post('/devices', body: {
        'token': token,
        'platform': platform,
        'device_name': ?deviceName,
      });

  Future<void> unregisterDevice(String token) async {
    // DELETE بجسم — نستخدم dio مباشرة عبر post-like؛ ApiClient.delete لا يدعم body،
    // فنمرر التوكن كاستعلام مقبول من الخادم عبر validate على body فقط... الأبسط: POST بديل غير موجود.
    // الحل: أرسل عبر delete مع query — الخادم يقرأ من request->validate الذي يشمل query.
    await _api.delete('/devices?token=${Uri.encodeComponent(token)}');
  }

  static Map<String, dynamic> _asMap(dynamic v) =>
      v is Map ? Map<String, dynamic>.from(v) : <String, dynamic>{};
}

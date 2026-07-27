/// نماذج الحساب: الأرصدة والخطط والإشعارات.
/// تقرأ حمولات AccountController في الخادم حرفيًا.
library;

class PlanOption {
  const PlanOption({
    required this.key,
    required this.name,
    required this.price,
    required this.monthlyCredits,
    required this.projectLimit,
    required this.features,
    required this.isCurrent,
  });

  factory PlanOption.fromJson(Map<String, dynamic> json) => PlanOption(
    key: json['key'] as String,
    name: json['name'] as String,
    price: json['price'] as int? ?? 0,
    monthlyCredits: json['monthly_credits'] as int? ?? 0,
    projectLimit: json['project_limit'] as int? ?? 1,
    features: (json['features'] as List? ?? const [])
        .map((e) => e.toString())
        .toList(),
    isCurrent: json['is_current'] as bool? ?? false,
  );

  final String key;
  final String name;
  final int price;
  final int monthlyCredits;
  final int projectLimit;
  final List<String> features;
  final bool isCurrent;
}

class CreditPackOption {
  const CreditPackOption({
    required this.id,
    required this.name,
    required this.credits,
    required this.price,
    required this.currency,
  });

  factory CreditPackOption.fromJson(Map<String, dynamic> json) =>
      CreditPackOption(
        id: json['id'] as int,
        name: json['name'] as String,
        credits: json['credits'] as int? ?? 0,
        price: json['price'] as int? ?? 0,
        currency: json['currency'] as String? ?? 'SAR',
      );

  final int id;
  final String name;
  final int credits;
  final int price;
  final String currency;
}

class PaymentGatewayOption {
  const PaymentGatewayOption({
    required this.id,
    required this.provider,
    required this.label,
    required this.isDefault,
    this.instructions,
  });

  factory PaymentGatewayOption.fromJson(Map<String, dynamic> json) =>
      PaymentGatewayOption(
        id: json['id'] as int,
        provider: json['provider']?.toString() ?? '',
        label: json['label']?.toString() ?? '',
        isDefault: json['is_default'] == true,
        instructions: json['instructions']?.toString(),
      );

  final int id;
  final String provider;
  final String label;
  final bool isDefault;
  final String? instructions;
}

class PaymentHistoryItem {
  const PaymentHistoryItem({
    required this.id,
    required this.provider,
    required this.purpose,
    required this.amount,
    required this.currency,
    required this.status,
  });
  factory PaymentHistoryItem.fromJson(Map<String, dynamic> json) =>
      PaymentHistoryItem(
        id: json['id'] as int,
        provider: json['provider']?.toString() ?? '',
        purpose: json['purpose']?.toString() ?? '',
        amount: (json['amount'] as num?)?.toDouble() ?? 0,
        currency: json['currency']?.toString() ?? 'SAR',
        status:
            json['status_label']?.toString() ??
            json['status']?.toString() ??
            '',
      );
  final int id;
  final String provider;
  final String purpose;
  final double amount;
  final String currency;
  final String status;
}

class BillingSummary {
  const BillingSummary({
    required this.balance,
    required this.currentPlan,
    required this.projectCount,
    required this.projectLimit,
    required this.plans,
    required this.packs,
    required this.paymentsEnabled,
    required this.gateways,
    required this.payments,
  });

  factory BillingSummary.fromJson(Map<String, dynamic> json) => BillingSummary(
    balance: json['balance'] as int? ?? 0,
    currentPlan: json['current_plan'] as String? ?? 'free',
    projectCount: json['project_count'] as int? ?? 0,
    projectLimit: json['project_limit'] as int? ?? 1,
    paymentsEnabled: json['payments_enabled'] as bool? ?? false,
    gateways: (json['gateways'] as List? ?? const [])
        .map(
          (e) => PaymentGatewayOption.fromJson(
            Map<String, dynamic>.from(e as Map),
          ),
        )
        .toList(),
    payments: (json['payments'] as List? ?? const [])
        .map(
          (e) =>
              PaymentHistoryItem.fromJson(Map<String, dynamic>.from(e as Map)),
        )
        .toList(),
    plans: (json['plans'] as List? ?? const [])
        .map((e) => PlanOption.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList(),
    packs: (json['packs'] as List? ?? const [])
        .map(
          (e) => CreditPackOption.fromJson(Map<String, dynamic>.from(e as Map)),
        )
        .toList(),
  );

  final int balance;
  final String currentPlan;
  final int projectCount;
  final int projectLimit;
  final bool paymentsEnabled;
  final List<PaymentGatewayOption> gateways;
  final List<PaymentHistoryItem> payments;
  final List<PlanOption> plans;
  final List<CreditPackOption> packs;
}

/// نتيجة بدء الشراء كما يعيدها الخادم.
///
/// ثلاث حالات لا حالتان: تحويل لبوابة، أو اكتمال فوري، أو انتظار اعتماد
/// (تحويل بنكي). كان غياب الحالة الثالثة يجعل التطبيق يقول «أُضيف رصيدك»
/// لدفعة لم تُعتمد بعد.
class CheckoutOutcome {
  const CheckoutOutcome({
    this.redirectUrl,
    this.completed = false,
    this.pendingApproval = false,
    this.message,
  });

  factory CheckoutOutcome.fromJson(Map<String, dynamic> json) =>
      CheckoutOutcome(
        redirectUrl: json['redirect_url'] as String?,
        completed: json['completed'] as bool? ?? false,
        pendingApproval: json['pending_approval'] as bool? ?? false,
        message: json['message'] as String?,
      );

  final String? redirectUrl;
  final bool completed;
  final bool pendingApproval;
  final String? message;
}

class AppNotification {
  const AppNotification({
    required this.id,
    required this.title,
    required this.body,
    required this.read,
    this.url,
  });

  factory AppNotification.fromJson(Map<String, dynamic> json) =>
      AppNotification(
        id: json['id'] as String,
        title: json['title'] as String? ?? 'إشعار',
        body: json['body'] as String? ?? '',
        read: json['read'] as bool? ?? false,
        url: json['url'] as String?,
      );

  final String id;
  final String title;
  final String body;
  final bool read;
  final String? url;
}

class NotificationList {
  const NotificationList({required this.items, required this.unread});

  final List<AppNotification> items;
  final int unread;
}

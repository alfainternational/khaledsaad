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
        features: (json['features'] as List? ?? const []).map((e) => e.toString()).toList(),
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

  factory CreditPackOption.fromJson(Map<String, dynamic> json) => CreditPackOption(
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

class BillingSummary {
  const BillingSummary({
    required this.balance,
    required this.currentPlan,
    required this.projectCount,
    required this.projectLimit,
    required this.plans,
    required this.packs,
    required this.paymentsEnabled,
  });

  factory BillingSummary.fromJson(Map<String, dynamic> json) => BillingSummary(
        balance: json['balance'] as int? ?? 0,
        currentPlan: json['current_plan'] as String? ?? 'free',
        projectCount: json['project_count'] as int? ?? 0,
        projectLimit: json['project_limit'] as int? ?? 1,
        paymentsEnabled: json['payments_enabled'] as bool? ?? false,
        plans: (json['plans'] as List? ?? const [])
            .map((e) => PlanOption.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList(),
        packs: (json['packs'] as List? ?? const [])
            .map((e) => CreditPackOption.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList(),
      );

  final int balance;
  final String currentPlan;
  final int projectCount;
  final int projectLimit;
  final bool paymentsEnabled;
  final List<PlanOption> plans;
  final List<CreditPackOption> packs;
}

class AppNotification {
  const AppNotification({
    required this.id,
    required this.title,
    required this.body,
    required this.read,
    this.url,
  });

  factory AppNotification.fromJson(Map<String, dynamic> json) => AppNotification(
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

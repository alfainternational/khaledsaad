/// موديلات الفوترة — تحويل رد `GET /workspaces/{ws}/billing` من Map خام إلى
/// أنواع typed. المصدر: BillingController::show + PlanResource.
///
/// شكل الرد (data):
/// ```
/// {
///   "plans": [ { code, name_ar, name_en, monthly_price, annual_price, features } ],
///   "current_plan_code": String?,
///   "subscription": { status, billing_cycle, current_period_end, has_paypal } | null,
///   "is_owner": bool,
///   "ai_credits_balance": num,
///   "paypal_ready": bool
/// }
/// ```
library;

/// باقة واحدة من قائمة الباقات المتاحة.
class BillingPlan {
  const BillingPlan({
    required this.code,
    required this.nameAr,
    required this.nameEn,
    required this.monthlyPrice,
    required this.annualPrice,
    required this.features,
  });

  final String code;
  final String? nameAr;
  final String? nameEn;

  /// السعر الشهري/السنوي كما يُعيدهما الخادم (قد يكون int أو double أو null).
  final num? monthlyPrice;
  final num? annualPrice;

  /// `features_json` — صلاحيات الباقة (entitlements) بصيغة مرنة.
  /// مثال المفاتيح: `workspaces.max`, `projects.max_per_workspace`, `*credits*`.
  final Map<String, dynamic> features;

  /// الاسم المعروض: العربي أولاً ثم الكود كحل أخير.
  String get displayName => nameAr?.isNotEmpty == true ? nameAr! : code;

  bool get isFree => code == 'free';

  /// السعر حسب دورة الفوترة: 'annual' → السنوي، غير ذلك → الشهري.
  num? priceFor(String billingCycle) =>
      billingCycle == 'annual' ? annualPrice : monthlyPrice;

  factory BillingPlan.fromJson(Map<String, dynamic> json) {
    return BillingPlan(
      code: json['code']?.toString() ?? '',
      nameAr: json['name_ar']?.toString(),
      nameEn: json['name_en']?.toString(),
      monthlyPrice: json['monthly_price'] is num
          ? json['monthly_price'] as num
          : num.tryParse(json['monthly_price']?.toString() ?? ''),
      annualPrice: json['annual_price'] is num
          ? json['annual_price'] as num
          : num.tryParse(json['annual_price']?.toString() ?? ''),
      features: json['features'] is Map
          ? Map<String, dynamic>.from(json['features'] as Map)
          : <String, dynamic>{},
    );
  }
}

/// حالة الاشتراك الحالي (null إن لم يوجد اشتراك بعد).
class BillingSubscription {
  const BillingSubscription({
    required this.status,
    required this.billingCycle,
    required this.currentPeriodEnd,
    required this.hasPaypal,
  });

  final String? status;
  final String? billingCycle;

  /// نهاية الفترة الحالية بصيغة ISO 8601 (String خام كما يُعاد).
  final String? currentPeriodEnd;

  /// هل الاشتراك مربوط بـ PayPal (يحدد إمكانية زر الإلغاء المدفوع).
  final bool hasPaypal;

  factory BillingSubscription.fromJson(Map<String, dynamic> json) {
    return BillingSubscription(
      status: json['status']?.toString(),
      billingCycle: json['billing_cycle']?.toString(),
      currentPeriodEnd: json['current_period_end']?.toString(),
      hasPaypal: json['has_paypal'] == true,
    );
  }
}

/// نظرة الفوترة الكاملة كما تستهلكها شاشة الفوترة.
class BillingOverview {
  const BillingOverview({
    required this.plans,
    required this.currentPlanCode,
    required this.subscription,
    required this.isOwner,
    required this.aiCreditsBalance,
    required this.paypalReady,
  });

  final List<BillingPlan> plans;
  final String? currentPlanCode;
  final BillingSubscription? subscription;
  final bool isOwner;

  /// رصيد المساعد الذكي الحالي.
  final num aiCreditsBalance;

  /// هل بوابة PayPal مُهيّأة (يحدد تفعيل أزرار الاشتراك).
  final bool paypalReady;

  /// هل للاشتراك الحالي ربط PayPal (يظهر زر الإلغاء المدفوع للمالك).
  bool get hasPaypal => subscription?.hasPaypal == true;

  /// الباقة الحالية المطابقة للكود (null إن لم تُوجد ضمن القائمة).
  BillingPlan? get currentPlan {
    for (final p in plans) {
      if (p.code == currentPlanCode) return p;
    }
    return null;
  }

  /// اسم الباقة الحالية للعرض: العربي ثم الكود ثم شرطة.
  String get currentPlanName =>
      currentPlan?.displayName ?? currentPlanCode ?? '—';

  factory BillingOverview.fromJson(Map<String, dynamic> json) {
    final rawPlans = json['plans'];
    final plans = <BillingPlan>[];
    if (rawPlans is List) {
      for (final p in rawPlans) {
        if (p is Map) {
          plans.add(BillingPlan.fromJson(Map<String, dynamic>.from(p)));
        }
      }
    }

    final rawSub = json['subscription'];
    return BillingOverview(
      plans: plans,
      currentPlanCode: json['current_plan_code']?.toString(),
      subscription: rawSub is Map
          ? BillingSubscription.fromJson(Map<String, dynamic>.from(rawSub))
          : null,
      isOwner: json['is_owner'] == true,
      aiCreditsBalance:
          json['ai_credits_balance'] is num ? json['ai_credits_balance'] as num : 0,
      paypalReady: json['paypal_ready'] == true,
    );
  }
}

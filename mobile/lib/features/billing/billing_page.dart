import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/error/api_exception.dart';
import '../../data/models/billing_models.dart';
import '../../data/repositories/billing_repository.dart';
import '../../data/services/deep_link_service.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';
import '../shared/widgets/ui_feedback.dart';

/// الفوترة: باقتك الحالية + رصيد الذكاء + الترقية عبر PayPal + الإلغاء.
/// الدفع يفتح في المتصفح ويعود عبر deep link: ksgrowth://billing/return.
class BillingPage extends StatefulWidget {
  const BillingPage({super.key});

  @override
  State<BillingPage> createState() => _BillingPageState();
}

class _BillingPageState extends State<BillingPage> {
  late final BillingRepository _repo = Get.find<BillingRepository>();
  late final WorkspaceService _workspaces = Get.find<WorkspaceService>();

  final _loading = true.obs;
  final _busy = RxnString(); // plan_code أثناء الاشتراك أو 'cancel'
  final _error = RxnString();
  final _data = Rxn<BillingOverview>();
  final _billingCycle = 'monthly'.obs;

  Worker? _refreshWorker;

  @override
  void initState() {
    super.initState();
    _load();
    // عودة الدفع تُعالَج مركزياً في DeepLinkService (تعمل حتى عند إغلاق الشاشة
    // أو إعادة تشغيل التطبيق). هنا نكتفي بتحديث العرض إذا أُكِّد دفعٌ والشاشة مفتوحة.
    if (Get.isRegistered<DeepLinkService>()) {
      _refreshWorker = ever(
        Get.find<DeepLinkService>().billingRefreshTick,
        (_) {
          if (mounted) _load();
        },
      );
    }
  }

  @override
  void dispose() {
    _refreshWorker?.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final ws = _workspaces.activeId;
    if (ws == null) {
      _loading.value = false;
      _error.value = 'لا توجد مساحة عمل نشطة.';
      return;
    }
    _loading.value = true;
    _error.value = null;
    try {
      _data.value = await _repo.overview(ws);
    } on ApiException catch (e) {
      _error.value = e.message;
    } finally {
      _loading.value = false;
    }
  }

  Future<void> _subscribe(String planCode) async {
    final ws = _workspaces.activeId;
    if (ws == null || _busy.value != null) return;
    _busy.value = planCode;
    try {
      final result = await _repo.subscribe(
        ws,
        planCode: planCode,
        billingCycle: _billingCycle.value,
      );
      final uri = Uri.parse(result.approvalUrl);
      if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
        UiFeedback.error('تعذر فتح صفحة الدفع.', title: 'الفوترة');
      }
    } on ApiException catch (e) {
      UiFeedback.error(e.message, title: 'الفوترة');
    } finally {
      _busy.value = null;
    }
  }

  Future<void> _cancel() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('إلغاء الاشتراك'),
        content: const Text(
            'سيُلغى اشتراكك المدفوع وتعود للباقة المجانية. هل أنت متأكد؟'),
        actions: [
          TextButton(
              onPressed: () => Get.back(result: false),
              child: const Text('تراجع')),
          FilledButton(
              onPressed: () => Get.back(result: true),
              child: const Text('إلغاء الاشتراك')),
        ],
      ),
    );
    if (confirmed != true) return;

    final ws = _workspaces.activeId;
    if (ws == null || _busy.value != null) return;
    _busy.value = 'cancel';
    try {
      final result = await _repo.cancel(ws);
      UiFeedback.success(
          result['message']?.toString() ?? 'تم إلغاء الاشتراك.',
          title: 'الفوترة');
      await _load();
    } on ApiException catch (e) {
      UiFeedback.error(e.message, title: 'الفوترة');
    } finally {
      _busy.value = null;
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('الفوترة والباقات')),
      body: Obx(() {
        if (_loading.value && _data.value == null) {
          return AppStateView.loading();
        }
        if (_error.value != null && _data.value == null) {
          return AppStateView.error(message: _error.value, onRetry: _load);
        }
        final data = _data.value;
        final plans = data?.plans ?? const <BillingPlan>[];
        final currentCode = data?.currentPlanCode;
        final isOwner = data?.isOwner == true;
        final paypalReady = data?.paypalReady == true;
        final credits = data?.aiCreditsBalance;
        final hasPaypal = data?.hasPaypal == true;

        // الباقة الحالية باسمها العربي بدل الكود الخام.
        final currentName = data?.currentPlanName ?? currentCode ?? '—';
        final features = data?.currentPlan?.features ?? <String, dynamic>{};

        return RefreshIndicator(
          onRefresh: _load,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              // بطاقة الحالة
              Card(
                color: theme.colorScheme.secondaryContainer,
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Icon(Icons.workspace_premium_outlined,
                              color: theme.colorScheme.primary),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text('باقتك الحالية: $currentName',
                                style: theme.textTheme.titleMedium
                                    ?.copyWith(fontWeight: FontWeight.w800)),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text('رصيد المساعد الذكي: ${credits ?? 0}',
                          style: theme.textTheme.bodyMedium),
                      if (hasPaypal && isOwner) ...[
                        const SizedBox(height: 12),
                        OutlinedButton.icon(
                          onPressed:
                              _busy.value == 'cancel' ? null : _cancel,
                          icon: _busy.value == 'cancel'
                              ? const SizedBox(
                                  height: 16,
                                  width: 16,
                                  child: CircularProgressIndicator(
                                      strokeWidth: 2))
                              : const Icon(Icons.cancel_outlined, size: 18),
                          label: const Text('إلغاء الاشتراك المدفوع'),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 12),
              _LimitsCard(
                credits: credits?.toDouble() ?? 0,
                features: features,
              ),
              const SizedBox(height: 16),
              if (!isOwner)
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Text(
                      'إدارة الفوترة متاحة لمالك الحساب أو مالك مساحة العمل فقط. يمكنك الاطلاع على الباقات هنا، وللترقية أو الإلغاء تواصل مع مالك الحساب.',
                      style: theme.textTheme.bodyMedium?.copyWith(height: 1.7),
                    ),
                  ),
                )
              else ...[
                // دورة الفوترة
                Obx(() => SegmentedButton<String>(
                      segments: const [
                        ButtonSegment(value: 'monthly', label: Text('شهري')),
                        ButtonSegment(value: 'annual', label: Text('سنوي')),
                      ],
                      selected: {_billingCycle.value},
                      onSelectionChanged: (s) => _billingCycle.value = s.first,
                    )),
                const SizedBox(height: 12),
                if (!paypalReady)
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(14),
                      child: Text(
                        'الدفع الإلكتروني غير مفعّل حالياً. تواصل مع الإدارة للترقية.',
                        style: theme.textTheme.bodyMedium,
                      ),
                    ),
                  ),
                ...plans.map((plan) {
                  final code = plan.code;
                  if (plan.isFree) return const SizedBox.shrink();
                  final isCurrent = code == currentCode;
                  final price = plan.priceFor(_billingCycle.value);
                  return Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      title: Text(plan.displayName,
                          style: theme.textTheme.titleSmall
                              ?.copyWith(fontWeight: FontWeight.w700)),
                      subtitle: Text(price != null
                          ? '${_formatPrice(price)} / ${_billingCycle.value == 'annual' ? 'سنة' : 'شهر'}'
                          : ''),
                      trailing: isCurrent
                          ? Chip(
                              label: const Text('الحالية'),
                              backgroundColor: theme
                                  .colorScheme.primary
                                  .withValues(alpha: 0.12),
                            )
                          : FilledButton.tonal(
                              onPressed: !paypalReady || _busy.value != null
                                  ? null
                                  : () => _subscribe(code),
                              child: _busy.value == code
                                  ? const SizedBox(
                                      height: 16,
                                      width: 16,
                                      child: CircularProgressIndicator(
                                          strokeWidth: 2))
                                  : const Text('اشترك'),
                            ),
                    ),
                  );
                }),
              ],
            ],
          ),
        );
      }),
    );
  }

  /// يُنسّق السعر والعملة موحّداً ومعزولاً اتجاهياً (LTR isolate) كي لا
  /// ينقلب ترتيب الرقم والرمز داخل نص عربي RTL.
  static String _formatPrice(dynamic price) {
    if (price == null) return '';
    return _ltrIsolate('\$$price');
  }
}

/// يعزل نصاً اتجاهياً LTR داخل سياق RTL دون وضع أحرف تحكّم حرفية في المصدر
/// (U+2066 … U+2069)، كي لا ينقلب ترتيب الأرقام/الرموز.
String _ltrIsolate(String s) =>
    '${String.fromCharCode(0x2066)}$s${String.fromCharCode(0x2069)}';

/// بطاقة الحدود والاستهلاك: رصيد الذكاء (كشريط تقدّم إن توفّر سقف) + حدود
/// الباقة الرقمية (مساحات/مشاريع) مشتقّة من entitlements الباقة الحالية.
class _LimitsCard extends StatelessWidget {
  const _LimitsCard({required this.credits, required this.features});

  final double credits;
  final Map<String, dynamic> features;

  static const _limitLabels = <String, (IconData, String)>{
    'workspaces.max': (Icons.workspaces_outline, 'مساحات العمل'),
    'projects.max_per_workspace':
        (Icons.folder_open, 'المشاريع لكل مساحة'),
  };

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    // سقف شهري للرصيد إن كان معرّفاً ضمن صلاحيات الباقة.
    double? creditCap;
    for (final e in features.entries) {
      if (e.key.contains('credit') && e.value is num) {
        creditCap = (e.value as num).toDouble();
        break;
      }
    }

    final rows = <Widget>[];
    _limitLabels.forEach((key, meta) {
      final raw = features[key];
      if (raw is num) {
        rows.add(_limitRow(theme, meta.$1, meta.$2, _formatLimit(raw)));
      }
    });

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('الحدود والاستهلاك',
                style: theme.textTheme.titleSmall
                    ?.copyWith(fontWeight: FontWeight.w800)),
            const SizedBox(height: 12),
            Row(
              children: [
                Icon(Icons.auto_awesome,
                    size: 18, color: theme.colorScheme.primary),
                const SizedBox(width: 8),
                Expanded(
                  child: Text('رصيد المساعد الذكي',
                      style: theme.textTheme.bodyMedium),
                ),
                Text(
                  creditCap != null
                      ? _ltrIsolate(
                          '${credits.toInt()}/${creditCap.toInt()}')
                      : _ltrIsolate('${credits.toInt()}'),
                  style: theme.textTheme.bodyMedium
                      ?.copyWith(fontWeight: FontWeight.w800),
                ),
              ],
            ),
            if (creditCap != null && creditCap > 0) ...[
              const SizedBox(height: 8),
              ClipRRect(
                borderRadius: BorderRadius.circular(999),
                child: LinearProgressIndicator(
                  value: (credits / creditCap).clamp(0, 1).toDouble(),
                  minHeight: 8,
                  backgroundColor:
                      theme.colorScheme.surfaceContainerHighest,
                ),
              ),
            ],
            if (rows.isNotEmpty) ...[
              const Divider(height: 24),
              ...rows,
            ],
          ],
        ),
      ),
    );
  }

  Widget _limitRow(
      ThemeData theme, IconData icon, String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        children: [
          Icon(icon, size: 18, color: theme.colorScheme.onSurfaceVariant),
          const SizedBox(width: 8),
          Expanded(child: Text(label, style: theme.textTheme.bodyMedium)),
          Text(value,
              style: theme.textTheme.bodyMedium
                  ?.copyWith(fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }

  /// القيم الكبيرة (999/9999...) تعني «غير محدود» في بذور الباقات.
  static String _formatLimit(num value) {
    if (value >= 999) return 'غير محدود';
    return _ltrIsolate('$value');
  }
}

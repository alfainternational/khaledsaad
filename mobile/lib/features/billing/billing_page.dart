import 'dart:async';

import 'package:app_links/app_links.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/error/api_exception.dart';
import '../../data/repositories/billing_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';

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
  final _data = Rxn<Map<String, dynamic>>();
  final _billingCycle = 'monthly'.obs;

  StreamSubscription<Uri>? _linkSub;

  @override
  void initState() {
    super.initState();
    _load();
    _listenForReturn();
  }

  @override
  void dispose() {
    _linkSub?.cancel();
    super.dispose();
  }

  /// يستمع لعودة PayPal عبر deep link ويؤكد الاشتراك تلقائياً.
  void _listenForReturn() {
    _linkSub = AppLinks().uriLinkStream.listen((uri) async {
      if (uri.host != 'billing') return;
      if (uri.path.contains('cancelled')) {
        Get.snackbar('الفوترة', 'أُلغيت عملية الدفع.',
            snackPosition: SnackPosition.BOTTOM);
        return;
      }
      final subId = uri.queryParameters['subscription_id'] ??
          uri.queryParameters['token'] ??
          '';
      if (subId.isEmpty) return;
      final ws = _workspaces.activeId;
      if (ws == null) return;
      try {
        final result = await _repo.confirmCallback(ws, subId);
        Get.snackbar(
          'الفوترة',
          result['message']?.toString() ??
              (result['activated'] == true ? 'تم تفعيل خطتك.' : 'لم يكتمل الدفع.'),
          snackPosition: SnackPosition.BOTTOM,
        );
        await _load();
      } on ApiException catch (e) {
        Get.snackbar('الفوترة', e.message, snackPosition: SnackPosition.BOTTOM);
      }
    });
  }

  Future<void> _load() async {
    final ws = _workspaces.activeId;
    if (ws == null) return;
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
        Get.snackbar('الفوترة', 'تعذر فتح صفحة الدفع.',
            snackPosition: SnackPosition.BOTTOM);
      }
    } on ApiException catch (e) {
      Get.snackbar('الفوترة', e.message, snackPosition: SnackPosition.BOTTOM);
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
      Get.snackbar('الفوترة',
          result['message']?.toString() ?? 'تم إلغاء الاشتراك.',
          snackPosition: SnackPosition.BOTTOM);
      await _load();
    } on ApiException catch (e) {
      Get.snackbar('الفوترة', e.message, snackPosition: SnackPosition.BOTTOM);
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
        final data = _data.value ?? {};
        final plans = (data['plans'] as List?) ?? const [];
        final currentCode = data['current_plan_code']?.toString();
        final isOwner = data['is_owner'] == true;
        final paypalReady = data['paypal_ready'] == true;
        final credits = data['ai_credits_balance'];
        final subscription = data['subscription'] is Map
            ? Map<String, dynamic>.from(data['subscription'] as Map)
            : null;
        final hasPaypal = subscription?['has_paypal'] == true;

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
                          Text('باقتك الحالية: ${currentCode ?? '—'}',
                              style: theme.textTheme.titleMedium
                                  ?.copyWith(fontWeight: FontWeight.w800)),
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
                ...plans.whereType<Map>().map((raw) {
                  final plan = Map<String, dynamic>.from(raw);
                  final code = plan['code']?.toString() ?? '';
                  if (code == 'free') return const SizedBox.shrink();
                  final isCurrent = code == currentCode;
                  final price = _billingCycle.value == 'annual'
                      ? plan['annual_price']
                      : plan['monthly_price'];
                  return Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      title: Text(plan['name_ar']?.toString() ?? code,
                          style: theme.textTheme.titleSmall
                              ?.copyWith(fontWeight: FontWeight.w700)),
                      subtitle: Text(price != null
                          ? '$price\$ / ${_billingCycle.value == 'annual' ? 'سنة' : 'شهر'}'
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
}

import 'package:flutter/material.dart';
import 'package:open_filex/open_filex.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';
import 'models.dart';

/// يقابل resources/views/app/billing/index.blade.php — الخطط وحزم الأرصدة.
/// المدفوع يمر بالبوابة (يُفتح رابط الدفع)، والمجاني/اليدوي يكتمل مباشرة.
class BillingScreen extends StatefulWidget {
  const BillingScreen({super.key, required this.repository});

  final PlatformRepository repository;

  @override
  State<BillingScreen> createState() => _BillingScreenState();
}

class _BillingScreenState extends State<BillingScreen> {
  late Future<BillingSummary> _future = widget.repository.billing();
  bool _busy = false;
  int? _selectedGatewayId;

  void _reload() => setState(() => _future = widget.repository.billing());

  Future<void> _buyPlan(PlanOption plan, BillingSummary billing) async {
    final gatewayId = plan.price == 0 ? null : await _chooseGateway(billing);
    if (plan.price > 0 && gatewayId == null) return;
    await _checkout(
      () => widget.repository.checkoutPlan(plan.key, gatewayId: gatewayId),
      done: 'فُعّلت خطة «${plan.name}».',
    );
  }

  Future<void> _buyPack(CreditPackOption pack, BillingSummary billing) async {
    final gatewayId = await _chooseGateway(billing);
    if (gatewayId == null) return;
    await _checkout(
      () => widget.repository.checkoutPack(pack.id, gatewayId: gatewayId),
      done: 'أُضيف رصيد «${pack.name}».',
    );
  }

  Future<int?> _chooseGateway(BillingSummary billing) async {
    if (billing.gateways.isEmpty) return null;
    if (billing.gateways.length == 1) return billing.gateways.first.id;
    final defaults = billing.gateways.where((item) => item.isDefault);
    final current =
        _selectedGatewayId ??
        (defaults.isNotEmpty ? defaults.first.id : billing.gateways.first.id);
    final selected = await showDialog<int>(
      context: context,
      builder: (context) => SimpleDialog(
        title: const Text('اختر وسيلة الدفع'),
        children: [
          RadioGroup<int>(
            groupValue: current,
            onChanged: (value) {
              if (value != null) Navigator.pop(context, value);
            },
            child: Column(
              children: [
                for (final gateway in billing.gateways)
                  RadioListTile<int>(
                    value: gateway.id,
                    title: Text(gateway.label),
                    subtitle: gateway.instructions == null
                        ? null
                        : Text(gateway.instructions!),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
    if (selected != null) setState(() => _selectedGatewayId = selected);
    return selected;
  }

  /// يُشغّل عملية الشراء: تحويل لبوابة، أو اكتمال فوري، أو انتظار اعتماد.
  Future<void> _checkout(
    Future<CheckoutOutcome> Function() action, {
    required String done,
  }) async {
    setState(() => _busy = true);

    try {
      final outcome = await action();
      final redirectUrl = outcome.redirectUrl;

      if (redirectUrl != null) {
        final uri = Uri.parse(redirectUrl);
        if (await canLaunchUrl(uri)) {
          await launchUrl(uri, mode: LaunchMode.externalApplication);
        } else {
          await OpenFilex.open(redirectUrl);
        }

        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('أكمل الدفع في المتصفح ثم عُد لتحديث رصيدك.'),
            ),
          );
        }
      } else if (mounted) {
        // الدفعة المعلّقة لا تُعلَن ناجحة: نقول للمستخدم حقيقتها.
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              outcome.completed
                  ? done
                  : (outcome.message ?? 'سجّلنا طلبك وهو قيد المراجعة.'),
            ),
          ),
        );
      }

      _reload();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AdaptiveScaffold(
      family: AdaptivePageFamily.operational,
      appBar: AppBar(
        title: const Text('الأرصدة والخطط'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _reload),
        ],
      ),
      body: FutureBuilder<BillingSummary>(
        future: _future,
        builder: (context, snapshot) => AsyncView(
          snapshot: snapshot,
          onRetry: _reload,
          builder: (billing) => ListView(
            padding: EdgeInsets.zero,
            children: [
              BrandCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Eyebrow('رصيدك'),
                    const SizedBox(height: 6),
                    Text(
                      '${billing.balance}',
                      style: const TextStyle(
                        fontSize: 40,
                        fontWeight: FontWeight.w700,
                        color: BrandColors.navy,
                      ),
                    ),
                    Text(
                      '${billing.projectCount} / ${billing.projectLimit} مشاريع',
                      style: const TextStyle(color: BrandColors.muted),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                'الخطط',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 12),
              for (final plan in billing.plans) ...[
                BrandCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        plan.name,
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        '${plan.price} ريال/شهر · ${plan.monthlyCredits} رصيد',
                        style: const TextStyle(color: BrandColors.muted),
                      ),
                      const SizedBox(height: 8),
                      for (final feature in plan.features)
                        Text(
                          '• $feature',
                          style: const TextStyle(fontSize: 13),
                        ),
                      const SizedBox(height: 12),
                      if (plan.isCurrent)
                        const SeverityBadge(
                          label: 'خطتك الحالية',
                          severity: 'low',
                        )
                      else
                        FilledButton(
                          onPressed: _busy
                              ? null
                              : () => _buyPlan(plan, billing),
                          child: Text(
                            plan.price == 0
                                ? 'التبديل إليها'
                                : 'اشترك — ${plan.price} ريال',
                          ),
                        ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
              ],

              if (billing.packs.isNotEmpty) ...[
                const SizedBox(height: 8),
                const Text(
                  'حزم الأرصدة',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 12),
                for (final pack in billing.packs) ...[
                  BrandCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          pack.name,
                          style: const TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${pack.credits} رصيد · ${pack.price} ${pack.currency}',
                          style: const TextStyle(color: BrandColors.muted),
                        ),
                        const SizedBox(height: 12),
                        FilledButton(
                          onPressed: _busy
                              ? null
                              : () => _buyPack(pack, billing),
                          child: const Text('اشترِ الآن'),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                ],
              ],

              if (!billing.paymentsEnabled)
                const Text(
                  'الدفع الإلكتروني غير متاح حاليًا. يمكنك العودة لاحقًا أو التواصل للاستفسار عن خيارات الدفع.',
                  style: TextStyle(color: BrandColors.muted, fontSize: 12),
                ),
              if (billing.payments.isNotEmpty) ...[
                const SizedBox(height: 20),
                const Text(
                  'سجل المدفوعات',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
                ),
                for (final payment in billing.payments)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    title: Text(
                      '${payment.amount.toStringAsFixed(2)} ${payment.currency} · ${payment.status}',
                    ),
                    subtitle: Text(
                      '${payment.purpose} · ${payment.provider} · #${payment.id}',
                    ),
                  ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

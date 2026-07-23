import 'package:flutter/material.dart';
import 'package:open_filex/open_filex.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
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

  void _reload() => setState(() => _future = widget.repository.billing());

  Future<void> _buyPlan(PlanOption plan) => _checkout(
        () => widget.repository.checkoutPlan(plan.key),
        done: 'فُعّلت خطة «${plan.name}».',
      );

  Future<void> _buyPack(CreditPackOption pack) => _checkout(
        () => widget.repository.checkoutPack(pack.id),
        done: 'أُضيف رصيد «${pack.name}».',
      );

  /// يُشغّل عملية الشراء: إن أعاد رابطًا فتحناه للدفع، وإلا اكتمل مباشرة.
  Future<void> _checkout(Future<String?> Function() action, {required String done}) async {
    setState(() => _busy = true);

    try {
      final redirectUrl = await action();

      if (redirectUrl != null) {
        final uri = Uri.parse(redirectUrl);
        if (await canLaunchUrl(uri)) {
          await launchUrl(uri, mode: LaunchMode.externalApplication);
        } else {
          await OpenFilex.open(redirectUrl);
        }

        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('أكمل الدفع في المتصفح ثم عُد لتحديث رصيدك.')),
          );
        }
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(done)));
      }

      _reload();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('الأرصدة والخطط'),
        actions: [IconButton(icon: const Icon(Icons.refresh), onPressed: _reload)],
      ),
      body: FutureBuilder<BillingSummary>(
        future: _future,
        builder: (context, snapshot) => AsyncView(
          snapshot: snapshot,
          onRetry: _reload,
          builder: (billing) => ListView(
            padding: const EdgeInsets.all(16),
            children: [
              BrandCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Eyebrow('رصيدك'),
                    const SizedBox(height: 6),
                    Text('${billing.balance}',
                        style: const TextStyle(
                            fontSize: 40, fontWeight: FontWeight.w700, color: BrandColors.navy)),
                    Text('${billing.projectCount} / ${billing.projectLimit} مشاريع',
                        style: const TextStyle(color: BrandColors.muted)),
                  ],
                ),
              ),
              const SizedBox(height: 20),
              const Text('الخطط',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
              const SizedBox(height: 12),
              for (final plan in billing.plans) ...[
                BrandCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(plan.name,
                          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                      const SizedBox(height: 4),
                      Text('${plan.price} ريال/شهر · ${plan.monthlyCredits} رصيد',
                          style: const TextStyle(color: BrandColors.muted)),
                      const SizedBox(height: 8),
                      for (final feature in plan.features)
                        Text('• $feature', style: const TextStyle(fontSize: 13)),
                      const SizedBox(height: 12),
                      if (plan.isCurrent)
                        const SeverityBadge(label: 'خطتك الحالية', severity: 'low')
                      else
                        FilledButton(
                          onPressed: _busy ? null : () => _buyPlan(plan),
                          child: Text(plan.price == 0 ? 'التبديل إليها' : 'اشترك — ${plan.price} ريال'),
                        ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
              ],

              if (billing.packs.isNotEmpty) ...[
                const SizedBox(height: 8),
                const Text('حزم الأرصدة',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
                const SizedBox(height: 12),
                for (final pack in billing.packs) ...[
                  BrandCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(pack.name,
                            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                        const SizedBox(height: 4),
                        Text('${pack.credits} رصيد · ${pack.price} ${pack.currency}',
                            style: const TextStyle(color: BrandColors.muted)),
                        const SizedBox(height: 12),
                        FilledButton(
                          onPressed: _busy ? null : () => _buyPack(pack),
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
                  'الدفع الإلكتروني غير مفعّل حاليًا. تُفعّله الإدارة من لوحة التحكم.',
                  style: TextStyle(color: BrandColors.muted, fontSize: 12),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

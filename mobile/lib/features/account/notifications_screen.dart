import 'package:flutter/material.dart';

import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';
import 'models.dart';

/// يقابل resources/views/app/notifications/index.blade.php
class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key, required this.repository});

  final PlatformRepository repository;

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  late Future<NotificationList> _future = widget.repository.notifications();

  void _reload() => setState(() => _future = widget.repository.notifications());

  bool _busy = false;

  /// تعليم الكل كمقروء — نظير `notifications.read-all` في الويب.
  ///
  /// قائمة إشعارات لا تُفرَّغ إلا واحدًا واحدًا تُهجَر بعد أسبوع، ومعها
  /// يُهجَر التنبيه نفسه — وهو المخرج المتكرر الوحيد.
  Future<void> _markAll() async {
    setState(() => _busy = true);

    try {
      await widget.repository.markAllNotificationsRead();
      if (mounted) _reload();
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _open(AppNotification notification) async {
    if (!notification.read) {
      await widget.repository.markNotificationRead(notification.id);
    }

    _reload();
  }

  @override
  Widget build(BuildContext context) {
    return AdaptiveScaffold(
      family: AdaptivePageFamily.operational,
      appBar: AppBar(
        title: const Text('الإشعارات'),
        actions: [
          IconButton(
            tooltip: 'تعليم الكل كمقروء',
            icon: const Icon(Icons.done_all),
            onPressed: _busy ? null : _markAll,
          ),
        ],
      ),
      body: FutureBuilder<NotificationList>(
        future: _future,
        builder: (context, snapshot) => AsyncView(
          snapshot: snapshot,
          onRetry: _reload,
          builder: (list) {
            if (list.items.isEmpty) {
              return const Center(
                child: EmptyState(
                  title: 'لا إشعارات بعد',
                  message:
                      'ستظهر هنا تحديثات التقارير والمهام والرصيد عندما تحتاج إلى اطلاع أو إجراء.',
                ),
              );
            }

            return RefreshIndicator(
              onRefresh: () async => _reload(),
              child: ListView.separated(
                padding: EdgeInsets.zero,
                itemCount: list.items.length,
                separatorBuilder: (context, index) =>
                    const SizedBox(height: 10),
                itemBuilder: (context, index) {
                  final notification = list.items[index];

                  return Card(
                    clipBehavior: Clip.antiAlias,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(16),
                      side: BorderSide(
                        color: notification.read
                            ? BrandColors.line
                            : BrandColors.blue,
                      ),
                    ),
                    child: ListTile(
                      title: Text(
                        notification.title,
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                      subtitle: Text(notification.body),
                      trailing: notification.read
                          ? null
                          : const Icon(
                              Icons.circle,
                              size: 10,
                              color: BrandColors.blue,
                            ),
                      onTap: () => _open(notification),
                    ),
                  );
                },
              ),
            );
          },
        ),
      ),
    );
  }
}

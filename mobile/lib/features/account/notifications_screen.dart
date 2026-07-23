import 'package:flutter/material.dart';

import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
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

  Future<void> _open(AppNotification notification) async {
    if (!notification.read) {
      await widget.repository.markNotificationRead(notification.id);
    }

    _reload();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('الإشعارات')),
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
                  message: 'سنخبرك هنا حين يجهز تقرير أو تتأخر مهمة أو ينخفض رصيدك.',
                ),
              );
            }

            return RefreshIndicator(
              onRefresh: () async => _reload(),
              child: ListView.separated(
                padding: const EdgeInsets.all(16),
                itemCount: list.items.length,
                separatorBuilder: (context, index) => const SizedBox(height: 10),
                itemBuilder: (context, index) {
                  final notification = list.items[index];

                  return Card(
                    clipBehavior: Clip.antiAlias,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(16),
                      side: BorderSide(
                        color: notification.read ? BrandColors.line : BrandColors.blue,
                      ),
                    ),
                    child: ListTile(
                      title: Text(notification.title,
                          style: const TextStyle(fontWeight: FontWeight.w700)),
                      subtitle: Text(notification.body),
                      trailing: notification.read
                          ? null
                          : const Icon(Icons.circle, size: 10, color: BrandColors.blue),
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

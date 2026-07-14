import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../core/error/api_exception.dart';
import '../../core/l10n/ar_labels.dart';
import '../../data/models/collab_models.dart';
import '../../data/repositories/collab_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';
import '../shared/widgets/status_badge.dart';

/// مركز إشعارات داخلي — يجمع ما يحتاج انتباه المستخدم (الموافقات المعلّقة)
/// في نقطة واحدة، بدل تشتّته عبر الشاشات.
class NotificationsPage extends StatefulWidget {
  const NotificationsPage({super.key});

  @override
  State<NotificationsPage> createState() => _NotificationsPageState();
}

class _NotificationsPageState extends State<NotificationsPage> {
  final _repo = Get.find<CollabRepository>();
  final _workspaces = Get.find<WorkspaceService>();

  bool _loading = true;
  String? _error;
  List<ApprovalModel> _approvals = const [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final ws = _workspaces.activeId;
    if (ws == null) {
      setState(() {
        _loading = false;
        _error = 'لا توجد مساحة عمل نشطة.';
      });
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await _repo.approvals(ws);
      if (!mounted) return;
      setState(() {
        _approvals = res.approvals
            .where((a) => a.status == 'pending')
            .toList();
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = e.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = 'تعذّر تحميل الإشعارات.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('الإشعارات')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    if (_loading) return AppStateView.skeleton();
    if (_error != null) {
      return AppStateView.error(message: _error, onRetry: _load);
    }
    if (_approvals.isEmpty) {
      return ListView(
        children: [
          SizedBox(height: MediaQuery.of(context).size.height * 0.2),
          AppStateView.empty(
            icon: Icons.notifications_none,
            title: 'لا إشعارات جديدة',
            message: 'ستظهر هنا الموافقات المعلّقة وما يحتاج انتباهك.',
          ),
        ],
      );
    }
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: _approvals.length,
      separatorBuilder: (_, _) => const SizedBox(height: 10),
      itemBuilder: (context, i) {
        final a = _approvals[i];
        return Card(
          child: ListTile(
            leading: const Icon(Icons.fact_check_outlined),
            title: Text(
              'طلب موافقة: ${ArLabels.value(a.itemType)}',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
            subtitle: a.projectName != null
                ? Text('المشروع: ${a.projectName}',
                    maxLines: 1, overflow: TextOverflow.ellipsis)
                : null,
            trailing: StatusBadge(status: a.status),
            onTap: () => Get.toNamed(Routes.approvals),
          ),
        );
      },
    );
  }
}

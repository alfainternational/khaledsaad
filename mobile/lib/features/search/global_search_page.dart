import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../core/error/api_exception.dart';
import '../../data/models/project_model.dart';
import '../../data/repositories/project_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';
import '../shared/widgets/status_badge.dart';

/// بحث عام عبر المشاريع داخل مساحة العمل النشطة — نقطة وصول سريعة من أي مكان.
class GlobalSearchPage extends StatefulWidget {
  const GlobalSearchPage({super.key});

  @override
  State<GlobalSearchPage> createState() => _GlobalSearchPageState();
}

class _GlobalSearchPageState extends State<GlobalSearchPage> {
  final _repo = Get.find<ProjectRepository>();
  final _workspaces = Get.find<WorkspaceService>();
  final _controller = TextEditingController();

  bool _loading = true;
  String? _error;
  List<ProjectModel> _all = const [];
  String _query = '';

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
      final rows = await _repo.list(ws);
      if (!mounted) return;
      setState(() {
        _all = rows;
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
        _error = 'تعذّر تحميل النتائج.';
      });
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  List<ProjectModel> get _results {
    final q = _query.trim();
    if (q.isEmpty) return _all;
    return _all
        .where((p) =>
            p.name.contains(q) || (p.client?.name.contains(q) ?? false))
        .toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: TextField(
          controller: _controller,
          autofocus: true,
          textInputAction: TextInputAction.search,
          decoration: const InputDecoration(
            hintText: 'ابحث في مشاريعك…',
            border: InputBorder.none,
          ),
          onChanged: (v) => setState(() => _query = v),
        ),
        actions: [
          if (_query.isNotEmpty)
            IconButton(
              tooltip: 'مسح',
              icon: const Icon(Icons.close),
              onPressed: () {
                _controller.clear();
                setState(() => _query = '');
              },
            ),
        ],
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_loading) return AppStateView.skeleton();
    if (_error != null) {
      return AppStateView.error(message: _error, onRetry: _load);
    }
    final results = _results;
    if (results.isEmpty) {
      return AppStateView.empty(
        icon: Icons.search_off,
        title: _query.isEmpty ? 'لا توجد مشاريع بعد' : 'لا نتائج',
        message: _query.isEmpty
            ? 'أنشئ مشروعك الأول من قسم مشاريعي.'
            : 'جرّب كلمة بحث أخرى.',
      );
    }
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: results.length,
      separatorBuilder: (_, _) => const SizedBox(height: 10),
      itemBuilder: (context, i) {
        final p = results[i];
        return Card(
          child: ListTile(
            title: Text(p.name, maxLines: 1, overflow: TextOverflow.ellipsis),
            subtitle: p.client != null
                ? Text('العميل: ${p.client!.name}',
                    maxLines: 1, overflow: TextOverflow.ellipsis)
                : null,
            trailing: StatusBadge(status: p.status),
            onTap: () =>
                Get.toNamed(Routes.projectDetail, arguments: p.publicId),
          ),
        );
      },
    );
  }
}

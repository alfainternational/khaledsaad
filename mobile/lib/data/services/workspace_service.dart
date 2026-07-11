import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';

import '../../core/error/api_exception.dart';
import '../models/workspace_model.dart';
import '../repositories/workspace_repository.dart';
import 'session_service.dart';

/// يدير قائمة مساحات العمل والمساحة النشطة، مع كاش offline خفيف.
class WorkspaceService extends GetxService {
  WorkspaceService(this._repo, this._session);

  final WorkspaceRepository _repo;
  final SessionService _session;
  final _cache = GetStorage();

  static const _cacheKey = 'cache.workspaces';

  final workspaces = <WorkspaceModel>[].obs;
  final Rxn<WorkspaceModel> active = Rxn<WorkspaceModel>();
  final isLoading = false.obs;

  /// آخر تحميل جاء من الكاش (بلا اتصال).
  final isOffline = false.obs;

  String? get activeId => active.value?.publicId;

  Future<void> loadWorkspaces() async {
    isLoading.value = true;
    try {
      final rows = await _repo.list();
      isOffline.value = false;
      workspaces.assignAll(rows);
      _resolveActive(rows);
      // كاش للقراءة بلا اتصال.
      _cache.write(
        _cacheKey,
        rows
            .map((w) => {
                  'public_id': w.publicId,
                  'name': w.name,
                  'type': w.type,
                  'status': w.status,
                })
            .toList(),
      );
    } on ApiException catch (e) {
      // بلا اتصال → اعرض آخر نسخة معروفة إن وُجدت.
      final cached = _cache.read<List>(_cacheKey);
      if (e.isNetwork && cached != null && cached.isNotEmpty) {
        isOffline.value = true;
        final rows = cached
            .whereType<Map>()
            .map((m) => WorkspaceModel.fromJson(Map<String, dynamic>.from(m)))
            .toList();
        workspaces.assignAll(rows);
        _resolveActive(rows);
      } else {
        rethrow;
      }
    } finally {
      isLoading.value = false;
    }
  }

  void _resolveActive(List<WorkspaceModel> rows) {
    if (rows.isEmpty) {
      active.value = null;
      return;
    }
    final savedId = _session.activeWorkspaceId.value;
    active.value = rows.firstWhereOrNull((w) => w.publicId == savedId) ?? rows.first;
    if (active.value != null) {
      _session.setActiveWorkspace(active.value!.publicId);
    }
  }

  Future<void> setActive(WorkspaceModel workspace) async {
    active.value = workspace;
    await _session.setActiveWorkspace(workspace.publicId);
  }
}

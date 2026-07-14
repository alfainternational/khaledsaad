import 'dart:async';

import 'package:get/get.dart';

import '../../core/error/api_exception.dart';
import '../../core/utils/file_exporter.dart';
import '../../data/models/studio_models.dart';
import '../../data/repositories/studio_repository.dart';
import '../../data/services/workspace_service.dart';

class GenerationDetailController extends GetxController {
  GenerationDetailController(this._repo, this._workspaces, this.publicId);

  final StudioRepository _repo;
  final WorkspaceService _workspaces;
  final String publicId;

  final Rxn<StudioGeneration> generation = Rxn<StudioGeneration>();
  final isLoading = false.obs;
  final isExporting = false.obs;
  final error = RxnString();

  /// هل المخرج ما زال يُولَّد على الخادم (طابور)؟
  bool get isPending => generation.value?.isProcessing ?? false;

  Timer? _pollTimer;
  int _pollAttempts = 0;
  static const _maxPollAttempts = 20; // ‎~60 ثانية (كل 3ث)

  @override
  void onReady() {
    super.onReady();
    load();
  }

  @override
  void onClose() {
    _pollTimer?.cancel();
    super.onClose();
  }

  Future<void> load() async {
    final ws = _workspaces.activeId;
    if (ws == null) return;
    isLoading.value = true;
    error.value = null;
    try {
      generation.value = await _repo.show(ws, publicId);
      _pollAttempts = 0;
      _schedulePoll();
    } on ApiException catch (e) {
      error.value = e.message;
    } finally {
      isLoading.value = false;
    }
  }

  /// يجدول إعادة جلب دورية طالما المخرج قيد التوليد ولم يتجاوز الحدّ.
  void _schedulePoll() {
    _pollTimer?.cancel();
    final g = generation.value;
    if (g == null || !g.isProcessing) return;
    if (_pollAttempts >= _maxPollAttempts) return;
    _pollTimer = Timer(const Duration(seconds: 3), _poll);
  }

  Future<void> _poll() async {
    final ws = _workspaces.activeId;
    if (ws == null) return;
    _pollAttempts++;
    try {
      generation.value = await _repo.show(ws, publicId);
    } on ApiException catch (_) {
      // خطأ عابر أثناء الطابور — نعيد المحاولة ضمن الحدّ المسموح.
    }
    _schedulePoll();
  }

  /// يصدّر بصيغة (md/html/pdf) ثم يشارك الملف. يعيد رسالة خطأ إن فشل.
  Future<String?> export(String format) async {
    final ws = _workspaces.activeId;
    if (ws == null) return 'لا توجد مساحة عمل نشطة.';
    isExporting.value = true;
    try {
      final bytes = await _repo.export(ws, publicId, format);
      final ext = format == 'markdown' ? 'md' : format;
      await FileExporter.saveAndShare(bytes, 'studio-$publicId.$ext');
      return null;
    } on ApiException catch (e) {
      if (e.isEntitlementRequired) {
        return 'التصدير غير متاح في باقتك الحالية.';
      }
      return e.message;
    } finally {
      isExporting.value = false;
    }
  }
}

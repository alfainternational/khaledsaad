import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../core/error/api_exception.dart';
import '../../data/models/collab_models.dart';
import '../../data/repositories/collab_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';
import '../shared/widgets/ui_feedback.dart';

/// عملاء الوكالة — قائمة هادئة + إنشاء/تعديل عبر ورقة سفلية.
class ClientsPage extends StatefulWidget {
  const ClientsPage({super.key});

  @override
  State<ClientsPage> createState() => _ClientsPageState();
}

class _ClientsPageState extends State<ClientsPage> {
  late final CollabRepository _repo = Get.find<CollabRepository>();
  late final WorkspaceService _workspaces = Get.find<WorkspaceService>();

  final _clients = <AgencyClient>[].obs;
  final _loading = true.obs;
  final _error = RxnString();

  @override
  void initState() {
    super.initState();
    _load();
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
      _clients.assignAll(await _repo.clients(ws));
    } on ApiException catch (e) {
      _error.value = e.message;
    } finally {
      _loading.value = false;
    }
  }

  Future<void> _upsert([AgencyClient? existing]) async {
    final name = TextEditingController(text: existing?.name ?? '');
    final email = TextEditingController(text: existing?.email ?? '');
    final phone = TextEditingController(text: existing?.phone ?? '');
    final company = TextEditingController(text: existing?.company ?? '');
    final notes = TextEditingController(text: existing?.notes ?? '');
    var status = existing?.status ?? 'active';

    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (sheetContext) => Padding(
        padding: EdgeInsets.only(
          left: 20,
          right: 20,
          top: 20,
          bottom: MediaQuery.of(sheetContext).viewInsets.bottom + 20,
        ),
        child: StatefulBuilder(
          builder: (context, setSheetState) => SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(existing == null ? 'عميل جديد' : 'تعديل العميل',
                    style: Theme.of(context)
                        .textTheme
                        .titleMedium
                        ?.copyWith(fontWeight: FontWeight.w800)),
                const SizedBox(height: 16),
                TextField(
                    controller: name,
                    decoration: const InputDecoration(labelText: 'الاسم')),
                const SizedBox(height: 10),
                TextField(
                    controller: email,
                    keyboardType: TextInputType.emailAddress,
                    decoration:
                        const InputDecoration(labelText: 'البريد (اختياري)')),
                const SizedBox(height: 10),
                TextField(
                    controller: phone,
                    keyboardType: TextInputType.phone,
                    decoration:
                        const InputDecoration(labelText: 'الهاتف (اختياري)')),
                const SizedBox(height: 10),
                TextField(
                    controller: company,
                    decoration:
                        const InputDecoration(labelText: 'الشركة (اختياري)')),
                const SizedBox(height: 10),
                TextField(
                    controller: notes,
                    minLines: 2,
                    maxLines: 4,
                    decoration:
                        const InputDecoration(labelText: 'ملاحظات (اختياري)')),
                const SizedBox(height: 10),
                DropdownButtonFormField<String>(
                  initialValue: status,
                  decoration: const InputDecoration(labelText: 'الحالة'),
                  items: AgencyClient.statusLabels.entries
                      .map((e) =>
                          DropdownMenuItem(value: e.key, child: Text(e.value)))
                      .toList(),
                  onChanged: (v) => setSheetState(() => status = v ?? 'active'),
                ),
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: () => Get.back(result: true),
                  child: Text(existing == null ? 'إضافة' : 'حفظ'),
                ),
              ],
            ),
          ),
        ),
      ),
    );

    if (ok == true && name.text.trim().isNotEmpty) {
      final ws = _workspaces.activeId;
      if (ws != null) {
        final payload = {
          'name': name.text.trim(),
          'email': email.text.trim().isEmpty ? null : email.text.trim(),
          'phone': phone.text.trim().isEmpty ? null : phone.text.trim(),
          'company': company.text.trim().isEmpty ? null : company.text.trim(),
          'notes': notes.text.trim().isEmpty ? null : notes.text.trim(),
          'status': status,
        };
        try {
          if (existing == null) {
            await _repo.createClient(ws, payload);
          } else {
            await _repo.updateClient(ws, existing.publicId, payload);
          }
          await _load();
        } on ApiException catch (e) {
          UiFeedback.error(e.message, title: 'العملاء');
        }
      }
    }
    for (final c in [name, email, phone, company, notes]) {
      c.dispose();
    }
  }

  Future<void> _delete(AgencyClient client) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('حذف العميل'),
        content: Text('هل تريد حذف ${client.name}؟'),
        actions: [
          TextButton(
              onPressed: () => Get.back(result: false),
              child: const Text('إلغاء')),
          FilledButton(
              onPressed: () => Get.back(result: true),
              child: const Text('حذف')),
        ],
      ),
    );
    if (ok != true) return;
    final ws = _workspaces.activeId;
    if (ws == null) return;
    try {
      await _repo.deleteClient(ws, client.publicId);
      _clients.removeWhere((c) => c.publicId == client.publicId);
    } on ApiException catch (e) {
      UiFeedback.error(e.message, title: 'العملاء');
    }
  }

  @override
  Widget build(BuildContext context) {
    final isAgency = _workspaces.active.value?.isAgency ?? true;
    return Scaffold(
      appBar: AppBar(title: const Text('عملاء الوكالة')),
      // startFloat في RTL = أسفل اليمين، بعيداً عن زر المساعد العائم (أسفل اليسار).
      floatingActionButtonLocation: FloatingActionButtonLocation.startFloat,
      floatingActionButton: isAgency
          ? FloatingActionButton.extended(
              onPressed: () => _upsert(),
              icon: const Icon(Icons.person_add_alt),
              label: const Text('عميل جديد'),
            )
          : null,
      body: Obx(() {
        // حارس نوع المساحة: العملاء خاصون بمساحات الوكالة فقط — يمنع الفتح
        // عبر deep link من مساحة غير-وكالة.
        if (!(_workspaces.active.value?.isAgency ?? true)) {
          return AppStateView.empty(
            icon: Icons.workspace_premium_outlined,
            title: 'خاص بمساحات الوكالة',
            message:
                'إدارة العملاء متاحة في مساحات العمل من نوع «وكالة». بدّل إلى مساحة وكالة أو رقِّ باقتك.',
          );
        }
        if (_loading.value) return AppStateView.loading();
        if (_error.value != null && _clients.isEmpty) {
          return AppStateView.error(message: _error.value, onRetry: _load);
        }
        if (_clients.isEmpty) {
          return AppStateView.empty(
            icon: Icons.groups_outlined,
            title: 'لا يوجد عملاء بعد',
            message: 'أضف عميلك الأول من زر «عميل جديد».',
          );
        }
        return RefreshIndicator(
          onRefresh: _load,
          child: ListView.builder(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
            itemCount: _clients.length,
            itemBuilder: (_, i) {
              final client = _clients[i];
              return Card(
                margin: const EdgeInsets.only(bottom: 8),
                child: ListTile(
                  leading: CircleAvatar(child: Text(client.name.characters.first)),
                  title: Text(client.name),
                  subtitle: Text([
                    AgencyClient.statusLabels[client.status] ?? client.status ?? '',
                    if (client.projectsCount != null)
                      '${client.projectsCount} مشروع',
                  ].where((s) => s.isNotEmpty).join(' · ')),
                  trailing: PopupMenuButton<String>(
                    tooltip: 'خيارات العميل',
                    onSelected: (action) => action == 'edit'
                        ? _upsert(client)
                        : _delete(client),
                    itemBuilder: (_) => const [
                      PopupMenuItem(value: 'edit', child: Text('تعديل')),
                      PopupMenuItem(value: 'delete', child: Text('حذف')),
                    ],
                  ),
                  onTap: () => _upsert(client),
                ),
              );
            },
          ),
        );
      }),
    );
  }
}

import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../core/error/api_exception.dart';
import '../../data/models/collab_models.dart';
import '../../data/repositories/collab_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';
import '../shared/widgets/ui_feedback.dart';

/// الفريق: الأعضاء والدعوات + دعوة جديدة — قسمان هادئان.
class TeamPage extends StatefulWidget {
  const TeamPage({super.key});

  @override
  State<TeamPage> createState() => _TeamPageState();
}

class _TeamPageState extends State<TeamPage> {
  late final CollabRepository _repo = Get.find<CollabRepository>();
  late final WorkspaceService _workspaces = Get.find<WorkspaceService>();

  final _members = <TeamMember>[].obs;
  final _invitations = <TeamInvitation>[].obs;
  final _loading = true.obs;
  final _error = RxnString();

  /// تحقّق مبسّط من صيغة البريد قبل إرسال الدعوة.
  static final _emailPattern = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');

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
      final result = await _repo.team(ws);
      _members.assignAll(result.members);
      _invitations.assignAll(result.invitations);
    } on ApiException catch (e) {
      _error.value = e.message;
    } finally {
      _loading.value = false;
    }
  }

  Future<void> _invite() async {
    final email = TextEditingController();
    var role = 'editor';
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
          builder: (context, setSheetState) => Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('دعوة عضو جديد',
                  style: Theme.of(context)
                      .textTheme
                      .titleMedium
                      ?.copyWith(fontWeight: FontWeight.w800)),
              const SizedBox(height: 16),
              TextField(
                controller: email,
                keyboardType: TextInputType.emailAddress,
                decoration: const InputDecoration(
                  labelText: 'البريد الإلكتروني',
                  prefixIcon: Icon(Icons.mail_outline),
                ),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: role,
                decoration: const InputDecoration(labelText: 'الدور'),
                items: TeamMember.roleLabels.entries
                    .where((e) => e.key != 'owner')
                    .map((e) => DropdownMenuItem(
                        value: e.key, child: Text(e.value)))
                    .toList(),
                onChanged: (v) => setSheetState(() => role = v ?? 'editor'),
              ),
              const SizedBox(height: 20),
              FilledButton(
                onPressed: () => Get.back(result: true),
                child: const Text('إرسال الدعوة'),
              ),
            ],
          ),
        ),
      ),
    );

    if (ok == true) {
      final value = email.text.trim();
      if (!_emailPattern.hasMatch(value)) {
        UiFeedback.error('أدخل بريداً إلكترونياً صحيحاً.');
        email.dispose();
        return;
      }
      final ws = _workspaces.activeId;
      if (ws == null) {
        email.dispose();
        return;
      }
      try {
        await _repo.invite(ws, email: value, role: role);
        UiFeedback.success('أُرسلت الدعوة بنجاح.');
        await _load();
      } on ApiException catch (e) {
        UiFeedback.error(e.fieldError('email') ?? e.message);
      }
    }
    email.dispose();
  }

  Future<void> _removeMember(TeamMember member) async {
    final ws = _workspaces.activeId;
    if (ws == null) return;
    final ok = await _confirm(
        'حذف العضو', 'هل تريد إزالة ${member.name ?? 'العضو'} من الفريق؟');
    if (ok != true) return;
    try {
      await _repo.removeMember(ws, member.id);
      _members.removeWhere((m) => m.id == member.id);
      UiFeedback.success('تمت إزالة العضو.');
    } on ApiException catch (e) {
      UiFeedback.error(e.message);
    }
  }

  Future<void> _removeInvitation(TeamInvitation invitation) async {
    final ws = _workspaces.activeId;
    if (ws == null) return;
    final ok = await _confirm(
        'إلغاء الدعوة', 'هل تريد إلغاء الدعوة المرسلة إلى ${invitation.email}؟');
    if (ok != true) return;
    try {
      await _repo.removeInvitation(ws, invitation.id);
      _invitations.removeWhere((i) => i.id == invitation.id);
      UiFeedback.success('أُلغيت الدعوة.');
    } on ApiException catch (e) {
      UiFeedback.error(e.message);
    }
  }

  Future<bool?> _confirm(String title, String message) => showDialog<bool>(
        context: context,
        builder: (_) => AlertDialog(
          title: Text(title),
          content: Text(message),
          actions: [
            TextButton(
                onPressed: () => Get.back(result: false),
                child: const Text('إلغاء')),
            FilledButton(
                onPressed: () => Get.back(result: true),
                child: const Text('تأكيد')),
          ],
        ),
      );

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('الفريق')),
      // startFloat في RTL = أسفل اليمين، بعيداً عن زر المساعد العائم (أسفل اليسار).
      floatingActionButtonLocation: FloatingActionButtonLocation.startFloat,
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _invite,
        icon: const Icon(Icons.person_add_alt),
        label: const Text('دعوة'),
      ),
      body: Obx(() {
        if (_loading.value) return AppStateView.loading();
        if (_error.value != null) {
          return AppStateView.error(message: _error.value, onRetry: _load);
        }
        if (_members.isEmpty && _invitations.isEmpty) {
          return AppStateView.empty(
            icon: Icons.group_outlined,
            title: 'لا يوجد أعضاء بعد',
            message: 'ادعُ زميلاً للعمل معك في هذه المساحة.',
            actionLabel: 'دعوة عضو',
            onAction: _invite,
          );
        }
        return RefreshIndicator(
          onRefresh: _load,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
            children: [
              Text('الأعضاء',
                  style: theme.textTheme.titleMedium
                      ?.copyWith(fontWeight: FontWeight.w800)),
              const SizedBox(height: 8),
              ..._members.map((m) => Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      leading: CircleAvatar(
                        child: Text((m.name ?? '؟').characters.first),
                      ),
                      title: Text(m.name ?? m.email ?? '—'),
                      subtitle:
                          Text(TeamMember.roleLabels[m.role] ?? m.role),
                      trailing: m.role == 'owner'
                          ? null
                          : IconButton(
                              tooltip: 'إزالة العضو',
                              icon: const Icon(Icons.person_remove_outlined),
                              onPressed: () => _removeMember(m),
                            ),
                    ),
                  )),
              if (_invitations.isNotEmpty) ...[
                const SizedBox(height: 16),
                Text('الدعوات المعلّقة',
                    style: theme.textTheme.titleMedium
                        ?.copyWith(fontWeight: FontWeight.w800)),
                const SizedBox(height: 8),
                ..._invitations.map((i) => Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      child: ListTile(
                        leading: const Icon(Icons.mark_email_unread_outlined),
                        title: Text(i.email),
                        subtitle:
                            Text(TeamMember.roleLabels[i.role] ?? i.role),
                        trailing: IconButton(
                          tooltip: 'إلغاء الدعوة',
                          icon: const Icon(Icons.close),
                          onPressed: () => _removeInvitation(i),
                        ),
                      ),
                    )),
              ],
            ],
          ),
        );
      }),
    );
  }
}

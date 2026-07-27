import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/platform_repository.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';

/// يقابل resources/views/app/projects/create.blade.php
class ProjectFormScreen extends StatefulWidget {
  const ProjectFormScreen({super.key, required this.repository});

  final PlatformRepository repository;

  @override
  State<ProjectFormScreen> createState() => _ProjectFormScreenState();
}

class _ProjectFormScreenState extends State<ProjectFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _industry = TextEditingController();
  final _description = TextEditingController();
  final _geography = TextEditingController();

  String _stage = 'growth';
  bool _busy = false;
  String? _error;

  static const Map<String, String> _stages = {
    'idea': 'فكرة',
    'launch': 'إطلاق',
    'growth': 'نمو',
    'scale': 'توسّع',
  };

  @override
  void dispose() {
    _name.dispose();
    _industry.dispose();
    _description.dispose();
    _geography.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      await widget.repository.createProject({
        'name': _name.text.trim(),
        'industry': _industry.text.trim().isEmpty
            ? null
            : _industry.text.trim(),
        'stage': _stage,
        'description': _description.text.trim().isEmpty
            ? null
            : _description.text.trim(),
        'geography': _geography.text.trim().isEmpty
            ? null
            : _geography.text.trim(),
      });

      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (exception) {
      setState(() => _error = exception.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AdaptiveScaffold(
      family: AdaptivePageFamily.form,
      appBar: AppBar(title: const Text('إضافة مشروع')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            const Text(
              'أدخل المعلومات الأساسية مرة واحدة لتخصيص الأسئلة والتقارير، ويمكنك تعديلها لاحقًا.',
              style: TextStyle(color: Color(0xFF5D6B82)),
            ),
            const SizedBox(height: 20),

            if (_error != null) ...[
              ErrorNotice(message: _error!),
              const SizedBox(height: 16),
            ],

            TextFormField(
              controller: _name,
              decoration: const InputDecoration(labelText: 'اسم المشروع'),
              validator: (value) => (value == null || value.trim().isEmpty)
                  ? 'الاسم مطلوب.'
                  : null,
            ),
            const SizedBox(height: 14),

            TextFormField(
              controller: _industry,
              decoration: const InputDecoration(
                labelText: 'القطاع',
                hintText: 'تعليم، تجزئة، خدمات…',
              ),
            ),
            const SizedBox(height: 14),

            DropdownButtonFormField<String>(
              initialValue: _stage,
              decoration: const InputDecoration(labelText: 'مرحلة المشروع'),
              items: _stages.entries
                  .map(
                    (entry) => DropdownMenuItem(
                      value: entry.key,
                      child: Text(entry.value),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _stage = value ?? 'growth'),
            ),
            const SizedBox(height: 14),

            TextFormField(
              controller: _description,
              decoration: const InputDecoration(
                labelText: 'ماذا يقدم المشروع؟',
                helperText:
                    'اكتب وصفًا مباشرًا يفهمه شخص يتعرف إلى مشروعك للمرة الأولى.',
              ),
              maxLines: 3,
            ),
            const SizedBox(height: 14),

            TextFormField(
              controller: _geography,
              decoration: const InputDecoration(labelText: 'السوق الجغرافي'),
            ),
            const SizedBox(height: 24),

            FilledButton(
              onPressed: _busy ? null : _submit,
              child: _busy
                  ? const SizedBox(
                      height: 20,
                      width: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Text('احفظ المشروع'),
            ),
          ],
        ),
      ),
    );
  }
}
